<?php

namespace App\Http\Controllers\Admin\GameChallenge;

use App\Http\Controllers\Controller;
use App\Models\Financial\TransferCashback;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Transaction;
use App\Models\GameChallenge\Wallet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    # Global 
    public $index_view                  =   "admin.pages.wallet.index";
    public $create_or_edit_view         =   "admin.pages.wallet.single";

    public $index_route                 =   "admin::wallet.index";

    public $permission_key              =   "wallet_transactions";
    public $table_name                  =   "wallet-transactions";
    public $folder_name                 =   "wallet-transactions";

    # Consturct

    protected $shared_data;

    public function __construct()
    {
        $this->shared_data          =   (object) [
            'page_title'        => "Wallet",
            'index_route'       => $this->index_route,
            'create_route'      => "admin::wallet-transactions.create",
            'edit_route'        => "admin::wallet-transactions.edit",
            'store_route'       => "admin::wallet-transactions.store",
            'destroy_route'     => "admin::wallet-transactions.destroy",
            'permission_key'    => $this->permission_key,
        ];
        view()->share('shared_data', $this->shared_data);
    }
    # End Consturct

    # ==> Model 
    public function eloquentModel()
    {
        return  new Wallet();
    }
    # ==> !Model 

    # End Global 

    public $total_balance   = 0;
    # Index
    public function index()
    {
        $slug = request()->segment(2);

        $this->authorize('permissions', [$this->permission_key, 'view']);

        if (request()->ajax()) :

            $data       =  $this->eloquentModel()->with('user')->latest('id');

            if (request()->date):
                $data->whereDate('created_at', request()->date);
            endif;

            #
            $slug = request()->segment(2);
            switch ($slug):
                case 'game-credit-and-debit':
                    // $data->whereWalletType('game')->where('added_by', '>', 0);
                    $data->whereWalletType('game')->where('added_by', '!=', 0);
                    break;

                case 'win-credit-and-debit':
                    $data->whereWalletType('win')->where('added_by', '!=', 0);
                    break;

                case 'game-ledger':

                    break;
            endswitch;
            #
            // $data = $data->get()->makeVisible(['status_view']);
            // return $data->toRawSql();
            return datatables()->of($data)
                ->addIndexColumn()
                ->filterColumn('remark', function($query, $keyword) {
                    $query->where('remark', 'like', "%" . $keyword . "%");
                })
                ->addColumn('user_details', function ($row) {
                    $uid = $row->user->uid ?? '';
                    $id = $row->user->id ?? '';
                    $name = $row->user->name ?? '';
                    $mobile = $row->user->mobile ?? '';

                    $edit_route             =  url('admin/users/' . $id . '/edit');
                    $user_details           =      "<div class='py-1'>$name <small class='py-1'>( $mobile )</small></div>";
                    $user_details           .=  "<div><small>( UID : <a href='$edit_route' target='_balnk'>$uid</a> )</small></div>";
                    return $user_details;
                })
                ->addColumn('win_wallet', function ($row) {
                    if ($row->wallet_type == 'win'):
                        return  "<span class='bg-secondary p-1'> $row->amount</span>";
                    endif;
                })
                ->addColumn('game_wallet', function ($row) {
                    if ($row->wallet_type == 'game'):
                        return  "<span class='bg-secondary p-1'> $row->amount</span>";
                    endif;
                })
                ->addColumn('remark', function ($row) {
                    return $row->remark ?? $row->payment_info ?? '';
                })
                ->addColumn('total_balance', function ($row) {
                    return $row->total_balance ?? '';
                })
                ->addColumn('total_amount', function ($row) {
                    $type = $row->type ? $row->type : $row->transfer_type;
                    
                    $status     =  'bg-warning';
                    
                    if( $row->status == 1 ):
                        $status     =  'bg-success';
                    endif;
                    
                    if( $row->status == 2 ):
                        $status     =  'bg-danger';
                    endif;


                    switch ($type):
                        case 'credit':
                            return "<span class='$status p-1'>+ $row->amount</span>";
                            break;
                        case 'debit':
                            return "<span class='bg-danger p-1'>- $row->amount</span>";
                            break;
                    endswitch;
                    return 0;
                })

                ->rawColumns(['user_details', 'win_wallet', 'game_wallet', 'total_amount', 'total_balance'])
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

    # 
    public function wallet_transaction_process()
    {
        $arr                        =   [];
        $amount                     =   request()->amount;
        $type                       =   request()->type;
        $remark                     =   request()->remark;
        $user_id                    =   request()->user_id;
        $wallet_type                =   request()->wallet_type;

        $user                       =   User::find($user_id);

        if(!$user):
            return response()->json(['status' => false, 'message' => 'User not found']);
        endif;

        # Balance and ledger row are written together, and the balance is moved
        # with a single statement so a concurrent game refund or payout is kept.
        $wallet_service             =   app(\App\Services\WalletService::class);

        $ledger                     =   [
            'type'              => $type,
            'remark'            => $remark,
            'status'            => 1,
            'added_by'          => auth('admin')->user()->id,
        ];

        # requireFunds is off so an admin correction can still be recorded when
        # the balance is already short.
        $balances                   =   $type == 'debit'
            ? $wallet_service->debit((int) $user_id, $wallet_type, (float) $amount, $ledger, false, false)
            : $wallet_service->credit((int) $user_id, $wallet_type, (float) $amount, $ledger);

        if ($balances):
            $arr                =   ['status' => true, 'message' => 'Successfully transaction added'];
        else:
            $arr                =   ['status' => false, 'message' => 'User not found'];
        endif;

        return response()->json($arr);
    }
    #

    /**
     * Win→Game cashback history (linked from dashboard cards).
     */
    public function win_to_game_cashbacks()
    {
        $this->authorize('permissions', ['win_to_game_cashbacks', 'view']);

        if (request()->ajax()) :
            $data = TransferCashback::query()->with('user')->latest('id');

            if (request()->date) :
                $data->whereDate('created_at', request()->date);
            endif;

            return datatables()->of($data)
                ->addIndexColumn()
                ->filterColumn('user.uid', function ($query, $keyword) {
                    $query->whereHas('user', function ($sub) use ($keyword) {
                        $sub->withoutGlobalScopes()
                            ->where('uid', 'like', '%'.$keyword.'%')
                            ->orWhere('mobile', 'like', '%'.$keyword.'%')
                            ->orWhere('name', 'like', '%'.$keyword.'%');
                    });
                })
                ->addColumn('user.user_details', function ($row) {
                    return optional($row->user)->user_details ?? '-';
                })
                ->make(true);
        endif;

        return view()->exists('admin.pages.wallet.win-to-game-cashbacks')
            ? view('admin.pages.wallet.win-to-game-cashbacks')
            : abort(404);
    }
}
