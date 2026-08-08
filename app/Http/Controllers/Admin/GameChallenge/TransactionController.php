<?php

namespace App\Http\Controllers\Admin\GameChallenge;

use App\Http\Controllers\Controller;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Transaction;
use App\Services\AdminDashboardStatsService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    # Global 
    public $index_view                  =   "admin.pages.transactions.index";
    public $create_or_edit_view         =   "admin.pages.transactions.single";

    public $index_route                 =   "admin::transactions.index";

    public $permission_key              =   "transactions";
    public $table_name                  =   "transactions";
    public $folder_name                 =   "transactions";

    # Consturct

    protected $shared_data;

    public function __construct()
    {
        $this->shared_data          =   (object) [
            'page_title'        => "Transaction",
            'index_route'       => $this->index_route,
            'create_route'      => "admin::transactions.create",
            'edit_route'        => "admin::transactions.edit",
            'store_route'       => "admin::transactions.store",
            'destroy_route'     => "admin::transactions.destroy",
            'permission_key'    => $this->permission_key,
        ];
        view()->share('shared_data', $this->shared_data);
    }
    # End Consturct

    # ==> Model 
    public function eloquentModel()
    {
        return  new Transaction();
    }
    # ==> !Model 

    # End Global 

    # Index
    public function index()
    {
        $this->authorize('permissions', [$this->permission_key, 'view']);

        if (request()->ajax()) :
            $stats = app(AdminDashboardStatsService::class);
            $data       =  $this->eloquentModel()->with('wallet')->latest();

            #
            switch (request()->filter):
                case 'pending-withdrawals':
                    $data       = $data->whereTransferType('withdrawal')->whereStatus(0);
                    if (request()->date) {
                        $data->whereDate('created_at', request()->date);
                    }
                    break;
                    case 'withdrawals':
                        if (request()->date) {
                            $data = $stats->successfulWithdrawalsQuery(request()->date)
                                ->with('wallet')
                                ->latest();
                        } else {
                            $data = $data->whereTransferType('withdrawal')->where('status', '!=', 0);
                        }
                        break;
                    case 'deposit-requests':
                        $data       = $data->whereTransferType('deposit')->whereStatus(0);
                        if (request()->date) {
                            $data->whereDate('created_at', request()->date);
                        }
                        break;
                    case 'deposits':
                        if (request()->date) {
                            $data = $stats->successfulDepositsQuery(request()->date)
                                ->with('wallet')
                                ->latest();
                        } else {
                            $data = $data->whereTransferType('deposit')->where('status', '!=', 0);
                        }
                    break;
                default:
                    if (request()->date) {
                        $data->whereDate('created_at', request()->date);
                    }
                    break;
            endswitch;
            #

            $data       =   $data->get()->makeVisible(['status_view']);

            return datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('user_details', function ($row) {
                    $uid = $row->user->uid;
                    $edit_route             =  url('admin/users/'.$row->user->id.'/edit');
                    $user_details    =      "<span class='py-1'>".$row->user->name."</span>";
                    $user_details            .=  " <small>( UID : <a href='$edit_route' target='_balnk'>$uid</a> )</small>";
                    return $user_details;
                })
        
                ->addColumn('payment_info', function ($row) {

                    if(!$row->deposit_screenshot):
                        return $row->payment_info;
                    endif;

                    if(request()->filter == 'deposit-requests' || request()->filter == 'deposits'):
                        return $row->deposit_screenshot ? "<a href='$row->deposit_screenshot_url' target='_blank'>View</a>" : '';
                    endif;

                    return '';

                })

                ->addColumn('amount', function ($row) {
                    if($row->transfer_type == 'withdrawal'):
                        $win_wallet_amount          =   $row->user->win_wallet_amount;
                        $details          =   "<div>₹ $row->amount<div>
                                                <div> <small class='text-success font-italic'>Win Wallet Amount : ₹ $win_wallet_amount </small> </div>";
                    else:
                        $game_wallet_amount          =   $row->user->game_wallet_amount;
                        $details          =   "<div>₹ $row->amount<div>
                                                <div> <small class='text-success font-italic'>Game Wallet Amount : ₹ $game_wallet_amount </small> </div>";
                    endif;
                    return $details;
                })
                ->addColumn('status_view', function ($row) {
                    $status_action_view         =   '';

                    # Withdrawal
                    if($row->transfer_type == 'withdrawal' && $row->status == 0):
                        $win_wallet_amount          =   $row->user->win_wallet_amount;
                        
                        $disabled                   =   '';
                        if($row->amount > $win_wallet_amount):
                            $disabled               =   '';
                        endif;

                        // $transaction_id             =   $row->wallet->transaction_id;
                        $transaction_id = ($row->wallet) ? $row->wallet->transaction_id : null;
                        
                        $status_action_view         =   "<button class='btn btn-success btn-sm transaction-action-btn' data-type='withdrawal' data-action='approve' data-transaction-id='$transaction_id' data-id='$row->id' $disabled>Approved</button>
                                                        <button class='btn btn-danger btn-sm transaction-action-btn' data-type='withdrawal' data-action='reject' data-transaction-id='$transaction_id' data-id='$row->id'>Rejected</button>";
                    endif;
                    # End Withdrawal
                    
                    # Deposit
                    if($row->transfer_type == 'deposit' && $row->status == 0):
                        $status_action_view         =   "<button class='btn btn-success btn-sm transaction-action-btn' data-type='deposit' data-action='approve' data-id='$row->id'>Approved</button>
                                                        <button class='btn btn-danger btn-sm transaction-action-btn' data-type='deposit' data-action='reject' data-id='$row->id'>Rejected</button>";
                    endif;
                    # End Deposit

                    $remark   = "";
                    if($row->status == 3 && $row->remark):
                        $remark           .= "
                                                <details class='p-3 my-2'>
                                                <summary> Remark </summary>
                                                    <div>$row->remark</div>
                                                </details>
                                                        ";
                    endif;

                    return $status_action_view == '' ? $row->status_view.$remark : $status_action_view;
                })
                ->rawColumns(['user_details', 'payment_info', 'amount', 'status_view', 'action'])
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

    public function update(Request $request, $id)
    {
    }

    public function destroy($id)
    {
        $this->authorize('permissions', [$this->permission_key, 'delete']);
        $record     =   $this->eloquentModel()->find($id);
        $record->delete();

        return back()->with('back_msg', "<div class='alert alert-success'>Record deleted successfully</div>");
    }
}
