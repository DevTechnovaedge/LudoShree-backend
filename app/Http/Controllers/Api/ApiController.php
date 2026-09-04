<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Slider;
use App\Models\Administrator;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;
use DB;
use Hash;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Transaction;
use App\Models\GameChallenge\Wallet;
use App\Models\GatewayPayment;
use App\Models\GameChallenge\CommissionHistory;
use App\Models\Financial\Financial;
use App\Models\ReferCodeRequest;

use App\Services\GameChallengeLkApiSubmitResolver;
use App\Services\GameChallengeStakeRefundService;
use App\Services\GameChallengeAutoSettleService;
use App\Services\GameChallengeWinnerPayoutService;
use App\Services\GameChallengeWaitingDismissService;
use App\Services\King\KingChallengeGateway;
use App\Services\LkGameApiService;
use App\Services\RegistrationWelcomeBonusService;
use App\Services\WalletService;
use App\Http\Resources\UserResource;
use App\Http\Resources\GameChallengeResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\WalletResource;
use App\Http\Resources\FinancialResource;
use App\Models\Notification\Notification;
use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
use App\Events\DemoEvent;
use App\Models\Financial\TransferCashback;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
// Log::debug("message");

class ApiController extends Controller
{
    
    private function normalizeMobile(string $mobile): string
      {
          $digits = preg_replace('/\D+/', '', $mobile) ?? '';
          if (strlen($digits) > 10) {
              return substr($digits, -10);
          }
          return $digits;
      }

      private function isCashierRoleName(?string $roleName): bool
      {
          $name = strtolower(trim((string) $roleName));
          return $name === 'cashier' || str_contains($name, 'cashier');
      }

      private function hasCashierAccess(?User $user): bool
      {
          if (!$user) {
              Log::info('[CashierAccess] denied: no authenticated user');
              return false;
          }

          // Keep endpoint authorization exactly consistent with profile payload logic.
          $payload = (new UserResource($user))->toArray(request());
          $allowed = ((int) ($payload['is_cashier'] ?? 0)) === 1;

          Log::info('[CashierAccess] resolved via UserResource', [
              'user_id' => $user->id,
              'mobile' => $user->mobile,
              'is_cashier_payload' => $payload['is_cashier'] ?? null,
              'allowed' => $allowed,
          ]);

          return $allowed;
      }

      /**
       * LK auto-settle for win/lose only when the match is not in cancel/dispute (admin handles those).
       */
      private function tryLkOfficialResultSettlement(GameChallenge $game_challenge, User $user): ?JsonResponse
      {
          return app(GameChallengeLkApiSubmitResolver::class)->maybeResolveAndRespond($game_challenge, $user);
      }

      private function userHasRunningGameChallenge(User $user): bool
      {
          return GameChallenge::runningForUser($user->id)->exists();
      }

      /**
       * App users that should receive cashier withdrawal FCM (explicit flag + admin "Cashier" role mobile match).
       *
       * @return \Illuminate\Support\Collection<int, User>
       */
    private function usersForCashierWithdrawalPush()
    {
        $recipients = collect();

        $explicit = User::query()
            ->where('is_cashier', 1)
            ->where('status', 1)
            ->whereNotNull('fcm_device_token')
            ->where('fcm_device_token', '!=', '')
            ->get(['id', 'name', 'mobile', 'fcm_device_token']);

        $recipients = $recipients->merge($explicit);

        $admins = Administrator::query()
            ->where('status', 1)
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->get(['mobile', 'role_id']);

        $roleNames = Role::whereIn('id', $admins->pluck('role_id')->filter()->unique()->values())
            ->pluck('name', 'id');

        foreach ($admins as $admin) {
            $roleName = $roleNames->get($admin->role_id);
            if (! $this->isCashierRoleName($roleName)) {
                continue;
            }
            $targetMobile = $this->normalizeMobile((string) $admin->mobile);
            if ($targetMobile === '') {
                continue;
            }

            $matching = User::query()
                ->where('status', 1)
                ->whereNotNull('fcm_device_token')
                ->where('fcm_device_token', '!=', '')
                ->get(['id', 'name', 'mobile', 'fcm_device_token'])
                ->filter(function (User $u) use ($targetMobile) {
                    return $this->normalizeMobile((string) ($u->mobile ?? '')) === $targetMobile;
                });

            $recipients = $recipients->merge($matching);
        }

        return $recipients->unique('id')->values();
    }

    /**
     * Push + in-app notification row when a user submits a pending withdrawal.
     */
    private function notifyCashiersPendingWithdrawal(Transaction $transaction, User $requestingUser): void
    {
        try {
            $cashiers = $this->usersForCashierWithdrawalPush();
            if ($cashiers->isEmpty()) {
                return;
            }

            $amt = number_format((float) $transaction->amount, 2, '.', '');
            $title = 'New withdrawal request';
            $body = sprintf(
                'Withdrawal request of ₹%s — Txn %s',
                $amt,
                $transaction->txn_id ?? (string) $transaction->id
            );

            $ids = [];

            foreach ($cashiers as $cashier) {
                $ids[] = $cashier->id;
                safe_notify(
                    $cashier->fcm_device_token,
                    $title,
                    $body,
                    'cashier_withdrawal',
                    null,
                    ['cashier_id' => $cashier->id]
                );
            }

            if ($ids !== []) {
                safe_notify(null, $title, $body, 'cashier_withdrawal', array_unique($ids));
            }
        } catch (\Throwable $e) {
            Log::warning('notifyCashiersPendingWithdrawal failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Bank details for cashier withdrawal screens: parse stored payment_info HTML from transfer(),
     * or fall back to the user's saved bank_account financial row.
     *
     * @return array{account_name: ?string, account_number: ?string, ifsc_code: ?string, payment_info_raw: ?string}
     */
    private function withdrawalBankDetailsForCashier(Transaction $transaction): array
    {
        $raw = $transaction->payment_info;
        $account_name = null;
        $account_number = null;
        $ifsc_code = null;

        if (is_string($raw) && $raw !== '') {
            if (preg_match('/Account Name\s*:\s*([^<\n\r]+)/iu', $raw, $m)) {
                $account_name = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if (preg_match('/Account Number\s*:\s*([^<\n\r]+)/iu', $raw, $m)) {
                $account_number = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if (preg_match('/IFSC\s*Code\s*:\s*([^<\n\r]+)/iu', $raw, $m)) {
                $ifsc_code = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        $missingCore = ($account_number === null || $account_number === '')
            && ($ifsc_code === null || $ifsc_code === '');

        if ($missingCore && $transaction->user_id) {
            $bank = Financial::query()
                ->where('user_id', $transaction->user_id)
                ->where('type', 'bank_account')
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->first();

            if ($bank) {
                $account_name = $account_name ?: $bank->account_name;
                $account_number = $account_number ?: $bank->account_number;
                $ifsc_code = $ifsc_code ?: $bank->ifsc_code;
            }
        }

        return [
            'account_name' => $account_name,
            'account_number' => $account_number,
            'ifsc_code' => $ifsc_code,
            'payment_info_raw' => is_string($raw) ? $raw : null,
        ];
    }

    /**
     * Total withdrawal amount approved successfully today (app timezone, by transaction updated_at).
     */
    private function todayWithdrawalApprovedTotalAmount(): float
    {
        return app(\App\Services\AdminDashboardStatsService::class)
            ->withdrawalApprovedSumForDate(Carbon::today()->toDateString());
    }

    public function user()
    {
        return auth('api')->user();
    }

    /**
     * The signed-in user re-read from the database.
     *
     * auth('api')->user() is resolved once per request, so it still holds the
     * balance from before anything this request credited or debited. Global
     * scopes are dropped because find() would otherwise return null for an
     * unverified mobile and blank out the wallet in the response.
     */
    private function freshUser()
    {
        $current = $this->user();

        return User::query()->withoutGlobalScopes()->find($current->id) ?? $current;
    }

    /**
     * Cheap balance read so the app can resync the wallet after any event that
     * moves money, without pulling the whole game table or wallet history.
     */
    public function wallet_balance()
    {
        return response()->json([
            'status' => true,
            'message' => 'Balance fetched successfully',
            'user' => new UserResource($this->freshUser()),
        ]);
    }

    public function home()
    {
        $arr                       =   array();

        # Home Slider
        $home_slider                =   getSliderViaCode('home-slider');

        if ($home_slider ?? 0):
            $home_slider->makeHidden(['class', 'status', 'type', 'created_at', 'updated_at', 'status_view']);
            if ($home_slider->slides ?? 0):
                $home_slider->slides->makeHidden(['slider_id', 'image', 'status_view', 'mobile_image', 'mobile_image_url']);
            endif;
        endif;
        # End Home Slider

        #   Support
        $support_data               =   [];

        $support_data               =   (object) [
            'deposit_issue_mobile'          => site_setting()->deposit_issue_support_mobile,
            'game_play_issue_mobile'        => site_setting()->game_play_issue_support_mobile,
            'telegram'                      => site_setting()->telegram_support,
            'email'                         => site_setting()->email,
        ];


        #   End Support

        # FCM Token
        $fcm_token = request()->fcm_token;

        $user                     =   User::find($this->user()->id);
        if ($fcm_token):
            $user->fcm_device_token        =   $fcm_token;
            $user->save();
        endif;
        # end FCM Token

        $arr                         =  [
            'status'                    => true,
            'message'                   => 'Successfully data fetched',
            'home_slider'               => $home_slider,
            'user'                      => new UserResource($user),
            'important_notification'    => site_setting()->important_notification,
            'support'                   => $support_data,
            'app_version'               => site_setting()->app_details?->app_version ?? null,
            'is_force_update'           => (bool) (site_setting()->app_details?->is_force_update ?? 0),
            'is_profile_updated'        =>  auth('api')->user()->is_profile_updated
        ];
        return response()->json($arr);
    }

    # Auth
    public function register(Request $request)
    {
        $arr                        =   array();

        $validator                  =   Validator::make($request->all(), [
            'name'                    =>  'required|alpha|min:3|max:255',
            'mobile'                        =>  'required|numeric|digits:10|unique:users',
            // 'email'                         =>  'required|email|max:255|unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'                    =>  false,
                'message'                   =>  $validator->errors()->first()
            ]);
        }

        $uid                        =   "LS" . random_int(10000000, 99999999);
        $profile                    =   uploadFile('profile', "profile/$uid/");

        $user_data                  =   array(
            'uid'            =>  $uid,
            'name'                  => request()->name,
            'mobile'                => request()->mobile,
            'email'                 => request()->email,
            'profile'               => $profile,
            'dob'                   => request()->dob,
            'state_id'              => request()->state_id,
            'registration_bonus_pending' => true,
        );

        $user                       =   User::create($user_data);

        if ($user) :
            app(RegistrationWelcomeBonusService::class)->grantIfEligible($user);
            $user->refresh();

            $user->makeVisible(['profile_url']);

            $access_token                       =   $user->createToken(env('APP_NAME'))->accessToken;
            $arr                    =   array(
                'status'            =>  true,
                'message'           =>  'User created successfully',
                'access_token'      =>  $access_token,
                'user'              =>  $user
            );
        else :
            $arr                    =   array(
                'status'    =>  false,
                'message'   =>  'some error occured'
            );

        endif;

        return response()->json($arr);
    }


    public function send_otp(Request $request)
    {


        $arr                                = [];
        $access_token                       = null;

        /************************************************************************
         * Validation
         ************************************************************************/
        $validator  =   Validator::make($request->all(), [
            'mobile'        => 'required|digits:10|numeric',
            'referral_code' => 'nullable|string|exists:users,refer_code',
        ]);

        if ($validator->fails()) {
            $arr    =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        }

        /************************************************************************
         * End Validation
         ************************************************************************/

        /************************************************************************
         * Check if the user already exists by mobile number
         ************************************************************************/
        $mobile         =   request()->mobile;
        $user           =   User::where('mobile', $mobile)->withoutGlobalScope('verified_mobile')->first();

        # Send Otp
        # Generete OTP
        //$otp                =   123456;
        $otp                =   random_int(100000, 999999);
        $otp_expires_at     =   carbon()->now()->addMinutes(10);

        $dummyMobiles = [
    
        ];

        if (in_array($mobile, $dummyMobiles)):
            $otp            =   123456;
        else:
            sms()->sendOTP($mobile, $otp);
        endif;
        # End Send Otp


        if ($user):
            # Save OTP to the user record
            $user->otp_expires_at   = $otp_expires_at;
            $user->otp              = $otp;
            $user->save();
        else:


            # If user doesn't exist, create a new user
            $uid            =  generate_uid();

            $user_data      =   [
                'uid'               => $uid,
                'mobile'            => $mobile,
                'name'              => "Ludo Shree",
                'otp_expires_at'    => $otp_expires_at,
                'otp'               => $otp,
                'refer_code'        => generate_alpa_numeric_code(7),
                'refer_income'        => 1,
                'registration_bonus_pending' => true,
            ];

            $user           =   User::create($user_data);

        endif;

        $arr            =   [
            'status'            => true,
            'message'           => "Otp sent successfully",
            'otp_expires_at'    => $otp_expires_at
        ];

        return response()->json($arr);
    }
    # End Send Otp

    public function verify_otp(Request $request)
       {
           $arr                                        =   array();
           $otp                                        =   request()->otp;
           $fcm_device_token                           =   request()->fcm_token;

           $validator                                  =   Validator::make(
               $request->all(),
               [
                   'mobile'            => 'required|digits:10|numeric|exists:users',
                   'otp'               => "required|numeric|exists:users"
               ],
               [
                   'mobile.exists' => 'The provided mobile number does not exist in our records.',
                   'otp.exists'    => 'Incorrect otp',
               ]
           );

           if ($validator->fails()) :
               $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
               return response()->json($arr);
           endif;

           $user                                   =   User::select('id', 'uid', 'name', 'mobile', 'win_wallet_amount', 'game_wallet_amount', 'fcm_device_token', 'refer_code', 'otp_expires_at', 'refer_by', 'registration_bonus_pending', 'is_mobile_verified')
               ->withoutGlobalScope('verified_mobile')
               ->whereMobile(request()->mobile)
               ->whereOtp($otp)
               ->first();

           
           if ($user) :

               /**********************************************
                * The OTP has expired.
                **********************************************/
               if (carbon()->parse(now())->gte(carbon()->parse($user->otp_expires_at))):
                   return response()->json(['status' => false,  'message' => 'The OTP has expired.']);
               endif;
               /**********************************************
                * End The OTP has expired.
                **********************************************/

               // Revoke previous tokens
               $user->tokens()->delete();

               $access_token                       =   $user->createToken(env('APP_NAME'))->accessToken;

               $shouldGrantRegistrationBonus       =   (bool) $user->registration_bonus_pending;

               if (!$user->is_mobile_verified) :
                   $user->is_mobile_verified       =   1;
                   $user->mobile_verified_at       =   date('Y-m-d H:i:m');
               endif;

               $user->fcm_device_token = $fcm_device_token;

               # refer_code_verify
               if (!$user->refer_by):
                   $ip = get_client_ip();
                   $referral_code_request              =   ReferCodeRequest::whereIpAddress($ip)->first();

                   if ($referral_code_request):
                       $referral_user          =   User::select('id')->whereReferCode($referral_code_request->refer_code)->first();

                       if ($referral_user):
                           if ($referral_user->refer_code != $referral_code_request->refer_code):
                               $user->refer_by         =   $referral_user->id == $user->id ? 0 : $referral_user->id;
                           endif;
                       endif;
                   endif;

                   if ($referral_code_request):
                       $referral_code_request->delete();
                   endif;
               endif;
               # End refer_code_verify

               $user->save();

               if ($shouldGrantRegistrationBonus) {
                   app(RegistrationWelcomeBonusService::class)->grantIfEligible($user);
               }

               // Reload full row so UserResource includes is_cashier and accessors see complete state.
               $userForResponse = User::withoutGlobalScope('verified_mobile')->findOrFail($user->id);

               $arr                                =   [
                   'status'        => true,
                   'message'       => "Mobile Number verified",
                   'access_token'  => $access_token,
                   'user'          => new UserResource($userForResponse)
               ];

           else :
               $arr                                =   array('status' => false, 'message' => "Invalid mobile or otp");
           endif;

           return response()->json($arr);
       }
    # End Verify Otp

    # Profile
    public function profile(Request $request)
    {
        $arr                                =   array();

        if (auth('api')->check()) :
            $user = auth('api')->user();
            // Fresh read so admin toggles (e.g. is_cashier) apply without forcing re-login.
            $fresh = User::withoutGlobalScope('verified_mobile')->find($user->id);
            $arr   = [
                'status'  => true,
                'message' => 'successfully fetched data',
                'user'    => new UserResource($fresh ?? $user),
            ];
        endif;

        return response()->json($arr);
    }
    # End Profile
    
    

    # Update Profile
    public function update_profile(Request $request)
    {
        $arr                                    =   array();

        $request_type                           =   request()->request_type;
        $user                                   =   $this->user();

        # Validation
        $validator                              =   Validator::make($request->all(), [
            'name'              => 'nullable|min:3|max:130',
            'email'             => 'nullable|email|unique:users,email,' . auth('api')->user()->id,
            'profile'           => 'nullable|mimes:jpeg,jpg,png|max:2048'
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;
        # End Validation


        # Profile
        $profile                            =   uploadFile('profile', "profile/$user->uid/");
        # End Profile


        if (request()->name):
            $user->name                         =   request()->name;
        endif;

        if (request()->email):
            $user->email                         =   request()->email;
        endif;

        if ($profile):
            $user->profile                      =   $profile;
        endif;

        $user->save();

        $arr                                =   array('status' => true, 'message' => 'Profile updated successfully', 'user' => new UserResource($user));

        return response()->json($arr);
    }
    # End Update Profile

    # Update KYC
    public function update_kyc()
    {
        $arr                                =   array();

        $document_type_id                   =   request()->document_type_id;
        $document_id                        =   request()->document_id;


        # Validation
        $validator                              =   Validator::make(request()->all(), [
            'document_type_id'      => 'required|exists:document_types,id',
            'document_id'           => [
                'required',
                // 'unique:users,document_id',
                function ($attribute, $value, $fail) {
                    // Apply custom validation logic if document_type_id is 1 (Aadhar)
                    if (request('document_type_id') == 1) {
                        // Example: Check if the document_id is a valid Aadhar number (12 digits)
                        if (!preg_match('/^\d{12}$/', $value)) {
                            $fail('Invalid Aadhar number.');
                        }
                    }
                },
                // Add the unique rule but only for users where kyc_status is 1
                Rule::unique('users', 'document_id')->where(function ($query) {
                    $query->where('kyc_status', 1);
                }),
            ],
            // 'kyc_document_front'    => 'required',
            // 'kyc_document_back'     => 'required'
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;
        # End Validation

        # Send Otp  : Aadhar Card
        if ($document_type_id == 1):

            $aadhar_kyc_result = crul((object) [
                'method'    => 'POST',
                'url'       => 'https://api.quickekyc.com/api/v1/aadhaar-v2/generate-otp',
                'post_data' =>  json_encode([
                    'key'           => env('QUICKY_EKYC_API'),
                    'id_number'     => $document_id
                ])
            ]);

            $user                               =   $this->user();
            $user->aadhaar_card_details         =   json_encode($aadhar_kyc_result ?? []);
            $user->save();

            if (!$aadhar_kyc_result->status):
                $arr                                =   array('status' => false, 'message' => $aadhar_kyc_result->data->message ?? $aadhar_kyc_result->message ?? 'Some error occured.');
                return response()->json($arr);
            endif;
        endif;
        # End Send Otp  : Aadhar Card

        $user                               =   $this->user();

        # Profile
        $kyc_document_front      =   uploadFile('kyc_document_front', "kyc_document_front/$user->uid/");
        $kyc_document_back       =   uploadFile('kyc_document_back', "kyc_document_back/$user->uid/");
        # End Profile

        $user->document_type_id      = $document_type_id;
        $user->document_id           = $document_id;
        $user->kyc_document_front    = $kyc_document_front;
        $user->kyc_document_back     = $kyc_document_back;
        $user->save();


        $arr                                =   array('status' => true, 'message' => 'OTP sent on registered number', 'aadhar_card_kyc_request_id' => $aadhar_kyc_result->data->request_id ?? null, 'user' => new UserResource($user));

        return response()->json($arr);
    }
    # End Update KYC

    public function verify_aadhar_card_otp()
    {

        $request_id             = request()->aadhar_card_kyc_request_id;
        $otp                    = request()->otp;

        # Validation
        $validator                              =   Validator::make(request()->all(), [
            'aadhar_card_kyc_request_id'      => 'required',
            'otp'           =>  'required'
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;
        # End Validation

        $result = crul((object) [
            'method'    => 'POST',
            'url'       => 'https://api.quickekyc.com/api/v1/aadhaar-v2/submit-otp',
            'post_data' => json_encode([
                'key'            => env('QUICKY_EKYC_API'),
                'request_id'     => $request_id,
                'otp'            => $otp
            ])
        ]);

        $user                           =   $this->user();
        $user->aadhaar_card_details     =   json_encode($result);

        $message            =   "Some error occured";

        if (!$result->status):
            $arr                                =   array('status' => false, 'message' => $result->data->message);
            return response()->json($arr);
        endif;

        if ($result->status):
            $user->kyc_status               =   1;
            $message                        =   'Congratulations, kyc verified!';
        endif;

        $user->save();

        $arr                                =   array('status' => true, 'message' => $message, 'user' => new UserResource($this->user()));

        return response()->json($arr);
    }

    # Challenge
     public function challenge()
    {
        
        $arr                    =   [];
        $message                =   [];

        $game_type_id           =   request()->game_type_id;
        $id                     =   request()->id;
        $amount                 =   request()->amount;
        $user                   =   $this->user();

        $game_challenge         =   null;
        $data                   =   [];
        $challengeLocked        =   false;

        # Validation

        $validator                              =   Validator::make(request()->all(), [
            'type'                            => "required|in:create,accept,roomcode,cancel,loser,winner",
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;

        # ===========================================================================
        #   End Validation
        # ===========================================================================

        if ($id) :
            $game_challenge       = GameChallenge::with(['challenger', 'opponent'])->find($id);

            
            if (!$game_challenge) :
                return response()->json(['status' =>  false, 'message' => 'Game Challenge not found']);
            endif;

            $autoSettle = app(GameChallengeAutoSettleService::class);
            $autoSettle->settleIfDecided($game_challenge);
            $game_challenge->refresh();

            // if($game_challenge->status == 2 || $game_challenge->status == 7):
            //     return json_encode(['status' => false, 'message' => "Already cancelled"]);
            // endif;
            
            if($game_challenge->status == 4):
                return json_encode(['status' => false, 'message' => "Already completed"]);
            endif;

            if ((int) $game_challenge->challenger_status === 3
                && (int) $game_challenge->opponent_status === 3
                && in_array((int) $game_challenge->status, [3, 7], true)):
                return json_encode(['status' => false, 'message' => "Already cancelled"]);
            endif;
            
            if($game_challenge->status == 6):
                return json_encode(['status' => false, 'message' => "Already suspended"]);
            endif;

            if ($game_challenge->is_lock) :
                if (! $autoSettle->clearStaleLock($game_challenge)) {
                    return response()->json(['status' =>  false, 'message' => 'Game Status updating please wait.....']);
                }
                $game_challenge->refresh();
            endif;

            lock_game_challenge($game_challenge);
            $challengeLocked = true;

        endif;

        try{
        # Switch
        switch (request()->type):

                /*******************************************************************************
             *   Create
             ********************************************************************************/
            case 'create':

                # ===========================================================================
                # Validation
                # ===========================================================================

                if ($game_challenge) {
                    unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'Game Challenge already exists.']);
                }

                # Is Valid Roomcode
                // $roomcode_data              =   (object) [ 'method' => 'POST', 'url' => "https://staging.rajnikantmahato.me/addcode?code=05611374" ];
                // $roomcode_response          =    crul($roomcode_data);

                $minimum_game_play_amount = site_setting()->minimum_game_play_amount;
                $maximum_game_play_amount = site_setting()->maximum_game_play_amount;

                $validator = Validator::make(request()->all(), [
                    'amount' => "required|numeric|min:$minimum_game_play_amount|max:$maximum_game_play_amount",
                    'game_type_id' => 'required|numeric|exists:game_types,id',
                ]);

                if ($validator->fails()) {
                    unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
                }

                if ($this->userHasRunningGameChallenge($user)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'You have a game in progress. Complete it before creating a new one.',
                    ]);
                }

                # ===========================================================================
                # Insufficient Balance
                # ===========================================================================

                if ($amount > $user->total_wallet_amount) {
                    unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'Insufficient Balance']);
                }

                # ===========================================================================
                # Calculate Total Amount and Game Commission
                # ===========================================================================

                $commission = resolve_game_commission((float) $amount);
                $total_amount = $commission['total'];
                $game_commission = $commission['percent'];
                $game_commission_amount = $commission['amount'];
                $paid_amount = $commission['paid_amount'];
                # ===========================================================================
                # Prepare Game Challenge Data
                # ===========================================================================

                $game_id                    =   generate_uid();

                $data                       =   [
                    'uid'                           => $game_id,
                    'game_type_id'                  => request()->game_type_id,
                    'challenger_amount'             => $amount,
                    'amount'                        => $amount,
                    'paid_amount'                   => $paid_amount,
                    'game_commission'               => $game_commission,
                    'game_commission_amount'        => $game_commission_amount,
                    'refer_commission_amount'       => 0,
                    'challenger_id'                 => $this->user()->id,
                    'status'                        => 0,
                ];

                # ===========================================================================
                #   End Game Challenge Data
                # ===========================================================================

                $message    =   'Game Challenge created successfully.';

                # ===========================================================================
                #   Notification
                # ===========================================================================
                # Notification

                $notification_title = 'Game Challenge created';
                $notification_body = 'Game Challenge created successfully Ref:' . $game_id;
                $notification_type = 'create';

                safe_notify(
                    null,
                    $notification_title,
                    $notification_body,
                    $notification_type,
                    $this->user()->id
                );
                # Notification
                # ===========================================================================
                #   End Notification
                # ===========================================================================

                break;

                /*******************************************************************************
                 *   Accept
                 ********************************************************************************/
            case 'accept':
                # ===========================================================================
                # Validation
                # ===========================================================================

                $validator = Validator::make(request()->all(), [
                    'id' => 'required|numeric|exists:game_challenges',
                ]);

                if ($validator->fails()) {
                     unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
                }

                if ($this->userHasRunningGameChallenge($user)) {
                    unlock_game_challenge($game_challenge);

                    return response()->json([
                        'status' => false,
                        'message' => 'You have a game in progress. Complete it before joining another one.',
                    ]);
                }

                # King (Daddy King) synced table: the join must be confirmed by
                # the King server FIRST so two platforms can never take the same
                # table. Wallet is debited only after confirmation.
                if ($game_challenge->isKingLinked()) {
                    $kingResponse = app(KingChallengeGateway::class)->acceptViaKing($game_challenge, $user);

                    if ($kingResponse !== null) {
                        return response()->json($kingResponse);
                    }
                    // null = King daemon offline + purely local table: continue
                    // with the normal local accept below.
                }

                if (GameChallenge::runningForUser($game_challenge->challenger_id)->exists()) {
                    unlock_game_challenge($game_challenge);

                    return response()->json([
                        'status' => false,
                        'message' => 'Challenge creator is already in an active game.',
                    ]);
                }

                # ===========================================================================
                # Game Challenge Validations
                # ===========================================================================

                if ($game_challenge->status) {
                    unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'Game Status updating please wait.....']);
                }

                if ($game_challenge->opponent_id) {
                     unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'Game Challenge already accepted.']);
                }

                if ($user->id == $game_challenge->challenger_id) {
                     unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'You are not allowed to accept. Challenge created by you.']);
                }

                $opponent_game_fee = $game_challenge->entryStakeAmount();
                $waitingDismiss = app(GameChallengeWaitingDismissService::class);
                $insufficientBalance = false;
                $acceptRejected = null;

                if ($opponent_game_fee <= 0) {
                    unlock_game_challenge($game_challenge);

                    return response()->json(['status' => false, 'message' => 'Invalid table amount. Please try another one.']);
                }

                # Lock the challenge row, confirm it is still waiting, check balance,
                # then dismiss other tables + debit + attach opponent in ONE transaction.
                # Previously dismiss ran before the balance check (auto-cancelling other
                # tables on a failed accept) and opponent_id was saved after debit committed
                # (cancel could refund only the creator and leave the joiner unpaid).
                try {
                DB::transaction(function () use (
                    $waitingDismiss,
                    &$game_challenge,
                    $user,
                    $opponent_game_fee,
                    &$insufficientBalance,
                    &$acceptRejected
                ) {
                    $lockedChallenge = GameChallenge::query()->lockForUpdate()->find($game_challenge->id);
                    if (! $lockedChallenge) {
                        $acceptRejected = 'Game Challenge not found';

                        return;
                    }

                    if (
                        (int) $lockedChallenge->status !== 0
                        || (int) $lockedChallenge->opponent_id > 0
                    ) {
                        if (
                            in_array((int) $lockedChallenge->status, [2, 3, 6, 7], true)
                            || (int) $lockedChallenge->challenger_status === 3
                        ) {
                            $acceptRejected = 'This game was cancelled. Please try another one.';
                        } else {
                            $acceptRejected = 'Game Challenge already accepted.';
                        }

                        return;
                    }

                    $lockedUser = User::query()->lockForUpdate()->find($user->id);
                    if (! $lockedUser) {
                        $insufficientBalance = true;

                        return;
                    }

                    $available = (float) $lockedUser->game_wallet_amount + (float) $lockedUser->win_wallet_amount;
                    if ($opponent_game_fee > $available) {
                        $insufficientBalance = true;

                        return;
                    }

                    $waitingDismiss->dismissWaitingGamesForChallenger($lockedChallenge->challenger_id, $lockedChallenge->id);
                    $waitingDismiss->dismissWaitingGamesForChallenger($user->id);

                    $walletService = app(WalletService::class);
                    $debited = $walletService->debitEntryStake((int) $lockedUser->id, $opponent_game_fee, [
                        'game_challenge_id' => $lockedChallenge->id,
                        'remark' => "Challenge accepted. Ref: $lockedChallenge->uid",
                    ]);

                    if (! $debited) {
                        throw new \RuntimeException('INSUFFICIENT_BALANCE_ON_ACCEPT');
                    }

                    $lockedChallenge->amount = $opponent_game_fee * 2;
                    $lockedChallenge->opponent_id = $user->id;
                    $lockedChallenge->opponent_amount = $opponent_game_fee;
                    $lockedChallenge->status = 1;
                    $lockedChallenge->is_lock = 0;
                    $lockedChallenge->save();

                    $balances = $walletService->balances((int) $lockedUser->id);
                    if ($balances) {
                        $user->game_wallet_amount = $balances['game'];
                        $user->win_wallet_amount = $balances['win'];
                    }
                    $game_challenge = $lockedChallenge;
                });
                } catch (\RuntimeException $acceptException) {
                    if ($acceptException->getMessage() === 'INSUFFICIENT_BALANCE_ON_ACCEPT') {
                        $insufficientBalance = true;
                    } else {
                        throw $acceptException;
                    }
                }

                if ($insufficientBalance) {
                    unlock_game_challenge($game_challenge);

                    return response()->json(['status' => false, 'message' => 'Insufficient Balance']);
                }

                if ($acceptRejected) {
                    unlock_game_challenge($game_challenge);

                    return response()->json(['status' => false, 'message' => $acceptRejected]);
                }

                # ===========================================================================
                # Prepare Data for Game Challenge Acceptance
                # ===========================================================================

                $data = [
                    'amount' => $opponent_game_fee * 2,
                    'opponent_id' => $user->id,
                    'opponent_amount' => $opponent_game_fee,
                    'status' => 1,
                ];

                # ===========================================================================
                # Notification Handling
                # ===========================================================================

                $notification_title = 'Challenge accepted';
                $notification_body = 'Game Challenge accepted: ' . $game_challenge->uid;
                $notification_type = 'accept';

                safe_notify(
                    optional($game_challenge->challenger)->fcm_device_token,
                    $notification_title,
                    $notification_body,
                    $notification_type,
                    $this->user()->id,
                    ['game_challenge_id' => $game_challenge->id]
                );

                $message    =   'Game Challenge accepted successfully.';

                break;

                /*******************************************************************************
                 *   Cancel
                 ********************************************************************************/
            case 'cancel':
                # ===========================================================================
                # Validation
                # ===========================================================================
                $validator = Validator::make(request()->all(), [
                    'id' => 'required|numeric|exists:game_challenges',
                ]);

                if ($validator->fails()) {
                    unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
                }

                # ===========================================================================
                # Permission Check
                # ===========================================================================
                if ($user->id != $game_challenge->challenger_id && $user->id != $game_challenge->opponent_id) {
                     unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'You are not allowed to cancel game']);
                }

                # User Validation
                    if($user->id == $game_challenge->challenger_id):
                        if($game_challenge->challenger_status == 1):
                            $arr                                =   array('status' => false, 'message' => "You are already winner. You can't update the result.");
                            unlock_game_challenge($game_challenge);
                            return response()->json($arr);
                        endif;

                        if($game_challenge->opponent_status == 1 && $game_challenge->challenger_status != 0):
                            $arr                                =   array('status' => false, 'message' => "Result already updated.");
                            unlock_game_challenge($game_challenge);
                            return response()->json($arr);
                        endif;

                    endif;

                    if($user->id == $game_challenge->opponent_id):
                        if($game_challenge->opponent_status == 1):
                            $arr                                =   array('status' => false, 'message' => "You are already winner. You can't update the result.");
                           unlock_game_challenge($game_challenge);
                            return response()->json($arr);
                        endif;

                        if($game_challenge->challenger_status == 1 && $game_challenge->opponent_status != 0):
                            $arr                                =   array('status' => false, 'message' => "Result already updated.");
                            unlock_game_challenge($game_challenge);
                            return response()->json($arr);
                        endif;
                    endif;
                # End

                # ===========================================================================
                # Prepare Notification and Wallet Update Data
                # ===========================================================================
                $data = [];
                $isChallenger = $user->id == $game_challenge->challenger_id;
                $isOpponent = $user->id == $game_challenge->opponent_id;
                $status = 3;  # Cancelled status
                $recipient = null;
                $proof_image = null;
                $cancelAlreadyDone = false;

                if ($isChallenger) {
                    $proof_image = uploadFile('proof_image', "proof/cancel/$game_challenge->uid/");
                    $data = [
                        'challenger_remark' => request()->remark,
                        'challenger_screenshot' => $proof_image,
                    ];
                    $recipient = $game_challenge->opponent;
                } elseif ($isOpponent) {
                    $proof_image = uploadFile('proof_image', "proof/cancel/$game_challenge->uid/");
                    $data = [
                        'opponent_remark' => request()->remark,
                        'opponent_screenshot' => $proof_image,
                    ];
                    $recipient = $game_challenge->challenger;
                }

                # ===========================================================================
                # Handle Game Challenge Data
                # ===========================================================================
                if ($recipient) {
                    safe_notify(
                        $recipient->fcm_device_token ?? null,
                        'Challenge cancel',
                        'Game Challenge cancel ' . $game_challenge->uid,
                        'cancel',
                        $user->id,
                        ['game_challenge_id' => $game_challenge->id]
                    );
                }

                $refundService = app(GameChallengeStakeRefundService::class);

                DB::transaction(function () use (
                    &$game_challenge,
                    &$data,
                    &$cancelAlreadyDone,
                    $isChallenger,
                    $isOpponent,
                    $refundService,
                    $user
                ) {
                    $locked = GameChallenge::query()->lockForUpdate()->find($game_challenge->id);
                    if (! $locked) {
                        return;
                    }

                    $alreadyThisSide = ($isChallenger && (int) $locked->challenger_status === 3)
                        || ($isOpponent && (int) $locked->opponent_status === 3);

                    $hasOpponent = (int) $locked->opponent_id > 0;
                    $hasRoomcode = ! empty($locked->roomcode);
                    $matchStarted = $hasOpponent && $hasRoomcode;
                    $otherSideCancelled = $isChallenger
                        ? (int) $locked->opponent_status === 3
                        : (int) $locked->challenger_status === 3;
                    $otherSideClaimedWin = $isChallenger
                        ? (int) $locked->opponent_status === 1
                        : (int) $locked->challenger_status === 1;

                    // Waiting tables can close immediately. A started match
                    // (roomcode + opponent) must hold both stakes until the
                    // other player also cancels, or admin decides a dispute.
                    $fullyClosed = ! $matchStarted
                        || in_array((int) $locked->status, [3, 7], true)
                        || ((int) $locked->challenger_status === 3 && (int) $locked->opponent_status === 3);

                    if ($isChallenger && ! empty($data['challenger_remark'])) {
                        $locked->challenger_remark = $data['challenger_remark'];
                    }
                    if ($isChallenger && ! empty($data['challenger_screenshot'])) {
                        $locked->challenger_screenshot = $data['challenger_screenshot'];
                    }
                    if ($isOpponent && ! empty($data['opponent_remark'])) {
                        $locked->opponent_remark = $data['opponent_remark'];
                    }
                    if ($isOpponent && ! empty($data['opponent_screenshot'])) {
                        $locked->opponent_screenshot = $data['opponent_screenshot'];
                    }

                    if ($alreadyThisSide) {
                        if ((int) $locked->challenger_status === 3
                            && (int) $locked->opponent_status === 3
                            && ! in_array((int) $locked->status, [3, 7], true)) {
                            $locked->status = 7;
                            $fullyClosed = true;
                        } else {
                            $cancelAlreadyDone = true;
                        }
                    } elseif (! $matchStarted || $otherSideCancelled) {
                        $locked->status = 7;
                        $locked->challenger_status = 3;
                        $locked->opponent_status = 3;
                        $fullyClosed = true;
                    } else {
                        if ($isChallenger) {
                            $locked->challenger_status = 3;
                        }
                        if ($isOpponent) {
                            $locked->opponent_status = 3;
                        }
                        $fullyClosed = (int) $locked->challenger_status === 3
                            && (int) $locked->opponent_status === 3;
                        if ($fullyClosed) {
                            $locked->status = 7;
                        } elseif ($otherSideClaimedWin) {
                            $locked->status = 5;
                        } else {
                            $locked->status = 2;
                        }
                    }

                    if ($fullyClosed) {
                        $locked->closed_at = now();
                    }

                    $locked->is_lock = 0;
                    $locked->save();

                    if ($fullyClosed && ! $cancelAlreadyDone) {
                        $refundService->refundAllStakes($locked);
                    } elseif (! $fullyClosed) {
                        Log::info('[challenge.cancel] stake held for admin/dispute', [
                            'game_challenge_id' => $locked->id,
                            'uid' => $locked->uid,
                            'user_id' => $user->id,
                            'challenger_status' => $locked->challenger_status,
                            'opponent_status' => $locked->opponent_status,
                            'status' => $locked->status,
                            'roomcode' => $locked->roomcode,
                        ]);
                    }

                    $data = array_merge($data, [
                        'status' => $locked->status,
                        'challenger_status' => $locked->challenger_status,
                        'opponent_status' => $locked->opponent_status,
                        'challenger_remark' => $locked->challenger_remark,
                        'challenger_screenshot' => $locked->challenger_screenshot,
                        'opponent_remark' => $locked->opponent_remark,
                        'opponent_screenshot' => $locked->opponent_screenshot,
                    ]);
                    $game_challenge = $locked;
                });

                if ($cancelAlreadyDone) {
                    unlock_game_challenge($game_challenge);

                    return response()->json(['status' => false, 'message' => 'Already game cancelled']);
                }

                $message    =   'Game Challenge cancel successfully.';

                break;

                /*******************************************************************************
                 *   Roomcode
                 ********************************************************************************/
            case 'roomcode':

                $roomcode   = request()->roomcode;
                
                ########################################################################################
                # Validation
                ########################################################################################

                $validator                              =   Validator::make(request()->all(), [
                    'id'         => 'required|numeric|exists:game_challenges',
                    'roomcode'   => 'required|max:8',
                ]);

                if ($validator->fails()) :
                    $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
                    unlock_game_challenge($game_challenge);
                    return response()->json($arr);
                endif;

                ########################################################################################
                # End Validation
                ########################################################################################

                # ===========================================================================
                #   Game Challenge not accepted yet.
                # ===========================================================================
                if (!$game_challenge->roomcode && !$game_challenge->opponent_id) :
                     unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'Game Challenge not accepted yet.']);
                endif;
                # ===========================================================================
                #   End Game Challenge not accepted yet.
                # ===========================================================================

                # ===========================================================================
                #   Room code only updated by challenger.
                # ===========================================================================
                if ($game_challenge->challenger_id != $user->id) :
                     unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'Room code only updated by challenger.']);
                endif;
                # ===========================================================================
                #   End Room code only updated by challenger.
                # ===========================================================================

                # ===========================================================================
                #   Roomcode added
                # ===========================================================================
                if ($game_challenge->roomcode) :
                     unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'Roomcode already added.']);
                endif;

                # LK Game API: validate room code and capture Mongo game_id for later /game-status calls
                $lk = app(LkGameApiService::class);
                $check = null;
                $ludoKingGameIdForDb = $roomcode;
                if ($lk->apiKey() !== '' && $lk->baseUrl() !== '') {
                    $check = $lk->checkRoom($roomcode);
                    if (! $check || ! $lk->isCheckRoomValid($check)) {
                        unlock_game_challenge($game_challenge);
                        $msg = is_object($check) && isset($check->msg) ? (string) $check->msg : 'Invalid room code';

                        return response()->json(['status' => false, 'message' => $msg]);
                    }
                    $rtype = strtolower((string) ($check->type ?? (is_object($check->data ?? null) ? ($check->data->type ?? '') : '')));
                    if ($rtype !== '' && $rtype !== 'classic') {
                        unlock_game_challenge($game_challenge);

                        return response()->json(['status' => false, 'message' => 'Only classic room type is allowed']);
                    }

                    $gid = $lk->extractMongoGameId($check) ?? '';
                    if ($gid === '') {
                        $gid = isset($check->game_id) ? trim((string) $check->game_id) : '';
                    }
                    if ($gid === '' || ! preg_match('/^[a-f\d]{24}$/i', $gid)) {
                        unlock_game_challenge($game_challenge);

                        return response()->json([
                            'status' => false,
                            'message' => 'Room accepted but game id was not returned; please try again in a moment.',
                        ]);
                    }
                    $ludoKingGameIdForDb = strtolower($gid);
                }

                # ===========================================================================
                #   Notification
                # ===========================================================================
                # Notification
                $notification_title        =   'Roomcode added';
                $notification_body        =   'Game Challenge roomcode updated Ref: ' . $game_challenge->uid;
                $notification_type        =   'roomcode';

                safe_notify(
                    optional($game_challenge->opponent)->fcm_device_token,
                    $notification_title,
                    $notification_body,
                    $notification_type,
                    $game_challenge->opponent_id ? (string) $game_challenge->opponent_id : null,
                    ['game_challenge_id' => $game_challenge->id]
                );
                # Notification

                # ===========================================================================
                #   Game Challenge Data
                # ===========================================================================

                $is_game_cancelled          =   (( $game_challenge->challenger_status == 3 ) || ( $game_challenge->opponent_status == 3 ) ) ? 1 : 0;

                $data       =   [
                                    'ludo_king_game_id'          => $ludoKingGameIdForDb,
                                    'roomcode_datetime'          => date('Y-m-d H:i:m'),
                                    'roomcode'                   => $roomcode,
                                    'status'                     => ( $is_game_cancelled ) ? 3 : 1,
                                ];
                # ===========================================================================
                #   End Game Challenge Data
                # ===========================================================================

                $message    =   ( $is_game_cancelled ) ? 'Game cancelled by opponent.' : 'Room code added successfully.';

               
                break;

                /*******************************************************************************
                 *   Winner
                 ********************************************************************************/
            case 'winner':

                # ===========================================================================
                #   Roomcode not available
                # ===========================================================================
                if (!$game_challenge->roomcode) :
                     unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'Roomcode not available']);
                endif;

                # Only true cross-platform games (a King ghost holds one seat) are
                # settled via the King network (ResultUpdateRequest). A local table
                # that merely carries a king_table_id must still use the LK resolver.
                if (! $game_challenge->isCrossPlatformKingGame()) {
                    $lkSubmit = $this->tryLkOfficialResultSettlement($game_challenge, $user);
                    if ($lkSubmit !== null) {
                        unlock_game_challenge($game_challenge);

                        return $lkSubmit;
                    }
                }

                # ===========================================================================
                #   Validation
                # ===========================================================================

                # User Validation
                if($user->id == $game_challenge->challenger_id):
                    if($game_challenge->challenger_status == 1):
                        $arr                                =   array('status' => false, 'message' => "You are already winner. You can't update the result.");
                         unlock_game_challenge($game_challenge);
                        return response()->json($arr);
                    endif;

                    if($game_challenge->opponent_status == 1 && $game_challenge->challenger_status != 0):
                        $arr                                =   array('status' => false, 'message' => "Result already updated.");
                         unlock_game_challenge($game_challenge);
                        return response()->json($arr);
                    endif;

                endif;

                if($user->id == $game_challenge->opponent_id):
                    if($game_challenge->opponent_status == 1):
                        $arr                                =   array('status' => false, 'message' => "You are already winner. You can't update the result.");
                       unlock_game_challenge($game_challenge);
                        return response()->json($arr);
                    endif;

                    if($game_challenge->challenger_status == 1 && $game_challenge->opponent_status != 0):
                        $arr                                =   array('status' => false, 'message' => "Result already updated.");
                        unlock_game_challenge($game_challenge);
                        return response()->json($arr);
                    endif;
                endif;
            # End

                $validator                              =   Validator::make(request()->all(), [
                                                                'id'              => 'required|numeric|exists:game_challenges',
                                                                'proof_image'     => 'required',
                                                            ]);

                if ($validator->fails()) :
                    $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
                     unlock_game_challenge($game_challenge);
                    return response()->json($arr);
                endif;

                # ===========================================================================
                #   Proof Image
                # ===========================================================================

                $proof_image                        =   uploadFile('proof_image', "proof/winner/$game_challenge->uid/");

                # ===========================================================================
                #   Game Status Condition
                # ===========================================================================


                if ($user->id == $game_challenge->challenger_id):

                    $status = 0;
                    # Uncomplete
                    if ($game_challenge->opponent_status == 0):
                        $status = 8;
                    endif;
                    # End Uncomplete

                    # Complete
                    if ($game_challenge->opponent_status == 2):
                        $status                         =   4;
                        app(GameChallengeWinnerPayoutService::class)
                            ->creditWinnerIfMissing($game_challenge, $user);
                        $refer_commission               =   game_commission_slot()->refer_commission;

                        $refer_commission_amount        =   ($game_challenge->challenger_amount * $refer_commission) / 100;

                        # Commission Histroy

                        if ($game_challenge->game_commission):
                            CommissionHistory::create([
                                'game_challenge_id'             =>  $game_challenge->id,
                                'total_amount'                  =>  $game_challenge->amount,
                                'game_commission'               =>  $game_challenge->game_commission,
                                'game_commission_amount'        =>  $game_challenge->game_commission_amount,
                                'refer_commission'              =>  $refer_commission,
                                'refer_commission_amount'       =>  $refer_commission_amount,
                                'remark'                        =>  "Game Ref:$game_challenge->uid",
                                'status'                        =>  1,
                            ]);

                            # Atomic: $user is the request-cached model and was
                            # loaded before the winner payout, so assigning a
                            # total here used to overwrite that payout.
                            app(GameChallengeWinnerPayoutService::class)
                                ->creditReferCommission(
                                    (int) $user->id,
                                    (int) $game_challenge->id,
                                    (float) $refer_commission_amount,
                                    "Refer commission Ref: $game_challenge->uid"
                                );
                        endif;
                    # End Commission Histroy

                    endif;
                    # End Complete

                    # Disupute
                    if ($game_challenge->opponent_status == 1 || $game_challenge->opponent_status == 3):
                        $status = 5;
                    endif;
                    # End Disupute


                    # ===========================================================================
                    #   Game Challenge Data
                    # ===========================================================================

                    $data       =   [
                        'challenger_status'                 =>  1,
                        'challenger_screenshot'             =>  $proof_image,
                        'challenger_result_date'            =>  date('Y-m-d H:i:m'),
                        'status'                            =>  $status,
                    ];


                    # ===========================================================================
                    #   End Game Challenge Data
                    # ===========================================================================


                    # ===========================================================================
                    #   Notification
                    # ===========================================================================
                    # Notification
                    safe_notify(
                        optional($game_challenge->opponent)->fcm_device_token,
                        "Result updated $game_challenge->uid",
                        'Game Challenge result updated by challenger ',
                        'winner',
                        $this->user()->id,
                        ['game_challenge_id' => $game_challenge->id]
                    );
                # Notification

                # ===========================================================================
                #   End Notification
                # ===========================================================================


                elseif ($user->id == $game_challenge->opponent_id):

                    $status             =   0;

                    # Uncomplete

                    if ($game_challenge->challenger_status == 0):
                        $status = 8;
                    endif;
                    # End Uncomplete

                    # Complete
                    if ($game_challenge->challenger_status == 2):
                        $status                         =   4;
                        app(GameChallengeWinnerPayoutService::class)
                            ->creditWinnerIfMissing($game_challenge, $user);
                        $refer_commission               =   game_commission_slot()->refer_commission;
                        $refer_commission_amount        =   ($game_challenge->challenger_amount * $refer_commission) / 100;

                        # Commission Histroy
                        if ($game_challenge->game_commission):
                            CommissionHistory::create([
                                'game_challenge_id'             =>  $game_challenge->id,
                                'total_amount'                  =>  $game_challenge->amount,
                                'game_commission'               =>  $game_challenge->game_commission,
                                'game_commission_amount'        =>  $game_challenge->game_commission_amount,
                                'refer_commission'              =>  $refer_commission,
                                'refer_commission_amount'       =>  $refer_commission_amount,
                                'remark'                        =>  "Game Ref:$game_challenge->uid",
                                'status'                        =>  1,
                            ]);

                            # Atomic: see the challenger branch above.
                            app(GameChallengeWinnerPayoutService::class)
                                ->creditReferCommission(
                                    (int) $user->id,
                                    (int) $game_challenge->id,
                                    (float) $refer_commission_amount,
                                    "Refer commission Ref: $game_challenge->uid"
                                );
                        endif;
                    # End Commission Histroy
                    endif;
                    # End Complete

                    # Disupute
                    if ($game_challenge->challenger_status == 1 || $game_challenge->challenger_status == 3):
                        $status = 5;
                    endif;
                    # End Disupute

                    # ===========================================================================
                    #   Game Challenge Data
                    # ===========================================================================
                    $data       =   [
                        'opponent_status'                   => 1,
                        'opponent_screenshot'               =>  $proof_image,
                        'opponent_date'                     =>  date('Y-m-d H:i:m'),
                        'status'                            => $status,
                    ];
                    # ===========================================================================
                    #   End Game Challenge Data
                    # ===========================================================================

                    # ===========================================================================
                    #   Notification
                    # ===========================================================================
                    # Notification
                    safe_notify(
                        optional($game_challenge->challenger)->fcm_device_token,
                        "Result updated $game_challenge->uid",
                        'Game Challenge result updated by opponent',
                        'result_updated',
                        $this->user()->id,
                        ['game_challenge_id' => $game_challenge->id]
                    );
                # Notification

                # ===========================================================================
                #   End Notification
                # ===========================================================================
                endif;

                # ===========================================================================
                #   End Game Status Condition
                # ===========================================================================


                # End Data
                $message    =   'Winner status successfully updated.';

                break;

                /*******************************************************************************
                 *   Loser
                 ********************************************************************************/
            case 'loser':
                # ===========================================================================
                #   Roomcode not available
                # ===========================================================================
                if (!$game_challenge->roomcode) :
                     unlock_game_challenge($game_challenge);
                    return response()->json(['status' => false, 'message' => 'Roomcode not available']);
                endif;

                # Only true cross-platform games are settled via the King network.
                if (! $game_challenge->isCrossPlatformKingGame()) {
                    $lkSubmitLoser = $this->tryLkOfficialResultSettlement($game_challenge, $user);
                    if ($lkSubmitLoser !== null) {
                        unlock_game_challenge($game_challenge);

                        return $lkSubmitLoser;
                    }
                }

                # ===========================================================================
                #   Validation
                # ===========================================================================

                $validator                              =   Validator::make(request()->all(), [
                                                                'id'             => 'required|numeric|exists:game_challenges',
                                                            ]);

                if ($validator->fails()) :
                    $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
                    unlock_game_challenge($game_challenge);
                    return response()->json($arr);
                endif;

                # User Validation
                    if($user->id == $game_challenge->challenger_id):
                        if($game_challenge->challenger_status == 1):
                            $arr                                =   array('status' => false, 'message' => "You are already winner. You can't update the result.");
                            unlock_game_challenge($game_challenge);
                            return response()->json($arr);
                        endif;

                        if($game_challenge->opponent_status == 1 && $game_challenge->challenger_status != 0):
                            $arr                                =   array('status' => false, 'message' => "Result already updated");
                          unlock_game_challenge($game_challenge);
                            return response()->json($arr);
                        endif;

                    endif;

                    if($user->id == $game_challenge->opponent_id):
                        if($game_challenge->opponent_status == 1):
                            $arr                                =   array('status' => false, 'message' => "You are already winner. You can't update the result.");
                           unlock_game_challenge($game_challenge);
                            return response()->json($arr);
                        endif;

                        if($game_challenge->challenger_status == 1 && $game_challenge->opponent_status != 0):
                            $arr                                =   array('status' => false, 'message' => "Result already updated.");
                           unlock_game_challenge($game_challenge);
                            return response()->json($arr);
                        endif;
                    endif;
                # End

                #
                if ($user->id != $game_challenge->challenger_id && $user->id != $game_challenge->opponent_id) :
                    $arr                                =   array('status' => false, 'message' => "You are not allowed to update result");
                     unlock_game_challenge($game_challenge);
                    return response()->json($arr);
                endif;
                #

                # ===========================================================================
                #   Proof Image
                # ===========================================================================
                $proof_image                            =   uploadFile('proof_image', "proof/loser/$game_challenge->uid/");

                # ===========================================================================
                #  Game Status Condition
                # ===========================================================================

                if ($user->id == $game_challenge->challenger_id):

                    $data       =   [
                                        'opponent_status'                   => 1,
                                        'challenger_status'                 => 2,
                                        'challenger_screenshot'             =>  $proof_image,
                                        'challenger_result_date'            =>  date('Y-m-d H:i:m'),
                                        'status'                            => 4,
                                    ];

                    # Refer Commission

                    $refer_commission_amount        =   0;
                    $refer_commission               =   game_commission_slot()->refer_commission;

                    $opponent_refer_by              =   $game_challenge->opponent->refer_by;

                    
                    if ($opponent_refer_by && $game_challenge->game_commission_amount):
                        $refer_user                         =   User::find($opponent_refer_by);

                        
                        if ($refer_user->refer_income == 1):
                            
                            if ($refer_user->commission):
                                $refer_commission = $refer_user->commission;
                            endif;
                            
                            $refer_commission_amount            =   ($game_challenge->challenger_amount * $refer_commission) / 100;
                            # Balance and ledger row are written atomically below.
                         

                            app(GameChallengeWinnerPayoutService::class)
                                ->creditReferCommission(
                                    (int) $refer_user->id,
                                    (int) $game_challenge->id,
                                    (float) $refer_commission_amount,
                                    'Refer wallet fund'
                                );
                        endif;
                    endif;
                    # End Refer Commission

                    # Commission Histroy
                    if ($game_challenge->game_commission):
                        CommissionHistory::create([
                            'refer_by'                      =>  $refer_user->id ?? 0,
                            'user_id'                       =>  $game_challenge->opponent->id ?? 0,

                            'game_challenge_id'             =>  $game_challenge->id,
                            'total_amount'                  =>  $game_challenge->amount,
                            'game_commission'               =>  $game_challenge->game_commission,
                            'game_commission_amount'        =>  $game_challenge->game_commission_amount,
                            'refer_commission'              =>  $refer_commission,
                            'refer_commission_amount'       =>  $refer_commission_amount,
                            'remark'                        =>  "Game Ref:$game_challenge->uid",
                            'status'                        =>  1,
                        ]);
                    endif;
                    # End Commission Histroy

                    # ===========================================================================
                    #   Wallet
                    # ===========================================================================
                    $opponent_user              =   User::find($game_challenge->opponent_id);

                    # King ghost opponents are paid on their OWN platform - never here.
                    if ($opponent_user && ! is_king_ghost_user($opponent_user)):
                        app(GameChallengeWinnerPayoutService::class)
                            ->creditWinnerIfMissing($game_challenge, $opponent_user);
                    endif;
                    # End King ghost opponent guard

                elseif ($user->id == $game_challenge->opponent_id):
                    $status = 4;

                    # Refer Commission

                    $refer_commission_amount        =   0;
                    $refer_commission               =   game_commission_slot()->refer_commission;

                    
                    $challenger_refer_by              =   $game_challenge->challenger->refer_by;
                    
                    if ($challenger_refer_by && $game_challenge->game_commission_amount):
                        $refer_user                         =   User::find($challenger_refer_by);
                        
                        if ($refer_user->commission):
                            $refer_commission = $refer_user->commission;
                        endif;

                        
                        if ($refer_user->refer_income == 1):
                            $refer_commission_amount            =   ($game_challenge->challenger_amount * $refer_commission) / 100;
                            # Balance and ledger row are written atomically below.
                            

                           
                            app(GameChallengeWinnerPayoutService::class)
                                ->creditReferCommission(
                                    (int) $refer_user->id,
                                    (int) $game_challenge->id,
                                    (float) $refer_commission_amount,
                                    'Refer wallet fund'
                                );

                        endif;
                    endif;
                    # End Refer Commission

                    # Commission Histroy
                    if ($game_challenge->game_commission):
                        CommissionHistory::create([
                                                        'refer_by'                      =>  $refer_user->id ?? 0,
                                                        'user_id'                       =>  $game_challenge->challenger->id ?? 0,

                                                        'game_challenge_id'             =>  $game_challenge->id,
                                                        'total_amount'                  =>  $game_challenge->amount,
                                                        'game_commission'               =>  $game_challenge->game_commission,
                                                        'game_commission_amount'        =>  $game_challenge->game_commission_amount,
                                                        'refer_commission'              =>  $refer_commission,
                                                        'refer_commission_amount'       => ($refer_commission_amount ?? 0),
                                                        'remark'                        =>  "Game Ref:$game_challenge->uid",
                                                        'status'                        =>  1,
                                                    ]);
                    endif;
                    # End Commission Histroy

                    # ===========================================================================
                    #   Game Challenge Data
                    # ===========================================================================
                    $data       =   [
                                        'challenger_status'                 => 1,
                                        'opponent_status'                   => 2,
                                        'opponent_screenshot'               =>  $proof_image,
                                        'opponent_date'                     =>  date('Y-m-d H:i:m'),
                                        'status'                            => $status,
                                    ];
                    # ===========================================================================
                    #   End Game Challenge Data
                    # ===========================================================================

                    # ===========================================================================
                    #   Wallet
                    # ===========================================================================
                    $challenger_user            =   User::find($game_challenge->challenger_id);

                    # King ghost challengers are paid on their OWN platform - never here.
                    if ($challenger_user && ! is_king_ghost_user($challenger_user)):
                        app(GameChallengeWinnerPayoutService::class)
                            ->creditWinnerIfMissing($game_challenge, $challenger_user);
                    endif;
                    # End King ghost challenger guard

                # ===========================================================================
                #   End Notification
                # ===========================================================================
                endif;

                # ===========================================================================
                #   End Game Status Condition
                # ===========================================================================

                $message    =   'Loser status successfully updated.';

                break;

        endswitch;
        # End Switch

        # ===========================================================================
        #   Wallet
        # ===========================================================================
         if(($game_challenge->challenger_id ?? 0 ) == $user->id):
            $data['challenger_result_date']   =   date('Y-m-d H:i:m');
        endif;

        if(( $game_challenge->opponent_id ?? 0 ) == $user->id):
            $data['opponent_result_date']   =   date('Y-m-d H:i:m');
        endif;

        $data['is_lock']   =   0;

        $debitCreateStake = function () use ($user, &$game_challenge, $amount): void {
            User::query()->lockForUpdate()->find($user->id);

            $walletService = app(WalletService::class);
            $debited = $walletService->debitEntryStake((int) $user->id, (float) $amount, [
                'game_challenge_id' => $game_challenge->id,
                'remark' => "Challenge created. Ref: $game_challenge->uid",
            ]);

            if (! $debited) {
                throw new \RuntimeException('INSUFFICIENT_BALANCE_AFTER_CREATE');
            }

            $balances = $walletService->balances((int) $user->id);
            if ($balances) {
                $user->game_wallet_amount = $balances['game'];
                $user->win_wallet_amount = $balances['win'];
            }
        };

        try {
            if (request()->type === 'create') {
                $result = DB::transaction(function () use ($id, $data, &$game_challenge, $debitCreateStake) {
                    $created = GameChallenge::updateOrCreate(['id' => $id], $data);
                    $game_challenge = GameChallenge::with(['challenger', 'opponent', 'game_type'])->find($created->id);
                    $debitCreateStake();

                    return $created;
                });
            } else {
                $result = GameChallenge::updateOrCreate(['id' => $id], $data);
            }
        } catch (\RuntimeException $createException) {
            if ($createException->getMessage() === 'INSUFFICIENT_BALANCE_AFTER_CREATE') {
                return response()->json(['status' => false, 'message' => 'Insufficient Balance']);
            }
            throw $createException;
        }
        # ===========================================================================
        #   End Wallet
        # ===========================================================================

        if ($result) :
            $game_challenge = GameChallenge::with(['challenger', 'opponent', 'game_type'])->find($result->id);

            if (! $result->wasRecentlyCreated) :
                #
                if ($game_challenge->challenger_status != 0 && $game_challenge->opponent_status != 0) :
                    $game_challenge->closed_at          =   date('Y-m-d H:i:s');
                    $game_challenge->is_lock = 0;
                    $game_challenge->save();
                endif;
            #
            endif;

            if (request()->type === 'cancel') {
                $freshCancel = $game_challenge;
                $matchStarted = (int) $freshCancel->opponent_id > 0 && ! empty($freshCancel->roomcode);
                $fullyClosed = ! $matchStarted
                    || in_array((int) $freshCancel->status, [3, 7], true)
                    || ((int) $freshCancel->challenger_status === 3 && (int) $freshCancel->opponent_status === 3);
                if ($fullyClosed) {
                    app(GameChallengeStakeRefundService::class)->refundAllStakes($freshCancel);
                }
            }

            if (in_array(request()->type, ['cancel', 'winner', 'loser'], true)) {
                $game_challenge = app(GameChallengeAutoSettleService::class)
                    ->settleIfDecided($game_challenge);
            }

            // Always return wallet balances read fresh from DB after stake changes.
            $user = $this->freshUser();

            $arr                    =   [
                'status'    => true,
                'message'   => $message,
                'data'      => new GameChallengeResource($game_challenge),
                'user'      => new UserResource($user),
            ];
        # ===========================================================================
        #   End Response
        # ===========================================================================

        else:
            $arr                    =   ['status' => false, 'message' => $message];
        endif;

        if (count($wallet_data ?? [])) :
            Wallet::create($wallet_data);
        endif;

        # King (Daddy King) sync hook: only after a successful challenge update.
        if ($result && ($arr['status'] ?? false)) {
            $challengeId = (int) $game_challenge->id;
            $actionType = (string) request()->type;
            $actingUserId = (int) $user->id;

            dispatch(function () use ($challengeId, $actionType, $actingUserId): void {
                try {
                    $challenge = GameChallenge::find($challengeId);
                    $actingUser = User::find($actingUserId);
                    if (! $challenge || ! $actingUser) {
                        return;
                    }

                    app(KingChallengeGateway::class)->afterLocalChallengeAction($actionType, $challenge, $actingUser);
                } catch (\Throwable $kingHookError) {
                    Log::error('[King] challenge hook failed', ['error' => $kingHookError->getMessage()]);
                }
            })->afterResponse();
        }

        if ($challengeLocked && $game_challenge) {
            unlock_game_challenge($game_challenge);
        }
        return response()->json($arr);
        } catch (\Throwable $e) {
            Log::error('[challenge] unhandled exception', [
                'type' => request()->type,
                'game_challenge_id' => $game_challenge->id ?? $id ?? null,
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
            ]);
            if ($game_challenge) {
                unlock_game_challenge($game_challenge);
            }

            return response()->json([
                'status' => false,
                'message' => 'Unable to update game. Please try again or contact support.',
            ]);
        }
    }
    # End Challenge

    # Challenges
    public function game_table()
    {
        $arr                    =   [];

        $game_challenge         =   GameChallenge::select(
            'id',
            'uid',
            'game_type_id',
            'amount',
            'roomcode',
            'paid_amount',
            'challenger_amount',
            'challenger_id',
            'opponent_id',
            'challenger_status',
            'opponent_status',
            'status',
            'game_source',
            'king_table_id'
        );

        $userId = $this->user()->id;
        // Eager-load full related models (do NOT constrain columns — profile_url is an accessor).
        $relations = ['challenger', 'opponent', 'game_type'];

        # My Challenges (active only: user is challenger or opponent, not terminal)
        # Terminal statuses: 2 Cancel, 4 Complete, 6 Suspended, 7 Cancelled
        $my_challenges          =   $game_challenge->clone()
                  ->with($relations)
                  ->where(function ($q) use ($userId) {
                      $q->where('challenger_id', $userId)->orWhere('opponent_id', $userId);
                  })
                  ->whereNotIn('status', [2, 3, 4, 6, 7])
                  ->where(function ($q) {
                      $q->whereNull('challenger_status')->orWhere('challenger_status', '!=', 3);
                  })
                  ->where(function ($q) {
                      $q->whereNull('opponent_status')->orWhere('opponent_status', '!=', 3);
                  })
                  ->orderBy('created_at', 'desc')
                  ->get();

        $my_challenges_ids      =   data_get($my_challenges, '*.id');
        # End My Challenges

        # Game Challenges
        $game_challenges        =   $game_challenge->clone()
            ->with($relations)
            ->whereNotIn('id', $my_challenges_ids)
            ->liveChallenges($this->user()->id)
            ->orderByRaw("CASE WHEN status = 0 THEN 0 ELSE 1 END ASC") // Status 0 comes first
            ->orderBy('created_at', 'desc')
            ->get();

        // return $game_challenges;
        # End Game Challenges


        $arr =  [
            'status'                    => true,
            'message'                   => 'Successfully records fetched',
            'is_ulta_ludo_active'       => site_setting()->ulta_ludo_status,
            'minimum_deposit_amount'    => site_setting()->minimum_deposit_amount,
            'maximum_deposit_amount'    => site_setting()->maximum_deposit_amount,
            'minimum_game_play_amount'  => site_setting()->minimum_game_play_amount,
            'maximum_game_play_amount'  => site_setting()->maximum_game_play_amount,
            'rules'                     =>  site_setting()->rules,
            'support_video'             => site_setting()->youtube_help_video,
            'my_challenges'             => GameChallengeResource::collection($my_challenges),
            'data'                      => GameChallengeResource::collection($game_challenges),
            'user'                      => new UserResource($this->freshUser())
        ];
        return response()->json($arr);
    }
    # End Challenges

    # My Challenges
    public function my_challenges()
    {
        $arr                    =   [];

        $userId = $this->user()->id;
        $filter = request()->query('filter', 'active');
        if (! in_array($filter, ['active', 'history'], true)) {
            $filter = 'active';
        }

        $game_challenge         =   GameChallenge::select(
            'id',
            'uid',
            'game_type_id',
            'roomcode',
            'amount',
            'paid_amount',
            'challenger_amount',
            'challenger_id',
            'opponent_id',
            'challenger_status',
            'opponent_status',
            'status',
            'game_source',
            'king_table_id'
        )->where(function ($q) use ($userId) {
            $q->where('challenger_id', $userId)->orWhere('opponent_id', $userId);
        });

        if ($filter === 'history') {
            $game_challenge->where(function ($q) {
                         $q->whereIn('status', [2, 3, 4, 5, 6, 7])
                             ->orWhere('challenger_status', 3)
                             ->orWhere('opponent_status', 3);
                     });
        } else {
            $game_challenge
                           ->whereNotIn('status', [2, 3, 4, 5, 6, 7])
                           ->where(function ($q) {
                               $q->whereNull('challenger_status')->orWhere('challenger_status', '!=', 3);
                           })
                           ->where(function ($q) {
                               $q->whereNull('opponent_status')->orWhere('opponent_status', '!=', 3);
                           });
        }

        # My Challenges / Game history (filter=active | history)
        $my_challenges          =   $game_challenge->latest()->paginate(10);
        # End My Challenges


        $my_challenges_data  =    GameChallengeResource::collection($my_challenges)->response()->getData();
        if ($my_challenges->count()) :


            $arr =  [
                'status'            => true,
                'message'           => 'Successfully records fetched',
                'data'              => $my_challenges_data,
                'user'              => new UserResource($this->user())

            ];
        else :
            $arr =  [
                'status' => true,
                'message' => 'No records found',
                'data'     => $my_challenges_data
            ];
        endif;

        return response()->json($arr);
    }
    # End My Challenges

    # Transfer
    public function transfer()
    {
        #
        $minimum_deposit_amount             =   site_setting()->minimum_deposit_amount;
        $maximum_deposit_amount             =   site_setting()->maximum_deposit_amount;

        $minimum_withdrawal_limit             =   site_setting()->minimum_withdrawal_limit;
        $maximum_withdrawal_limit             =   site_setting()->maximum_withdrawal_limit;

        $without_kyc_withdrawal_limit      =   site_setting()->without_kyc_withdrawal_limit;

        $all_withdrawal_status             =   site_setting()->all_withdrawal_status;

        # Validation
        $validator                              =   Validator::make(request()->all(), [
            'transfer_type'                 => 'required|in:deposit,withdrawal',
            'amount'                        => "required|numeric",
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;
        # End Validation

        # Init
        $user                   =    auth('api')->user();
        $txn_id                 =    "TXN_" . str()->upper(str()->random(10));
        $transfer_type          =   request()->transfer_type;
        $amount                 =   request()->amount;
        $payment_info           =   request()->payment_info;

        $status                    = 0;
        if ($transfer_type == 'withdrawal') :

            if (!$all_withdrawal_status):
                $arr                                =   array('status' => false, 'message' => "Withdrawal is inactive.");
                return response()->json($arr);
            endif;

            if(!$user->withdrawal_status):
                return response()->json(['status' => false, 'message' => "Withdrawal option is currently inactive. Please try again later."]);
            endif;

            if ($amount > $user->win_wallet_amount):
                $arr                                =   array('status' => false, 'message' => "Insufficient Balance ( ₹ $user->win_wallet_amount ) in win wallet");
                return response()->json($arr);
            endif;

            if (($user->kyc_status ==  0) && $amount > $without_kyc_withdrawal_limit):
                $arr                                =   array('status' => false, 'message' => "Without kyc withdrawal limit amount is $without_kyc_withdrawal_limit");
                return response()->json($arr);
            endif;

            # Validation
            $validator                              =   Validator::make(request()->all(), [
                'amount'                                => "gte:$minimum_withdrawal_limit|lte:$maximum_withdrawal_limit",
            ]);

            if ($validator->fails()) :
                $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
                return response()->json($arr);
            endif;
            # End Validation

            #
            $total_balance                =  $user->win_wallet_amount - $amount;
            #

            # Payment Info (withdrawals: bank account only; UPI not accepted)
            $payment_payload        =   json_decode($payment_info ?? '');

            if (! is_object($payment_payload)) {
                return response()->json(['status' => false, 'message' => 'Withdrawal requires valid bank account details.']);
            }

            if (($payment_payload->type ?? '') === 'upi') {
                return response()->json(['status' => false, 'message' => 'Unable to add UPI now. Only bank account can be saved.']);
            }

            if (($payment_payload->type ?? '') !== 'bank_account') {
                return response()->json(['status' => false, 'message' => 'Withdrawal requires bank account details.']);
            }

            $account_name       = $payment_payload->account_name ?? '';
            $account_number     = $payment_payload->account_number ?? '';
            $ifsc_code          = $payment_payload->ifsc_code ?? '';

            if ($account_name === '' || $account_number === '' || $ifsc_code === '') {
                return response()->json(['status' => false, 'message' => 'Please provide account name, account number and IFSC code.']);
            }

            $payment_info = "Account Name : $account_name <br>
                                 Account Number :  $account_number <br>
                                   IFSC Code : $ifsc_code
                                ";

            /*
            // Previously (UPI disabled — rejected earlier in this block):
            // elseif (($payment_info->type ?? '') == 'upi'):
            //     $payment_info = $payment_info->upi_id;
            // endif;
            */

        # End Payment Info

        elseif ($transfer_type == 'deposit') :

            # Validation
            $validator                              =   Validator::make(request()->all(), [
                'amount'                        => "gte:$minimum_deposit_amount|lte:$maximum_deposit_amount",
            ]);

            if ($validator->fails()) :
                $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
                return response()->json($arr);
            endif;
            # End Validation

            $total_balance          = Transaction::whereTransferType('deposit')->whereUserId($user->id)->whereStatus(1)->sum('final_amount');
            $total_balance           =   $total_balance + $amount;
            $final_amount           =   $amount;
            $status                    = 1;
        endif;

        # End Init

        $transaction = Transaction::create([
            'txn_id'                     => $txn_id,
            'transfer_type'             => $transfer_type,
            'amount'                    => $amount,
            'total_balance'             => $total_balance,
            'payment_info'              => $payment_info,
            'user_id'                   => auth('api')->user()->id,
            'status'                    => $status
        ]);

        $user               =   User::find($user->id);


        if ($transfer_type == 'withdrawal') :

            # Atomic debit. Assigning a total that was calculated earlier in the
            # request would discard anything credited in the meantime.
            $debited = app(WalletService::class)->debit((int) $user->id, 'win', (float) $amount, [
                'transaction_id' => $transaction->id,
                'remark' => 'Withdrawal Fund',
                'status' => 0,
            ]);

            if (! $debited) :
                $transaction->delete();

                return response()->json([
                    'status'    => false,
                    'message'   => 'Insufficient balance in win wallet',
                ]);
            endif;

            $user->refresh();

            $message = "Withdrawal amount will be reflected within 10-15 mins";

            safe_notify(
                $user->fcm_device_token,
                'Withdrawal Request Submitted',
                'Your withdrawal request is received and is currently pending review.',
                'withdrawal',
                $user->id,
                ['user_id' => $user->id, 'transaction_id' => $transaction->id]
            );

            $this->notifyCashiersPendingWithdrawal($transaction, $user);
        endif;



        if ($transfer_type == 'deposit') :
            app(WalletService::class)->credit((int) $user->id, 'game', (float) $final_amount, [
                'remark' => 'Deposit Fund',
                'status' => 1,
            ]);

            $user->refresh();

            # ===========================================================================
            #   Notification
            # ===========================================================================
            # Notification
            safe_notify(
                $this->user()->fcm_device_token,
                'Deposit Successfully',
                'Amount deposit successfully.',
                'deposit',
                $this->user()->id,
                ['user_id' => $this->user()->id]
            );
            # Notification

            $message = "Deposit successfully";
        endif;

        return response()->json(['status' => true, 'message' => $message]);
    }
    # End Transfer

    public function deposit_request()
    {
        $deposit_type = site_setting()->payment_gateway;
        
        # Validation
        $validator =   Validator::make(request()->all(), [
            'amount'                        => "required|numeric",
            'deposit_screenshot'            => ( $deposit_type == 'manually' ) ? 'required' : ''
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;
        # End Validation
        $userId = auth('api')->user()->id;
        // $deposit_type = request()->deposit_type;
       
        $amount                 =   request()->amount;
        $total_balance          =   auth('api')->user()->game_wallet_amount + request()->amount;
        $txn_id                =    "TXN_" . str()->upper(str()->random(10));

        $deposit_screenshot            = uploadFile('deposit_screenshot', "proof/deposit-request/$txn_id/");

        $result = Transaction::create([
            'txn_id'                    => $txn_id,
            'transfer_type'             => 'deposit',
            'amount'                    => $amount,
            'total_balance'             => $total_balance,
            'user_id'                   => auth('api')->user()->id,
            'payment_info'              => $deposit_type,
            'deposit_screenshot'        => $deposit_screenshot,
            'status'                    => 0
        ]);

        $transactionId = $result->id;
         # Wallet Update
         Wallet::create([
            'user_id'               =>  auth()->user()->id,
            'type'                  =>  'credit',
            'wallet_type'           =>  'game',
            'remark'                =>  "Deposit Fund ( $deposit_type : $txn_id )",
            'amount'                =>  $amount,
            'total_balance'         =>  $total_balance,
            'transaction_id'        =>  $transactionId,
            'status'                =>  0
        ]);
        # End Wallet Update

        if($deposit_type == 'manually'):
            return response()->json(['status' => true, 'message' => $result ? "Deposit request sent successfully. Your amount will be reflected in your wallet within 5-10 mins" : "Some error occured"]);
        endif;
        
        if($deposit_type == 'cashfree'):
            return response()->json([
                                    'status' => true,
                                    'message' => $result ?
                                                        "redirect to url...."
                                                        : "Some error occured",
                                    'txn_id'       => $txn_id,
                                    'redirect_url' => url("cashfree/pay?user_id=$userId&amount=$amount&transactionId=$txn_id")
                                ]);
        endif;
        
        if($deposit_type == 'rozarpay'):
            return response()->json([
                                    'status' => true,
                                    'message' => $result ?
                                                        "redirect to url...."
                                                        : "Some error occured",
                                    'txn_id'       => $txn_id,
                                    'redirect_url' => url("rozarpay/pay?user_id=$userId&amount=$amount&transactionId=$txn_id")
                                ]);
        endif;

        if($deposit_type == 'upigateway'):
            $gateway = new \App\Http\Controllers\PaymentGateway\UpiGatewayController();
            $created = $gateway->createOrder($result);

            if (!$created['status']) {
                Log::warning('deposit_request upigateway failed', [
                    'txn_id' => $txn_id,
                    'message' => $created['message'] ?? null,
                ]);
                $result->status = 2;
                $result->save();
                \App\Models\GameChallenge\Wallet::whereTransactionId($result->id)
                    ->update(['status' => 2, 'remark' => 'UPI Gateway: '.$created['message']]);
                return response()->json(['status' => false, 'message' => $created['message']]);
            }

            $redirectUrl = $created['redirect_url'] ?? '';
            Log::info('deposit_request upigateway ok', [
                'txn_id' => $txn_id,
                'redirect_url_len' => strlen($redirectUrl),
                'redirect_url_host' => strlen($redirectUrl) ? (parse_url($redirectUrl, PHP_URL_HOST) ?: '') : '',
            ]);

            return response()->json([
                'status'                  => true,
                'message'                 => 'redirect to url....',
                'txn_id'                  => $txn_id,
                'gateway_order_id'        => $created['order_id'] ?? null,
                'redirect_url'            => $redirectUrl,
                'completion_url_hint'     => url('upigateway/return'),
            ]);
        endif;
    }

    /**
     * Re-verify a deposit transaction's status from the active gateway and
     * return the latest record. Called by the app after the user finishes
     * the in-app checkout, so the wallet refreshes immediately without
     * waiting for the webhook.
     */
    public function transaction_status()
    {
        $requestTxnId = request()->input('txn_id');
        $authUserId = null;

        try {
            $authUser = $this->user();
        } catch (\Throwable $e) {
            Log::warning('[API] transaction_status auth threw', [
                'txn_id_request' => $requestTxnId,
                'error'          => $e->getMessage(),
                'exception'      => $e::class,
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        if (!$authUser) {
            Log::notice('[API] transaction_status no authenticated user', [
                'txn_id_request' => $requestTxnId,
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $authUserId = $authUser->id;

        Log::info('[API] transaction_status begin', [
            'txn_id'  => $requestTxnId,
            'user_id' => $authUserId,
            'ip'      => request()->ip(),
        ]);

        try {
            $validator = Validator::make(request()->all(), [
                'txn_id' => 'required|string',
            ]);

            if ($validator->fails()):
                Log::notice('[API] transaction_status validation failed', [
                    'errors' => $validator->errors()->toArray(),
                ]);

                return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
            endif;

            $transaction = Transaction::whereTxnId(request()->txn_id)
                ->whereUserId($authUserId)
                ->first();

            if (!$transaction):
                Log::notice('[API] transaction_status not found', [
                    'txn_id'  => request()->txn_id,
                    'user_id' => $authUserId,
                ]);

                return response()->json(['status' => false, 'message' => 'Transaction not found']);
            endif;

            $pg = '';
            try {
                $pg = strtolower((string) site_setting()->payment_gateway);
            } catch (\Throwable $e) {
                Log::error('[API] transaction_status site_setting() failed', [
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }

            if ((int) $transaction->status === 0 && $this->isUpiGatewayDeposit($transaction, $pg)) {
                try {
                    Log::info('[UPI Gateway] mobile transaction_status → syncStatus', [
                        'client_txn_id' => $transaction->txn_id,
                        'user_id'       => $transaction->user_id,
                    ]);
                    (new \App\Http\Controllers\PaymentGateway\UpiGatewayController())
                        ->syncStatus($transaction->txn_id, 4);
                    $transaction->refresh();
                    Log::info('[UPI Gateway] mobile transaction_status ← after sync', [
                        'client_txn_id' => $transaction->txn_id,
                        'local_status'  => $transaction->status,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[UPI Gateway] mobile transaction_status sync crashed (continuing with JSON)', [
                        'client_txn_id' => $transaction->txn_id,
                        'error'         => $e->getMessage(),
                        'exception'     => $e::class,
                        'file'          => $e->getFile(),
                        'line'          => $e->getLine(),
                        'trace'         => substr($e->getTraceAsString(), 0, 8000),
                    ]);
                    try {
                        $transaction->refresh();
                    } catch (\Throwable $ignored) {
                    }
                }
            }

            $userResource = null;
            try {
                $fresh = $authUser ? $authUser->fresh() : null;
                if ($fresh) {
                    $userResource = new UserResource($fresh);
                }
            } catch (\Throwable $e) {
                Log::error('[API] transaction_status UserResource failed', [
                    'txn_id'    => $transaction->txn_id,
                    'user_id'   => $authUserId,
                    'error'     => $e->getMessage(),
                    'exception' => $e::class,
                    'trace'     => substr($e->getTraceAsString(), 0, 4000),
                ]);
                try {
                    $userResource = new UserResource($authUser);
                } catch (\Throwable $ignored) {
                    $userResource = null;
                }
            }

            Log::info('[API] transaction_status success', [
                'txn_id'        => $transaction->txn_id,
                'local_status'  => $transaction->status,
                'payment_gateway' => $pg,
            ]);

            $payload = [
                'status'  => true,
                'message' => 'Transaction status fetched',
                'data'    => [
                    'txn_id'        => $transaction->txn_id,
                    'amount'        => $transaction->amount,
                    'status'        => $transaction->status,
                    'status_label'  => $transaction->status_label,
                    'payment_info'  => $transaction->payment_info,
                ],
            ];

            if ($userResource !== null) {
                $payload['user'] = $userResource;
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('[API] transaction_status fatal', [
                'txn_id_request' => $requestTxnId,
                'user_id'        => $authUserId ?? null,
                'message'        => $e->getMessage(),
                'exception'      => $e::class,
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'trace'          => substr($e->getTraceAsString(), 0, 8000),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Unable to load transaction status. Try again.',
            ], 500);
        }
    }

    private function isUpiGatewayDeposit(Transaction $transaction, string $pg): bool
    {
        if (strtolower(trim($pg)) === 'upigateway') {
            return true;
        }

        $pi = strtolower(trim((string) $transaction->payment_info));
        if ($pi !== '' && (str_contains($pi, 'upi gateway') || str_starts_with($pi, 'upigateway'))) {
            return true;
        }

        try {
            return $transaction->gatewayPayment()->where('provider', 'ekqr')->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Gateway payment mirror (EKQR) + wallet row. Register in routes/api.php
     * (authenticated API group): Route::post('payment-status', [ApiController::class, 'payment_status_by_order']);
     */
    public function payment_status_by_order()
    {
        $requestTxnId = request()->input('txn_id');
        $requestOrderId = request()->input('gateway_order_id');
        $authUserId = null;
        $authUser = null;

        try {
            $authUser = $this->user();
        } catch (\Throwable $e) {
            Log::warning('[API] payment_status_by_order auth threw', [
                'error'     => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return response()->json(['status' => false, 'message' => 'Unauthorized.'], 401);
        }

        if (!$authUser) {
            return response()->json(['status' => false, 'message' => 'Unauthorized.'], 401);
        }

        $authUserId = $authUser->id;

        $validator = Validator::make(request()->all(), [
            'txn_id'           => 'nullable|string',
            'gateway_order_id' => 'nullable|string',
            'sync'             => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $txnId = is_string($requestTxnId) ? trim($requestTxnId) : '';
        $gatewayOrderId = is_string($requestOrderId) ? trim($requestOrderId) : '';

        if ($txnId === '' && $gatewayOrderId === '') {
            return response()->json(['status' => false, 'message' => 'Provide txn_id and/or gateway_order_id']);
        }

        Log::info('[API] payment_status_by_order', [
            'user_id'          => $authUserId,
            'txn_id'           => $txnId !== '' ? $txnId : null,
            'gateway_order_id' => $gatewayOrderId !== '' ? $gatewayOrderId : null,
            'sync'             => request()->boolean('sync'),
        ]);

        try {
            $q = GatewayPayment::query()->whereHas('transaction', function ($sub) use ($authUserId) {
                $sub->where('user_id', $authUserId);
            });

            if ($txnId !== '' && $gatewayOrderId !== '') {
                $q->where('client_txn_id', $txnId)->where('gateway_order_id', $gatewayOrderId);
            } elseif ($txnId !== '') {
                $q->where('client_txn_id', $txnId);
            } else {
                $q->where('gateway_order_id', $gatewayOrderId);
            }

            $gp = $q->first();

            if (!$gp) {
                Log::notice('[API] payment_status_by_order not found', [
                    'user_id' => $authUserId,
                ]);

                return response()->json(['status' => false, 'message' => 'Payment not found']);
            }

            $transaction = $gp->transaction;
            if (!$transaction) {
                return response()->json(['status' => false, 'message' => 'Transaction missing']);
            }

            $pg = '';
            try {
                $pg = strtolower((string) site_setting()->payment_gateway);
            } catch (\Throwable $e) {
                Log::error('[API] payment_status_by_order site_setting failed', [
                    'error' => $e->getMessage(),
                ]);
            }

            if (request()->boolean('sync') && (int) $transaction->status === 0 && $this->isUpiGatewayDeposit($transaction, $pg)) {
                try {
                    (new \App\Http\Controllers\PaymentGateway\UpiGatewayController())
                        ->syncStatus($transaction->txn_id, 4);
                    $transaction->refresh();
                    $gp->refresh();
                } catch (\Throwable $e) {
                    Log::error('[API] payment_status_by_order sync failed', [
                        'txn_id' => $transaction->txn_id,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }

            $userResource = null;
            try {
                $fresh = $authUser->fresh();
                if ($fresh) {
                    $userResource = new UserResource($fresh);
                }
            } catch (\Throwable $e) {
                Log::error('[API] payment_status_by_order UserResource failed', [
                    'error' => $e->getMessage(),
                ]);
            }

            $payload = [
                'status'  => true,
                'message' => 'Payment status fetched',
                'data'    => [
                    'txn_id'                     => $transaction->txn_id,
                    'gateway_order_id'           => $gp->gateway_order_id,
                    'amount'                     => $transaction->amount,
                    'transaction_status'         => $transaction->status,
                    'transaction_status_label'   => $transaction->status_label,
                    'gateway_payment_status'     => $gp->status,
                    'gateway_raw_status'         => $gp->gateway_raw_status,
                    'utr'                        => $gp->utr,
                    'webhook_received_at'        => $gp->webhook_received_at ? $gp->webhook_received_at->toIso8601String() : null,
                    'gateway_payment_updated_at' => $gp->updated_at ? $gp->updated_at->toIso8601String() : null,
                ],
            ];

            if ($userResource !== null) {
                $payload['user'] = $userResource;
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('[API] payment_status_by_order fatal', [
                'message'   => $e->getMessage(),
                'exception' => $e::class,
                'trace'     => substr($e->getTraceAsString(), 0, 6000),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Unable to load payment status.',
            ], 500);
        }
    }

    public function deposit_request_old()
    {
        # Validation
        $validator                              =   Validator::make(request()->all(), [
            'amount'                        => "required|numeric",
            'deposit_screenshot'                 => 'required',
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;
        # End Validation

        $amount                 =   request()->amount;
        $total_balance          =   auth('api')->user()->game_wallet_amount + request()->amount;
        $txn_id                =    "TXN_" . str()->upper(str()->random(10));

        $deposit_screenshot            = uploadFile('deposit_screenshot', "proof/deposit-request/$txn_id/");

        $result = Transaction::create([
            'txn_id'                    => $txn_id,
            'transfer_type'             => 'deposit',
            'amount'                    => $amount,
            'total_balance'             => $total_balance,
            'user_id'                   => auth('api')->user()->id,
            'deposit_screenshot'        => $deposit_screenshot,
            'status'                    => 0
        ]);

         # Wallet Update
         Wallet::create([
            'user_id'               =>  auth()->user()->id,
            'type'                  =>  'credit',
            'wallet_type'           =>  'game',
            'remark'                =>  "Deposit Fund",
            'amount'                =>  $amount,
            'total_balance'         =>  $total_balance,
            'transaction_id'        =>  $result->id,
            'status'                =>  0
        ]);
        # End Wallet Update

        return response()->json(['status' => true, 'message' => $result ? "Deposit request sent successfully. Your amount will be reflected in your wallet within 5-10 mins" : "Some error occured"]);
    }

    # Wallet History
    public  function wallet_history()
    {
        $arr                        =   [];
        $wallet_history             =   Wallet::whereUserId($this->user()->id)->latest('updated_at');

        # Filter
        switch (request()->filter):
            case 'deposit':
                $wallet_history->whereWalletType('game')->whereType('credit');
                break;

            case 'withdrawal':
                $wallet_history->whereWalletType('win')->whereType('debit')->where('remark', 'LIKE', '%Withdrawal%');
                break;

            case 'game':
                $wallet_history->whereWalletType('game');
                break;

            case 'win':
                $wallet_history->whereWalletType('win');
                break;
        endswitch;
        # End Filter

        $wallet_history             =    $wallet_history->paginate(10);

        $wallet_history_data             =   WalletResource::collection($wallet_history)->response()->getData(true);

        if ($wallet_history->count()):
            $arr                    =   [
                'status'                        => true,
                'message'                       => 'Data fetched successfully',
                'deposit_scanner_image'         => site_setting()->deposit_scanner_img_url,
                'upi_id'                        => site_setting()->upi_id,
                'minimum_deposit_amount'        => site_setting()->minimum_deposit_amount,
                'maximum_deposit_amount'        => site_setting()->maximum_deposit_amount,
                'payment_gateway'               => site_setting()->payment_gateway,
                'data'                          => $wallet_history_data,
                'user'                          => new UserResource($this->freshUser())
            ];
        else:
            $arr                    =   [
                'status'                    => true,
                'message'                   => 'No records found',
                'deposit_scanner_image'     => site_setting()->deposit_scanner_img_url,
                'upi_id'                        => site_setting()->upi_id,
                'minimum_deposit_amount'    => site_setting()->minimum_deposit_amount,
                'maximum_deposit_amount'    => site_setting()->maximum_deposit_amount,
                'payment_gateway'               => site_setting()->payment_gateway,
                'data'                      => $wallet_history_data,
                'user'                      => new UserResource($this->freshUser())
            ];
        endif;

        return response()->json($arr);
    }
    # End Wallet History

    # Notifications
    public function notifications()
    {
        $notifications          =   Notification::where(function ($query) {
            $query->whereRaw('FIND_IN_SET(?, user_ids)', [$this->user()->id])
                ->orWhere('user_ids', 0);
        })->whereIsSent(1)->latest()->paginate(10);


        $notifications_data          =   NotificationResource::collection($notifications)->response()->getData(true);

        if ($notifications->count()):
            $arr                    =   [
                'status'    => true,
                'message'   => 'Data fetched successfully',
                'data'      => $notifications_data,
                'user'      => new UserResource($this->user())
            ];
        else:
            $arr                    =   ['status' => true, 'message' => 'No records found', 'data' => $notifications_data];
        endif;

        return response()->json($arr);
    }
    # End Notifications

    # Leaderboard
   public function leaderboard()
   {
       $arr                    =   [];
       $filter  = request()->filter; // capture the filter from request
   
    $date_query = " 1 = 1 ";
    if ($filter === 'daily') {
        $date_query .= " AND wallet.created_at BETWEEN DATE_FORMAT(NOW(), '%Y-%m-%d 00:00:00') AND DATE_FORMAT(NOW() + INTERVAL 1 DAY, '%Y-%m-%d 00:00:00')";
    } elseif ($filter === 'weekly') {
        $date_query .= " AND wallet.created_at BETWEEN DATE_FORMAT(NOW() - INTERVAL WEEKDAY(NOW()) DAY, '%Y-%m-%d 00:00:00') AND DATE_FORMAT(NOW() + INTERVAL (6 - WEEKDAY(NOW())) DAY, '%Y-%m-%d 23:59:59')";
    } elseif ($filter === 'monthly') {
        $date_query .= " AND wallet.created_at BETWEEN DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00') AND LAST_DAY(NOW())";
    }

    $winners = User::select(
        'users.id',
        'users.uid',
        'users.name',
        'users.email',
        'users.mobile',
        'users.fcm_device_token',
        'users.profile',
        DB::raw("IFNULL(win_data.win_wallet_amount, 0) as win_wallet_amount"),
        'users.game_wallet_amount',
        'users.status'
    )
    ->leftJoin(DB::raw("(SELECT user_id, SUM(amount) as win_wallet_amount
                        FROM wallet 
                        WHERE type = 'credit' 
                        AND remark LIKE '%Winner%' 
                        AND status = 1 
                        AND wallet_type = 'win' 
                        AND game_challenge_id IS NOT NULL 
                        AND $date_query
                        GROUP BY user_id) as win_data"), 'win_data.user_id', '=', 'users.id')
                        ->having('win_wallet_amount', '>', 0)
                        ->orderByDesc('win_wallet_amount')
                        // return $winners->toRawSql();
    ->paginate(10);
                
    // return $winners;
    $winners_data           =   UserResource::collection($winners)->response()->getData(true);
       
       $data   = $winners_data;
   
       if (count($winners_data)):
           $arr                    =   [
               'status'    => true,
               'message'   => 'Data fetched successfully',
               'data'      =>  $data,
               'user'      => new UserResource($this->user())
           ];
       else:
           $arr                    =   [
               'status'    => true,
               'message'   => 'No records found',
               'data'      =>  $data,
               'user'      => new UserResource($this->user())
           ];
       endif;

       return response()->json($arr);
   }
   # End Leaderboard



    # Referrals
    public function referrals()
    {
        // Select only required fields from the users table and aggregate the referral amount
        $referral_users = User::where('refer_by', $this->user()->id)->paginate(20);

        foreach ($referral_users as $referral_user):
            $referral_user->total_generated_refer_amount  = round((float) CommissionHistory::whereUserId($referral_user->id)->whereReferBy($this->user()->id)->sum('refer_commission_amount'), 2);
        endforeach;

        $referrals_data = UserResource::collection($referral_users)->response()->getData(true);

        if (count($referrals_data)) {
            $arr = [
                'status'    => true,
                'message'   => 'Data fetched successfully',
                'data'      => $referrals_data,
                'user'      => new UserResource($this->user())
            ];
        } else {
            $arr = ['status' => false, 'message' => 'No records found'];
        }

        return response()->json($arr);
    }

    # End Referrals

    # Store Financial Details
    public function store_financial_details()
    {


        # Validation
        // Original rule allowed both: 'type' => 'required|in:bank_account,upi',
        $validator                              =   Validator::make(request()->all(), [
            'type'                 => 'required',
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;

        $type               =   request()->type;

        if ($type === 'upi') {
            return response()->json(['status' => false, 'message' => 'Unable to add UPI now. Only bank account can be saved.']);
        }

        if ($type !== 'bank_account') {
            return response()->json(['status' => false, 'message' => 'Only bank account details can be saved.']);
        }
        $account_name       =   request()->account_name;
        $account_number     =   request()->account_number;
        $ifsc_code          =   request()->ifsc_code;
        $is_default             =   request()->is_default;

        $user_id            =   auth('api')->user()->id;

        if ($is_default):

            Financial::whereUserId($user_id)->update(['is_default' => 0]);
        endif;

        if ($type == 'bank_account'):
            $validator                              =   Validator::make(request()->all(), [
                'account_name'                 => 'required',
                'account_number'                 => 'required',
                'ifsc_code'                     => 'required',
            ]);

            if ($validator->fails()) :
                $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
                return response()->json($arr);
            endif;

            $financial_data         = Financial::updateOrCreate(
                [
                    'id'                => request()->id
                ],
                [
                    'user_id'              => $user_id,
                    'type'              => $type,
                    'account_name'      => $account_name,
                    'account_number'    => $account_number,
                    'ifsc_code'         => $ifsc_code,
                    'is_default'        => $is_default,
                ]
            );
        endif;

        /*
        // UPI branch disabled — requests with type=upi return error above. Kept for reference:
        // $upi_id             =   request()->upi_id;
        // $status             =   request()->status;
        // if ($type == 'upi'):
        //     $validator                              =   Validator::make(request()->all(), [
        //         'upi_id'                 => 'required'
        //     ]);
        //
        //     if ($validator->fails()) :
        //         $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
        //         return response()->json($arr);
        //     endif;
        //
        //     $financial_data         = Financial::updateOrCreate(
        //         [
        //             'id'                => request()->id
        //         ],
        //         [
        //             'user_id'           => $user_id,
        //             'type'              => $type,
        //             'upi_id'            => $upi_id,
        //             'is_default'        => $is_default,
        //             'status'        => $status,
        //         ]
        //     );
        // endif;
        */

        # End Validation



        if ($financial_data ?? null):
            #
            $message            =   "";
            if ($financial_data->wasRecentlyCreated) :
                $message = "Record  added successfully";
            else:
                $message = "Record  updated successfully";
            endif;
            #

            $arr                    =   [
                'status'    => true,
                'message'   => $message,
                'data'      => new FinancialResource($financial_data),
                'user'      => new UserResource($this->user())
            ];
        else:
            $arr                    =   [
                'status'    => true,
                'message'   => 'No records found',
                'user'      => new UserResource($this->user())
            ];
        endif;

        return response()->json($arr);
    }
    # End Store Financial Details

    # Financial Details
    public function financial_remove()
    {

        $validator                              =   Validator::make(request()->all(), [
            'id'                 => 'required|exists:financial_details,id'
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;


        $user_id                       =   auth('api')->user()->id;
        $financial_records              =    Financial::whereId(request()->id)->whereUserId($user_id)->delete();

        if ($financial_records):
            $arr                        =   [
                'status'    => true,
                'message'   => 'Record removed successfully',
                'user'      => new UserResource($this->user())
            ];
        else:
            $arr                        =   [
                'status'    => true,
                'message'   => 'No records found',
                'user'      => new UserResource($this->user())
            ];
        endif;

        return response()->json($arr);
    }
    # End Financial Details

    # Financial List
    public function financial_list()
    {
        $user_id                   =   auth('api')->user()->id;
        $financial_records             =    Financial::whereUserId($user_id)->latest()->get();
        $bank_details               =   null;
        $upi                        =   null;

        #
        foreach ($financial_records ?? [] as $financial_record):
            if ($financial_record->type == 'upi'):
                $upi       =   $financial_record;
            endif;

            if ($financial_record->type == 'bank_account'):
                $bank_details       =   $financial_record;
            endif;
        endforeach;
        #

        $arr                        =   [];
        $wallet_history             =   Wallet::whereUserId($this->user()->id)->latest('updated_at');

        $wallet_history->whereWalletType('win')->whereType('debit')->where('game_challenge_id', null);

        $wallet_history             =    $wallet_history->paginate(10);
        $wallet_history_data             =   WalletResource::collection($wallet_history)->response()->getData(true);
        $arr =   [
            'status' => true,
            'message' => 'Records fetched',
            'minimum_win_amount' => site_setting()->minimum_win_amount,
            'win_to_game_cashback_percentage' => site_setting()->win_to_game_cashback_percentage,
            'wallet_history' => $wallet_history,
            'upi' => $upi,
            'bank_details' => $bank_details,
            'user' => new UserResource($this->user())
        ];

        return response()->json($arr);
    }
    # End Financial List

    # Challenge
    public function game_challenge()
    {
        $arr                    =   [];


        $validator                  =   Validator::make(request()->all(), [
            'id'                    =>  'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'                    =>  false,
                'message'                   =>  $validator->errors()->first()
            ]);
        }

        $id                     =   request()->id;

        $game_challenge         =   GameChallenge::select(
            'id',
            'uid',
            'game_type_id',
            'roomcode',
            'amount',
            'paid_amount',
            'challenger_amount',
            'challenger_id',
            'opponent_id',
            'challenger_status',
            'opponent_status',
            'status'
        )->whereId($id)->first();


        if ($game_challenge) :

            $arr =  [
                'status'            => true,
                'message'           => 'Successfully records fetched',
                'data'              => new GameChallengeResource($game_challenge),
                'user'              => new UserResource($this->user())

            ];
        else :
            $arr =  [
                'status' => true,
                'message' => 'No records found'
            ];
        endif;

        return response()->json($arr);
    }
    # End Challenge

    public function transfer_win_to_game_amount(){

        $validator =   Validator::make(request()->all(), [
            'amount' =>  'required|gt:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'                    =>  false,
                'message'                   =>  $validator->errors()->first()
            ]);
        }

       $amount = request()->amount;
       $user = User::find($this->user()->id);
       $minimum_win_amount = site_setting()->minimum_win_amount;

        if($minimum_win_amount > $amount):
            return response()->json([
                                    'status' => false,
                                    'message' => 'Required minimum win amount ₹ '.$minimum_win_amount
                                ]);
       endif;

        if($amount > $user->win_wallet_amount):
            return response()->json([
                                    'status' => false,
                                    'message' => 'Insufficient balance'
                                ]);
       endif;

       #
       $cashback_amount = 0;
       $cashback_percentage = site_setting()->win_to_game_cashback_percentage;
       $cashback_amount = ( $cashback_percentage / 100 ) * $amount;
       #

       #
       $actual_win_amount = $user->win_wallet_amount;
       $transferred_win_amount = $amount;
       $actual_game_amount = $user->game_wallet_amount;
       #
       # Each leg is an atomic statement with its own ledger row, so a refund or
       # payout landing mid-transfer can no longer be overwritten.
       $wallet_service = app(WalletService::class);

       $debited = $wallet_service->debit((int) $user->id, 'win', (float) $amount, [
           'remark' => 'Transfer to game wallet',
           'status' => 1,
       ]);

       if(! $debited):
            return response()->json([
                                    'status' => false,
                                    'message' => 'Insufficient balance'
                                ]);
       endif;

       $after_transfer = $wallet_service->credit((int) $user->id, 'game', (float) $amount, [
           'remark' => 'Transfer from win wallet',
           'status' => 1,
       ]);

       $game_amount_without_cashback = $after_transfer['game'] ?? $actual_game_amount;

       $after_cashback = $cashback_amount > 0
            ? $wallet_service->credit((int) $user->id, 'game', (float) $cashback_amount, [
                'game_challenge_id' => 0,
                'type' => 'cashback',
                'remark' => 'Transfer from win to game',
                'status' => 1,
            ])
            : $after_transfer;

       $game_amount_with_cashback = $after_cashback['game'] ?? $game_amount_without_cashback;

        $user->refresh();

        $result = $after_transfer !== null;

        #============ Transfer Cashback ============#
        if($result):
            TransferCashback::create([
                                        'user_id' => $user->id,
                                        'cashback_percentage' => $cashback_percentage,
                                        'cashback_amount' => $cashback_amount,
                                        'actual_win_amount' => $actual_win_amount,
                                        'transferred_win_amount' => $transferred_win_amount,
                                        'remaining_win_amount' => $user->win_wallet_amount,
                                        'actual_game_amount' => $actual_game_amount,
                                        'game_amount_without_cashback' => $game_amount_without_cashback,
                                        'game_amount_with_cashback' => $game_amount_with_cashback
                                    ]);
        endif;
        #============ !Transfer Cashback ============#

        # ===========================================================================
        #   Notification
        # ===========================================================================
        if($result):
            safe_notify(
                $user->fcm_device_token,
                'Win amount successfully tranferred to game wallet.',
                'Win Amount ( ₹'.$amount.' ) successfully tranferred to game wallet.',
                'transfer_win_to_game_amount',
                $user->id,
                ['user_id' => $user->id]
            );
        endif;
            # Notification

            # ===========================================================================
            #   Notification
            # ===========================================================================

        $arr =  [
            'status'            => $result ? true : false,
            'message'           => $result ? 'Successfully win amount transferred to game wallet' : 'Some error occured',
            'user'              => new UserResource($user)
        ];
      
        return response()->json($arr);
    }

    /**
     * Cashier: list pending withdrawal transactions.
     * Requires the authenticated user to have `is_cashier = 1`.
     */
    public function cashier_withdrawals()
    {
        $user = $this->user();
        if (!$user || !$user->is_cashier) {
            return response()->json(['status' => false, 'message' => 'You do not have cashier access.'], 403);
        }

        $query = Transaction::with(['user:id,uid,name,mobile,win_wallet_amount,kyc_status'])
            ->whereTransferType('withdrawal')
            ->latest();

        $statusFilter = request()->status;
        if ($statusFilter === 'pending') {
            $query->whereStatus(0);
        } elseif ($statusFilter === 'history') {
            $query->where('status', '!=', 0);
        } else {
            $query->whereStatus(0);
        }

        $records = $query->paginate(20);

        $data = $records->getCollection()->map(function ($t) {
            return [
                'id'              => $t->id,
                'txn_id'          => $t->txn_id,
                'amount'          => (float) $t->amount,
                'remark'          => $t->remark,
                'status'          => (int) $t->status,
                'status_label'    => $t->status_label,
                'created_at'      => $t->created_at,
                'bank_details'    => $this->withdrawalBankDetailsForCashier($t),
                'user'            => $t->user ? [
                    'id'                  => $t->user->id,
                    'uid'                 => $t->user->uid,
                    'name'                => $t->user->name,
                    'mobile'              => $t->user->mobile,
                    'win_wallet_amount'   => (float) $t->user->win_wallet_amount,
                    'kyc_status'          => (int) $t->user->kyc_status,
                ] : null,
            ];
        });

        return response()->json([
            'status'  => true,
            'message' => 'Withdrawals fetched',
            'today_total_processed_amount' => $this->todayWithdrawalApprovedTotalAmount(),
            'data'    => $data,
            'meta'    => [
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
                'total'        => $records->total(),
            ],
        ]);
    }

    /**
     * Cashier: approve or reject a pending withdrawal transaction.
     * Mirrors AdminController@update_transaction_status withdrawal branch.
     */
    public function cashier_withdrawal_action()
    {
        $user = $this->user();
        if (!$user || !$user->is_cashier) {
            return response()->json(['status' => false, 'message' => 'You do not have cashier access.'], 403);
        }

        $validator = Validator::make(request()->all(), [
            'id'         => 'required|integer|exists:transactions,id',
            'actionType' => 'required|in:approve,reject',
            'remark'     => 'required_if:actionType,reject|nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $transaction = Transaction::find(request()->id);
        if ($transaction->transfer_type !== 'withdrawal') {
            return response()->json(['status' => false, 'message' => 'Not a withdrawal transaction.']);
        }
        if ((int) $transaction->status !== 0) {
            return response()->json(['status' => false, 'message' => 'Already processed.']);
        }

        $targetUser = User::find($transaction->user_id);
        $message    = '';

        if (request()->actionType === 'approve') {
            $transaction->status = 1;
            $transaction->remark = "Approved by cashier ({$user->name})";
            $transaction->save();

            Wallet::whereTransactionId($transaction->id)->update([
                'win_and_game_total_amount' => $targetUser->total_wallet_amount,
                'status' => 1,
            ]);

            safe_notify(
                optional($targetUser)->fcm_device_token,
                'Withdrawal Successfully',
                'Amount withdrawal successfully.',
                'withdrawal',
                optional($targetUser)->id,
                ['transaction_id' => $transaction->id]
            );

            $message = 'Withdrawal approved successfully';
        } else {
            $remark = request()->remark;
            $transaction->status = 3;
            $transaction->remark = $remark;
            $transaction->save();

            app(WalletService::class)
                ->incrementColumn((int) $targetUser->id, 'win_wallet_amount', (float) $transaction->amount);
            $targetUser->refresh();

            Wallet::whereTransactionId($transaction->id)->update([
                'type'                      => 'credit',
                'remark'                    => 'Rejected your withdrawal: ' . $remark,
                'win_and_game_total_amount' => $targetUser->total_wallet_amount,
                'status'                    => 2,
            ]);

            safe_notify(
                optional($targetUser)->fcm_device_token,
                'Withdrawal Rejected',
                'Your withdrawal request was rejected. Reason: ' . $remark,
                'withdrawal',
                optional($targetUser)->id,
                ['transaction_id' => $transaction->id]
            );

            $message = 'Withdrawal rejected and amount refunded';
        }

        $transaction->refresh();

        return response()->json([
            'status' => true,
            'message' => $message,
            'today_total_processed_amount' => $this->todayWithdrawalApprovedTotalAmount(),
            'bank_details' => $this->withdrawalBankDetailsForCashier($transaction),
        ]);
    }
}
