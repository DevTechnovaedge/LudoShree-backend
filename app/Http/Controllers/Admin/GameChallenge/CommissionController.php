<?php

namespace App\Http\Controllers\Admin\GameChallenge;

use App\Http\Controllers\Controller;
use App\Models\GameChallenge\CommissionHistory;
use App\Models\GameChallenge\GameCommissionSlot;
use App\Models\User;

class CommissionController extends Controller
{
    # Global 
    public $index_view                  =   "admin.pages.commission-history.index";
    public $create_or_edit_view         =   "admin.pages.commission-history.single";

    public $index_route                 =   "admin::commission-history.index";

    public $permission_key              =   "";
    public $table_name                  =   "game_challenges";
    public $folder_name                 =   "game-challenges";
    public $page_title                  =   "Game Challenge";
    public $slug                        =   "";

    # Consturct

    protected $shared_data;

    public function __construct()
    {
   #        
            $this->slug  = request()->segment(2);
             switch ($this->slug):
                case 'refer-commissions':
                    $this->permission_key   =  'refer_commissions';
                    $this->page_title       =  'Refer Commissions';
                    break;
                case 'game-commissions':
                    $this->permission_key   =  'game_commissions';
                    $this->page_title       =  'Game Commissions';
                    break;
                case 'user-commissions':
                    $this->permission_key   =  'user_commissions';
                    $this->page_title       =  'User Commissions';
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
        return  new CommissionHistory();
    }
    # ==> !Model 

    # End Global 

    # Index
    public function index()
    {
        $this->authorize('permissions', [$this->permission_key, 'view']);

        if (request()->ajax()) :
            $data       =  $this->eloquentModel()->latest();

            if(request()->date):
                $data->whereDate('created_at', request()->date);
            endif;
            
            if($this->slug == 'refer-commissions'):
                $data->where('refer_by', '!=', 0);
            endif;
            
            $data       =   $data->with('refer_by_user');

            return datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('user_details', function ($row) {
                    $uid = $row->user->uid ?? 0;
                    $id = $row->user->id ?? 0;
                    $name = $row->user->name ?? 0;
                    
                    $edit_route             =  url('admin/users/'.$id.'/edit');
                    $user_details    =      "<span class='py-1'>".$name."</span>";
                    $user_details            .=  " <small>( UID : <a href='$edit_route' target='_balnk'>$uid</a> )</small>";
                    return $user_details;
                })

                ->addColumn('commissions', function ($row) {

                    switch ($this->slug):
                        case 'refer-commissions':
                            return $row->refer_commission;
                            break;
                        case 'game-commissions':
                            return $row->game_commission;
                            break;
                    endswitch;

                    return 0 ;
                })
                ->addColumn('amount', function ($row) {
                    switch ($this->slug):
                        case 'refer-commissions':
                            return $row->refer_commission_amount;
                            break;
                        case 'game-commissions':
                            return $row->final_game_commission;
                            break;
                    endswitch;
                    return 0 ;
                })
                ->rawColumns(['user_details', 'amount'])
                ->make(true);
        endif;

        return view()->exists($this->index_view) ? view($this->index_view) : abort(404);
    }
    # Index

    # Index
    public function user_commissions_list()
    {
       if (request()->ajax()) :
            $data       =  User::select('id', 'name', 'uid', 'commission', 'updated_at')->where('commission', '!=', 0)->latest();

            $data       =   $data->get();
            
            if($data):
                $data->makeHidden(['game_lose_count', 'game_win_count', '']);
                $data->makeVisible(['updated_at']);
            endif;

            return datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('commission', function ($row) {
                    return $row->commission." %";
                })
                ->rawColumns(['user_details'])
                ->make(true);
        endif;
    }
    # Index

    # User Commissions
    public function user_commissions(){
        $user_commissions       =   User::select('id', 'name', 'uid', 'commission', 'updated_at')->get();

        if($user_commissions):
            $user_commissions->makeVisible(['updated_at']);
        endif;

        return view('admin.pages.commission-history.user-commissions',  compact('user_commissions'));
    }
    # End User Commissions

    # Game Commissions Slot
    public function game_commission_slot(){
        $record         =    GameCommissionSlot::findOrFail(1);
        return view('admin.pages.commission-history.game-commission-slot', compact('record'));
    }
    # End Game Commission Slot

    # fetch_user_details
    public function fetch_user_details(){
        $arr            =   [];
        $uid            =   request()->user_uid;

        $user           =   User::select('id', 'uid', 'name', 'game_wallet_amount', 'win_wallet_amount', 'commission')->whereUid($uid)->orWhere('mobile', $uid)->first();

        if($user):
            $user->makeHidden(['game_win_count', 'game_lose_count', 'user_details']);
            $arr        =   ['status' => true, 'message' => 'Successfully data fetched', 'user' => $user ];
        else:
            $arr        =   ['status' => false, 'message' => 'Data not found'];
        endif;

        return response()->json($arr);
    }
    # End fetch_user_details

    # update_user_commission
    public function update_user_commission(){
        $arr                    =   [];               
        $uid                    =   request()->uid;
        $commission             =   request()->commission;

        $user                   =   User::whereUid($uid)->orWhere('mobile', $uid)->first();

        if($user):
            $user->commission   =   $commission;
            $user->save();

            $arr                =   [ 'status' => true, 'message' => 'Successfully commission updated'];
        else:
            $arr                =   [ 'status' => false, 'message' => 'User not found'];
        endif;

        return response()->json($arr);
    }
    # End update_user_commission

    # Update Game Commission Slot
    public function update_game_commission_slot(){

        request()->validate([
            'slot_1_to_99'                          =>  "required|numeric|gte:0|lte:100",
            'slab_100_to_499'                       =>  "required|numeric|gte:0|lte:100",
            'slab_500_to_above'                     =>  "required|numeric|gte:0|lte:100",
            'refer_commission'                      =>  "required|numeric|gte:0|lte:100",
         ],[
            'slot_1_to_99.gte'                 =>  "The field value must be between 0 to 100",
            'slab_100_to_499.gte'              =>  "The field value must be between 0 to 100",
            'slab_500_to_above.gte'            =>  "The field value must be between 0 to 100",
            'refer_commission.gte'             =>  "The field value must be between 0 to 100",

            'slot_1_to_99.lte'                 =>  "The field value must be between 0 to 100",
            'slab_100_to_499.lte'              =>  "The field value must be between 0 to 100",
            'slab_500_to_above.lte'            =>  "The field value must be between 0 to 100",
            'refer_commission.lte'             =>  "The field value must be between 0 to 100",
         ]
        );

        $result             =   GameCommissionSlot::updateOrCreate(
                                                [
                                                    'id'                    => request()->id
                                                ],
                                                [
                                                    'slot_1_to_99'          => request()->slot_1_to_99,
                                                    'slab_100_to_499'       => request()->slab_100_to_499,
                                                    'slab_500_to_above'     => request()->slab_500_to_above,
                                                    'refer_commission'      => request()->refer_commission,
                                                ]
                                            );
        if ($result) :
            $back_msg                            =   "<div class='alert alert-success'>Record udpated successfully </div>";
        else :
            $back_msg                            =   "<div class='alert alert-danger'>Some error occured</div>";
        endif;

        return back()->with('back_msg', $back_msg);
    }
    # End Update Game Commission Slot
}
