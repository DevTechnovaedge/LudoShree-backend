<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\GameChallenge\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\GameChallenge\CommissionHistory;
use App\Models\GameChallenge\GameChallenge;

class UserController extends Controller
{
    # Global
    public $index_view                  =   "admin.users.index";
    public $create_or_edit_view         =   "admin.users.single";

    public $index_route                 =   "admin::users.index";

    public $permission_key              =   "all_users";
    public $table_name                  =   "users";
    public $folder_name                 =   "users";
    public $page_title                 =    "User";

    # Consturct

    protected $shared_data;

    public function __construct()
    {

        #
        switch (request()->filter):
            case 'zero_balance_users':
                $this->permission_key = 'zero_balance_users';
                $this->page_title       =   'Zero Balance User';
                break;
            case 'kyc_pending':
                $this->permission_key = 'kyc_pending_users';
                $this->page_title       =   'KYC Pending User';
                break;
            case 'kyc_complete':
                $this->permission_key = 'kyc_complete_users';
                $this->page_title       =   'KYC Complete User';
                break;
        endswitch;
        #
        $this->shared_data          =   (object) [
            'page_title'        => $this->page_title,
            'index_route'       => $this->index_route,
            'create_route'      => "admin::users.create",
            'edit_route'        => "admin::users.edit",
            'store_route'       => "admin::users.store",
            'destroy_route'     => "admin::users.destroy",
            'permission_key'    => $this->permission_key,
        ];
        view()->share('shared_data', $this->shared_data);
    }
    # End Consturct

    # ==> Model
    public function eloquentModel()
    {
        return  new User();
    }
    # ==> !Model

    # End Global

    # Index
    public function index()
    {

        $this->authorize('permissions', [$this->permission_key, 'view']);

        if (request()->ajax()) :

            $data       =  $this->eloquentModel()->latest('otp_expires_at')->withoutGlobalScope('active')->with('refer_by_user:refer_by,id,uid');

            $registrationDate = $this->resolveUserRegistrationDateFilter();

            if ($registrationDate) {
                $data->whereDate('created_at', $registrationDate);
            }

            switch (request()->filter):
                case 'today':
                    break;
                case 'zero_balance_users':
                    $data       = $data->zeroBalance();
                    break;
                case 'kyc_pending':
                    $data       = $data->whereKycStatus(0);
                    break;
                case 'kyc_complete':
                    $data       = $data->whereKycStatus(1);
                    break;
                default:

                    break;
            endswitch;

            # From - To
            // Parse the 'from' date to start at the beginning of the day
            $from = request()->from ? carbon()->parse(request()->from)->startOfDay() : null;
            // Parse the 'to' date to include the end of the day (23:59:59)
            $to = request()->to ?  carbon()->parse(request()->to)->endOfDay() : null;

            if ($from && $to):
                $data->whereBetween('created_at', [$from, $to]);
            endif;

            if ($from && !$to):
                $data = $data->whereDate('created_at', '>=', $from);
            endif;

            if (!$from && $to):
                $data = $data->whereDate('created_at', '<=', $to);
            endif;
            # End From - To
            # End Filter

            return datatables()->eloquent($data)
                    
                 ->addIndexColumn()
                ->editColumn('name', function ($record) {
                    $name               =   $record->name;

                    if (auth('admin')->user()->can('permissions', [$this->permission_key, 'edit'])) :
                        $edit_route         =   route($this->shared_data->edit_route, $record->id);
                        $name                .=  "<div><small>( UID : <a href='$edit_route' target='_blank'>$record->uid</a> )</small></div>";
                    else:
                        $name                .=  "<div><small>( UID : $record->uid )</small></div>";
                    endif;

                    return $name;
                })
                ->editColumn('email', function ($record) {
                    return auth('admin')->user()->can('permissions', ['email', 'view']) ?  ($record->email ? $record->email : '-') : '-';
                })
                ->editColumn('mobile', function ($record) {
                    return auth('admin')->user()->can('permissions', ['mobile', 'view']) ?  ($record->mobile ? $record->mobile : '-') : '-';
                })
                ->editColumn('otp_view', function ($record) {
                    $expiresAt = strtotime($record->otp_expires_at);
                    $now = time(); // Current timestamp
                
                    // Check if the expiration time is within the next 10 minutes
                    if ($expiresAt > $now && $expiresAt <= ($now + 600)) { // 600 seconds = 10 minutes
                        $view = "$record->otp <br>";
                        $view .= "<small style='color: red;'>Expires at: " . date('h:i A', $expiresAt) . "</small>";
                        return $view;
                    }
                
                    return ''; // Return empty if not expiring in the next 10 minutes
                })
                
                ->addColumn('game_play_count', function ($record) {
                    return $record->game_play_count ?? 0;
                })
                ->addColumn('refer_count', function ($record) {
                    return $record->refer_count ?? 0;
                })
                ->addColumn('refer_by', function ($record) {
                    return ($record->refer_by_user->uid ?? 0) ? $record->refer_by_user->uid : '-';
                })
                ->addColumn('withdrawal', function ($record) {
                    return 0;
                })
                ->addColumn('refer_by', function ($record) {

                    $refer_uid = $record->refer_by_user->uid ?? 0;

                    if (!$refer_uid):
                        return 'Admin';
                    endif;

                    if (request()->user()->can('permissions', [$this->permission_key, 'edit'])) :
                        $edit_route         =   route($this->shared_data->edit_route, $record->refer_by_user->id);
                        $refer_by                =  "<small><a href='$edit_route' target='_blank'>$refer_uid</a></small>";
                    else:
                        $refer_by                = $record->refer_by_user->uid;
                    endif;

                    return $refer_by;
                })
                ->addColumn('action', function ($record) {
                    $row                    =   "";

                    if ($record->deleted_at) :
                        if (request()->user()->can('permissions', [$this->permission_key, 'delete'])) :
                            $restore_route      =   route($this->shared_data->restore_route, $record->id);
                            $row                .=  "<a href='$restore_route' class='btn btn-success btn-sm' onclick='return confirm(`Are you sure!`)'><i class='fa fa-trash-restore'></i></a>";
                        endif;
                    else :
                        if (request()->user()->can('permissions', [$this->permission_key, 'edit'])) :
                            $edit_route         =   route($this->shared_data->edit_route, $record->id);
                            $row                .=  "<a href='$edit_route' target='_balnk' class='btn btn-info btn-sm'><i class='fa fa-edit'></i></a>";
                        endif;

                        if (request()->user()->can('permissions', [$this->permission_key, 'delete'])) :
                            $destroy_route      =   route($this->shared_data->destroy_route, $record->id);
                            $row                .=  "<form action='$destroy_route' method='post' class='d-inline'>
                                                    <input type='hidden' name='_token' value='" . csrf_token() . "' autocomplete='off'>
                                                    <input type='hidden' name='_method' value='DELETE'>
                                                    <button type='button' class='btn btn-danger btn-sm ml-2' onclick='deleteRecord(this)'><i class='fa fa-trash'></i></button>
                                                </form>";

                        endif;
                    endif;
                    return $row;
                })
                ->rawColumns(['otp_view', 'name', 'refer_by', 'withdrawal_status_view', 'status_view', 'action'])
                ->toJson();
        endif;

        return view()->exists($this->index_view) ? view($this->index_view) : abort(404);
    }
    # Index

    # Create
    public function create()
    {
        $this->authorize('permissions', [$this->permission_key, 'create']);

        $sponsorUsers = $this->currentSponsorUsers(null, old('sponsor_id'));

        return view()->exists($this->create_or_edit_view)
            ? view($this->create_or_edit_view, compact('sponsorUsers'))
            : abort(404);
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
        $data                    =  [];
        $id                      =  request()->id;
        $uid                     =  request()->uid ? request()->uid : generate_uid();

        $profile                 =   uploadFile('profile', "profile/$uid/");
        $kyc_document_front      =   uploadFile('kyc_document_front', "kyc_document_front/$uid/");
        $kyc_document_back       =   uploadFile('kyc_document_back', "kyc_document_back/$uid/");

        # Data
        $data = [
            'uid'                   => $uid,
            'name'                  => request()->name,
            'mobile'                => request()->mobile,
            'email'                 => request()->email,
            'profile'               => $profile,
            'dob'                   => request()->dob,
            'state_id'              => request()->state_id,
            'document_type_id'      => request()->document_type_id,
            'document_id'           => request()->document_id,
            'refer_income'          => request()->refer_income,
            'kyc_document_front'    => $kyc_document_front,
            'kyc_document_back'     => $kyc_document_back,
            'refer_by'              => ((int) request()->sponsor_id === (int) request()->id) ? 0 : (int) request()->sponsor_id,
            'remark'                => request()->remark,
            'kyc_status'            => request()->kyc_status,
            'withdrawal_status'     => request()->withdrawal_status,
            'status'                => request()->status,
            'is_cashier'            => request()->is_cashier ? 1 : 0,
        ];

        if (!$id):
            $data['refer_code']              =   generate_alpa_numeric_code(7);
        endif;
        # End Data

        $record = $this->eloquentModel()->updateOrCreate(
            [
                'id'                    => request()->id
            ],
            $data
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

        return back()->with('back_msg', $back_msg);
        // return redirect()->route($this->index_route)->with('back_msg', $back_msg);
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

        $record = $this->eloquentModel()
            ->withSum('deposit', 'amount')
            ->withSum('withdrawal', 'amount')
            ->withSum('refer_commissions', 'refer_commission_amount')
            ->with([
                'refer_by_user:refer_by,id,uid',
                'deposit' => function ($query) {
                    $query->latest()->limit(50);
                },
                'withdrawal' => function ($query) {
                    $query->latest()->limit(50);
                },
            ])
            ->findOrFail($id);

        $record->makeVisible([
            'kyc_status_label',
            'kyc_status_view',
            'kyc_document_front_url',
            'kyc_document_back_url',
            'status_label',
            'status_view',
        ]);

        $wallet_history = Wallet::whereUserId($id)->latest('updated_at')->limit(150)->get();

        $game_challenges = GameChallenge::where(function ($query) use ($id) {
            $query->where('challenger_id', $id);
            $query->orWhere('opponent_id', $id);
        })->latest()->limit(50)->get();

        $record->game_challenges = $game_challenges;

        $sponsorUsers = $this->currentSponsorUsers((int) $id, old('sponsor_id', $record->refer_by));

        $referralCommissionByUser = CommissionHistory::query()
            ->whereReferBy($id)
            ->selectRaw('user_id, SUM(refer_commission_amount) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $directReferralIds = User::withoutGlobalScope('verified_mobile')
            ->whereReferBy($id)
            ->limit(200)
            ->pluck('id')
            ->all();

        $referralUserIds = array_values(array_unique(array_merge(
            $directReferralIds,
            $referralCommissionByUser->keys()->all()
        )));

        $referral_users = collect();
        if ($referralUserIds !== []) {
            $referral_users = User::withoutGlobalScope('verified_mobile')
                ->select('id', 'uid', 'name', 'email', 'mobile', 'refer_by', 'win_wallet_amount', 'game_wallet_amount', 'status', 'created_at')
                ->whereIn('id', array_slice($referralUserIds, 0, 200))
                ->get();
        }

        return view()->exists($this->create_or_edit_view)
            ? view($this->create_or_edit_view, compact(
                'record',
                'wallet_history',
                'sponsorUsers',
                'referral_users',
                'referralCommissionByUser'
            ))
            : abort(404);
    }

    public function update(Request $request, $id) {}

    public function destroy($id)
    {
        $this->authorize('permissions', [$this->permission_key, 'delete']);
        $record     =   $this->eloquentModel()->find($id);
        $record->delete();

        return back()->with('back_msg', "<div class='alert alert-success'>Record deleted successfully</div>");
    }

    /**
     * AJAX Select2 search for the sponsor (refer_by) dropdown.
     * Previously only the first 500 names were rendered, so most users never appeared.
     */
    public function searchSponsors(Request $request)
    {
        $this->authorize('permissions', [$this->permission_key, 'view']);

        $raw = trim((string) ($request->input('q', $request->input('term', ''))));
        $excludeId = (int) $request->input('exclude_id', 0);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;

        $needle = mb_strtolower($raw, 'UTF-8');
        $like = '%'.addcslashes($needle, '%_\\').'%';

        $query = DB::table('users')
            ->select('id', 'uid', 'name', 'mobile', 'email')
            ->when($excludeId > 0, fn ($q) => $q->where('id', '!=', $excludeId));

        if ($needle !== '') {
            $query->where(function ($q) use ($like, $raw) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(uid) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(mobile, ?)) LIKE ?', ['', $like])
                    ->orWhereRaw('LOWER(IFNULL(email, ?)) LIKE ?', ['', $like]);

                if (ctype_digit($raw)) {
                    $q->orWhere('id', (int) $raw);
                }
            });
            $query->orderByRaw('LOWER(name) = ? DESC', [$needle]);
        }

        $users = $query->orderBy('name')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'results' => collect($users->items())->map(fn ($user) => [
                'id' => (string) $user->id,
                'text' => trim((string) $user->name).' (UID: '.$user->uid.')',
            ])->values(),
            'pagination' => [
                'more' => $users->hasMorePages(),
            ],
        ]);
    }

    /**
     * Only the currently selected sponsor is preloaded; the rest are fetched via searchSponsors().
     */
    private function currentSponsorUsers(?int $excludeUserId, mixed $sponsorId): \Illuminate\Support\Collection
    {
        $sponsorId = (int) $sponsorId;
        if ($sponsorId <= 0 || ($excludeUserId && $sponsorId === $excludeUserId)) {
            return collect();
        }

        return User::query()
            ->withoutGlobalScope('verified_mobile')
            ->select('id', 'uid', 'name')
            ->whereKey($sponsorId)
            ->get();
    }

    /**
     * Registration date for dashboard links (?filter=today&date=YYYY-MM-DD) and KYC filters with ?date=.
     */
    private function resolveUserRegistrationDateFilter(): ?string
    {
        $date = request()->date;

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        if (request()->filter === 'today') {
            return carbon()->today()->toDateString();
        }

        return null;
    }
}
