<?php

namespace App\Http\Controllers\Admin\GameChallenge;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardStatsService;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Wallet;
use App\Models\User;
use App\Services\GameChallengeWinnerPayoutService;
use Illuminate\Http\Request;
class GameChallengeController extends Controller
{
    # Global 
    public $index_view                  =   "admin.pages.game-challenges.index";
    public $create_or_edit_view         =   "admin.pages.game-challenges.single";

    public $index_route                 =   "admin::game-challenges.index";

    public $permission_key              =   "";
    public $table_name                  =   "game_challenges";
    public $folder_name                 =   "game-challenges";
    public $page_title                  =   "Game Challenge";

    # Consturct

    protected $shared_data;

    public function __construct()
    {
        #
        switch (request()->filter):
            case 'pending_challenges':
                $this->permission_key   =  'pending_challenges';
                $this->page_title       =  'Pending Challenges';
                break;
            case 'live_challenges':
                $this->permission_key   =  'live_challenges';
                $this->page_title       =  'Live Challenges';
                break;
            case 'complete_challenges':
                $this->permission_key   =  'complete_challenges';
                $this->page_title       =  'Complete Challenges';
                break;
            case 'uncomplete_challenges':
                $this->permission_key   =  'uncomplete_challenges';
                $this->page_title       =  'Uncomplete Challenges';
                break;
            case 'uncomplete_games':
                $this->permission_key   =  'uncomplete_games';
                $this->page_title       =  'Uncomplete Game';
                break;
            case 'uncomplete_cancel_games':
                $this->permission_key   =  'uncomplete_cancel_games';
                $this->page_title       =  'Uncomplete Cancel Game';
                break;
            case 'dispute_games':
                $this->permission_key   =  'dispute_games';
                $this->page_title       =  'Dispute Game';
                break;
            case 'dispute_games':
                $this->permission_key   =  'dispute_games';
                $this->page_title       =  'Dispute Game';
                break;

            default:
                $this->permission_key   =  'game_challenges';
                $this->page_title       =  'Game Challenges';
                break;
        endswitch;
        #

        $this->shared_data          =   (object) [
            'page_title'        => $this->page_title,
            'index_route'       => $this->index_route,
            'create_route'      => "admin::game-challenges.create",
            'edit_route'        => "admin::game-challenges.edit",
            'store_route'       => "admin::game-challenges.store",
            'destroy_route'     => "admin::game-challenges.destroy",
            'permission_key'    => $this->permission_key,
        ];
        view()->share('shared_data', $this->shared_data);
    }
    # End Consturct

    # ==> Model 
    public function eloquentModel()
    {
        return  new GameChallenge();
    }
    # ==> !Model 

    # End Global 

    # Index
    public function index()
    {
        $this->authorize('permissions', [$this->permission_key, 'view']);

        if (request()->ajax()) :
            
            // Capture search keyword
            $keyword = request('search')['value'] ?? null;

            $stats = app(AdminDashboardStatsService::class);
            $data       =  $this->eloquentModel()->with(['challenger', 'opponent'])->latest();

            $completedDateFilters = ['complete_challenges', 'classic', 'ulta-ludo'];

            switch (request()->filter):
                case 'pending_challenges':
                    $data->where(function ($query){
                        $query->whereStatus(3)
                            ->where('challenger_status', '!=', 3)
                            ->where('opponent_status', '!=', 3)
                            ->orWhere(function ($sub_query) {
                                $sub_query->whereNull('roomcode');
                                $sub_query->where('status', '!=', 2);
                                $sub_query->where('status', '!=', 3);
                                $sub_query->where('status', '!=', 7);
                                $sub_query->where('status', '!=', 8);
                            });
                    });

                    // dd($data->toRawSql());

                    break;
                case 'live_challenges':
                    $data       = $data->whereNotNull('roomcode')->whereStatus(1);
                    break;
                case 'uncomplete_challenges':
                    // $data       = $data->whereStatus(3)->orWhere('status', 6)->orWhere('status', 7)->orWhere('status', 8);
                    $data->where(function ($query){
                        $query->whereStatus(3)->orWhere('status', 6)->orWhere('status', 7)->orWhere('status', 8);
                    });
                    break;
                case 'uncomplete_games':
                    $data       = $data->whereStatus(8);
                    break;
                case 'uncomplete_cancel_games':
                    $data       = $data->whereStatus(2);
                    break;
                case 'complete_challenges':
                    $data       = $data->whereStatus(4);
                    break;
                case 'dispute_games':
                    $data       = $data->whereStatus(5);
                    break;
                case 'classic':
                    $data       = $data->whereGameTypeId(1)->whereStatus(4);
                    break;
                case 'ulta-ludo':
                    $data       = $data->whereGameTypeId(2)->whereStatus(4);
                    break;
                default:

                    break;
            endswitch;
            #

            if (request()->date) {
                if (in_array(request()->filter, $completedDateFilters, true)) {
                    $day = $stats->filterDateString(request()->date);
                    $data->where(function ($query) use ($day) {
                        $query->whereDate('closed_at', $day)
                            ->orWhere(function ($inner) use ($day) {
                                $inner->whereNull('closed_at')
                                    ->whereDate('created_at', $day);
                            });
                    });
                } else {
                    $data->whereDate('created_at', request()->date);
                }
            }

            if ($keyword) {
                $data->where(function ($query) use ($keyword) {
                    $query->where('uid', 'LIKE', '%' . $keyword . '%')
                          ->orWhere('roomcode', 'LIKE', '%' . $keyword . '%')
                          ->orWhereHas('challenger', function ($subQuery) use ($keyword) {
                              $subQuery->where('uid', 'LIKE', '%' . $keyword . '%')
                                       ->orWhere('name', 'LIKE', '%' . $keyword . '%');
                          })
                          ->orWhereHas('opponent', function ($subQuery) use ($keyword) {
                              $subQuery->where('uid', 'LIKE', '%' . $keyword . '%')
                                       ->orWhere('name', 'LIKE', '%' . $keyword . '%');
                          });
                });
            }
            
            // return $data->toRawSql();
            
            // $data       =   $data->get()->makeVisible(['status_view']);

            return datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('roomcode_details', function ($record) {

                    $roomcode_date_time = $record->roomcode_datetime;
                    return "
                            <div>$record->roomcode</div>
                            <div>$roomcode_date_time</div>
                            ";
                })
                ->addColumn('amount', function ($record) {
                    return "₹ " . $record->challenger_amount;
                })
                ->addColumn('game_commission_amount', function ($record) {
                    return $record->game_commission ? "₹ " . $record->game_commission_amount : 0;
                })
                ->addColumn('paid_amount', function ($record) {
                    return "₹ " . $record->paid_amount;
                })
                ->rawColumns(['game_details', 'challenger_details', 'opponent_details', 'roomcode_details', 'status_view', 'action'])
                ->make(true);
        endif;

        return view()->exists($this->index_view) ? view($this->index_view) : abort(404);
    }
    # Index

    # Create
    public function create()
    {
        $this->authorize('permissions', [$this->permission_key, 'create']);
        return view()->exists($this->create_or_edit_view) ? view($this->create_or_edit_view) : abort(404);
    }
    # End Create

    #################################################################### Store ####################################################################

    # Validation : Step 1
    public function validation()
    {
        request()->validate([
            'name'                      =>  "required|max:225",
            'mobile'                    =>  "required|digits:10|unique:$this->table_name,mobile," . request()->id,
            // 'email'                     =>  "email",
            // 'dob'                       =>  "date",
            'status'                    =>  "required|in:0,1",
        ]);
    }
    # End Validation : Step 1

    # Custom Create Or Update : Step 2
    public function updateOrCreate()
    {
        $uid                     =  request()->uid ? request()->uid : "LF" . random_int(10000000, 99999999);;

        $profile                 =   uploadFile('profile', "profile/$uid/");
        $kyc_document_front      =   uploadFile('kyc_document_front', "kyc_document_front/$uid/");
        $kyc_document_back       =   uploadFile('kyc_document_back', "kyc_document_back/$uid/");

        $record = $this->eloquentModel()->updateOrCreate(
            [
                'id'                    => request()->id
            ],
            [
                'uid'                   => $uid,
                'name'                  => request()->name,
                'mobile'                => request()->mobile,
                'email'                 => request()->email,
                'profile'               => $profile,
                'dob'                   => request()->dob,
                'state_id'              => request()->state_id,
                'document_type_id'      => request()->document_type_id,
                'document_id'           => request()->document_id,
                'kyc_document_front'    => $kyc_document_front,
                'kyc_document_back'     => $kyc_document_back,
                'kyc_status'            => request()->kyc_status,
                'status'                => request()->status,
            ]
        );

        return $record;
    }
    # End Custom Create Or Update : Step 2

    # Store : Step 3
    public function store()
    {
        $this->authorize('permissions', [$this->permission_key, 'create']);

        # Custom method for validation
        $this->validation();
        # Custom method for validation

        # Update Or Create
        $result   =     $this->updateOrCreate();
        # End Update Or Create

        if ($result) :
            $back_msg                            =   $result->wasRecentlyCreated ?
                "<div class='alert alert-success'>Record added successfully </div>"
                :
                "<div class='alert alert-success'>Record udpated successfully </div>";
        else :
            $back_msg                            =   "<div class='alert alert-danger'>Some error occured</div>";
        endif;

        return redirect()->route($this->index_route)->with('back_msg', $back_msg);
    }
    # End Store : Step 3

    #################################################################### !Store ####################################################################

    public function show($id)
    {
        $this->authorize('permissions', [$this->permission_key, 'view']);
    }

    public function edit($id)
    {
        $this->authorize('permissions', [$this->permission_key, 'edit']);

        $record                 =   $this->eloquentModel()->find($id);

        return view()->exists($this->create_or_edit_view) ? view($this->create_or_edit_view, compact('record')) : abort(404);
    }

    public function update(Request $request, $id) {}

    public function destroy($id)
    {
        $this->authorize('permissions', [$this->permission_key, 'delete']);
        $record     =   $this->eloquentModel()->find($id);
        $record->delete();

        return back()->with('back_msg', "<div class='alert alert-success'>Record deleted successfully</div>");
    }

    # update_game_challenge_result
    public function update_game_challenge_result()
    {
        $game_challenge_id                  =   request()->game_challenge_id;
        $action                             =   request()->action;
        $penalty                            =   request()->penalty;
        
        $game_challenge                     =   GameChallenge::whereUid($game_challenge_id)->first();

        if(($game_challenge->status == 3 && $game_challenge->challenger_status == 3 && $game_challenge->opponent_status == 3 ) || $game_challenge->status == 7):
            return json_encode(['status' => false, 'message' => "Already cancelled"]);
        endif;
        
        if($game_challenge->status == 4):
            return json_encode(['status' => false, 'message' => "Already completed"]);
        endif;
        
        if($game_challenge->status == 6):
            return json_encode(['status' => false, 'message' => "Already suspended"]);
        endif;

        if ($game_challenge->is_lock) :
            return response()->json(['status' =>  false, 'message' => 'Game Status updating please wait.....']);
        endif;
        
        lock_game_challenge($game_challenge);

        switch ($action):
            case 'challenger_win';

                if (! $game_challenge->opponent_id):
                    unlock_game_challenge($game_challenge);

                    return json_encode(['status' => false, 'message' => 'Opponent not found']);
                endif;

                app(GameChallengeWinnerPayoutService::class)->awardChallengerWin($game_challenge);

                break;

            case 'opponent_win';
                $opponent = User::find($game_challenge->opponent_id);

                if (!$opponent):
                    unlock_game_challenge($game_challenge);

                    return json_encode(['status' => false, 'message' => 'Opponent not found']);
                endif;

                app(GameChallengeWinnerPayoutService::class)->awardOpponentWin($game_challenge);

                break;

            case 'cancel';


                // Update Game Challenge Status
                $game_challenge->opponent_status = 3;
                $game_challenge->challenger_status = 3;
                $game_challenge->status = 7;
                $id = $game_challenge->id;
                $amount = $game_challenge->challenger_amount;

                $challenger_user = User::find($game_challenge->challenger_id);
                    $opponent_user  = User::find($game_challenge->opponent_id);

                    $challenger_wallet_history = Wallet::whereUserId($challenger_user->id)
                        ->whereGameChallengeId($id)
                        ->whereType('debit')
                        ->get();

                    $opponent_wallet_history = Wallet::whereUserId($opponent_user->id ?? 0)
                        ->whereGameChallengeId($id)
                        ->whereType('debit')
                        ->get();

                    // Combine the wallet histories
                    $wallet_histories = $challenger_wallet_history->concat($opponent_wallet_history);

                    foreach ($wallet_histories as $wallet_history) {
                        $wallet_type = $wallet_history->wallet_type;
                        $amount = $wallet_history->amount;

                        // Determine which user is being updated and update the balance accordingly
                        if ($challenger_user->id == $wallet_history->user_id) {
                            $total_balance = ($wallet_type == 'game')
                                ? $challenger_user->game_wallet_amount + $amount
                                : $challenger_user->win_wallet_amount + $amount;

                            if ($wallet_type == 'game') {
                                $challenger_user->game_wallet_amount = $total_balance;
                            } elseif ($wallet_type == 'win') {
                                $challenger_user->win_wallet_amount = $total_balance;
                            }
                        }

                        if (( $opponent_user->id ?? 0 ) == $wallet_history->user_id) {
                            $total_balance = ($wallet_type == 'game')
                                ? $opponent_user->game_wallet_amount + $amount
                                : $opponent_user->win_wallet_amount + $amount;

                            if ($wallet_type == 'game') {
                                $opponent_user->game_wallet_amount = $total_balance;
                            } elseif ($wallet_type == 'win') {
                                $opponent_user->win_wallet_amount = $total_balance;
                            }
                        }

                        if($wallet_history->user_id):
                            // Create a new wallet entry
                            Wallet::create([
                                'user_id' => $wallet_history->user_id,
                                'game_challenge_id' => $id,
                                'type' => 'credit',
                                'wallet_type' => $wallet_type,
                                'remark' => "Challenge Refund Ref: $game_challenge->uid",
                                'amount' => $wallet_history->amount,
                                'total_balance' => $total_balance,
                                'status' => 1,
                            ]);
                        endif;
                    }

                    // Save changes to both users after processing all wallet histories
                    $challenger_user->save();

                    if(( $opponent_user->id ?? 0 )):
                        $opponent_user->save();
                    endif;
                break;

            case 'suspended';
                $game_challenge->status                             =   6;
                break;
        endswitch;
        #

        switch ($penalty) {
            case '1':
                // Apply penalty for challenger
                $challenger_user = User::find($game_challenge->challenger_id);
                $this->applyPenalty($challenger_user, $game_challenge, 'challenger');
                break;

            case '2':
                if(!$game_challenge->opponent_id):
                    unlock_game_challenge($game_challenge);
                    return json_encode(['status' => false, 'message' => "Opponent not found"]);
                endif;

                // Apply penalty for opponent
                $opponent_user = User::find($game_challenge->opponent_id);
                $this->applyPenalty($opponent_user, $game_challenge, 'opponent');
                break;
        }

        $game_challenge->save();
        # 

        unlock_game_challenge($game_challenge);
        
        $arr                =   ['status' => true, 'message' => 'Successfully challenge updated'];

        return response()->json($arr);
    }
    # update_game_challenge_result

    # 
    function applyPenalty($user, $game_challenge, $wallet_type)
    {
        $penalty_amount_charge = site_setting()->penalty_amount;
        $remaining_penalty_amount = $penalty_amount_charge;
    
        // Deduct from both wallets in order
        $remaining_penalty_amount = $this->deductFromWallet(
            $user, $game_challenge, 'game', $user->game_wallet_amount, $remaining_penalty_amount
        );
    
        if ($remaining_penalty_amount > 0) {
            $remaining_penalty_amount = $this->deductFromWallet(
                $user, $game_challenge, 'win', $user->win_wallet_amount, $remaining_penalty_amount
            );
        }
    
        // Save user wallet updates in one go
        $user->save();
    
        // Update Game Challenge penalty information
        $game_challenge->update([
            'penalty'                   => 1,
            'total_penalty_amount'       => $penalty_amount_charge,
            'deducted_penalty_amount'    => $penalty_amount_charge - $remaining_penalty_amount,
            'remaining_penalty_amount'   => $remaining_penalty_amount,
            'penalty_status'             => ($remaining_penalty_amount) ? 0 : 1,
        ]);
    }
    
    /**
     * Deducts from a wallet and logs the transaction.
     *
     * @param $user
     * @param $game_challenge
     * @param string $wallet_type 'game' or 'win'
     * @param float $wallet_amount
     * @param float $penalty_amount
     * @return float Remaining penalty amount after deduction
     */
    private function deductFromWallet($user, $game_challenge, $wallet_type, $wallet_amount, $penalty_amount)
    {
        $deduct_amount = min($wallet_amount, $penalty_amount);
        $remaining_penalty_amount = $penalty_amount - $deduct_amount;
    
        if($deduct_amount):
            // Log Wallet Transaction
            Wallet::create([
                'user_id'           => $user->id,
                'game_challenge_id' => $game_challenge->id,
                'type'              => 'debit',
                'wallet_type'       => $wallet_type,
                'remark'            => "Penalty Deducted from {$wallet_type} Wallet Ref: {$game_challenge->uid}",
                'amount'            => $deduct_amount,
                'total_balance'     => $wallet_amount - $deduct_amount,
                'status'            => 1
            ]);
        endif;
    
        // Update user's wallet balance
        $wallet_field = "{$wallet_type}_wallet_amount";
        $user->$wallet_field = $wallet_amount - $deduct_amount;
    
        return $remaining_penalty_amount;
    }

    # Delete Game Challenge
    public function delete_game_challenge(){
        $game_id            =   request()->game_id;

    
        $validator                  =   validator()->make(request()->all(), [
            'game_id'                    =>  'required|exists:game_challenges,uid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'                    =>  false,
                'message'                   =>  $validator->errors()->first()
            ]);
        }

        # Delete
        $deleted = $this->eloquentModel()->whereUid($game_id)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Record successfully deleted.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Record not found or deletion failed.',
            ]);
        }
    }
  
}
