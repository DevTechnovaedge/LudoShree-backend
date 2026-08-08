<?php

namespace App\Http\Controllers\Admin\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    # Global 
    public $index_view                  =   "admin.pages.notifications.notification.index";
    public $create_or_edit_view         =   "admin.pages.notifications.notification.single";

    public $index_route                 =   "admin::notifications.index";

    public $permission_key              =   "notifications";
    public $table_name                  =   "notifications";
    public $folder_name                 =   "notifications";

    # Consturct

    protected $shared_data;

    public function __construct()
    {
        $this->shared_data          =   (object) [
            'page_title'        => "Notification",
            'index_route'       => $this->index_route,
            'create_route'      => "admin::notifications.create",
            'edit_route'        => "admin::notifications.edit",
            'store_route'       => "admin::notifications.store",
            'destroy_route'     => "admin::notifications.destroy",
            'permission_key'    => $this->permission_key,
        ];
        view()->share('shared_data', $this->shared_data);
    }
    # End Consturct

    # ==> Model 
    public function eloquentModel()
    {
        return  new Notification();
    }
    # ==> !Model 

    # End Global 

    # Index
    public function index()
    {

        $this->authorize('permissions', [$this->permission_key, 'view']);

        $records       =  $this->eloquentModel()->whereIn('sent_type', ['instant', 'schedule', 'regular'])->with('category')->latest()->withoutGlobalScope('active')->get();


        return view()->exists($this->index_view) ? view($this->index_view, compact('records')) : abort(404);
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
            'title'                     =>  "required|max:225," . request()->id,
            'content'                   =>  "required",
            'sent_type'                 =>  "required|in:instant,schedule,regular",
            'schedule_date_time'        =>  "required_if:sent_type,==,schedule",
            'regular_time'              =>  "required_if:sent_type,==,regular",
            'user_ids'                  =>  "required",
        ]);
    }
    # End Validation : Step 1

    # Custom Create Or Update : Step 2
    public function updateOrCreate()
    {
        $title = request()->title;
        $content = request()->content;

        $sent_type   = request()->sent_type;

        $record = $this->eloquentModel()->create(
            [
                'title'                 => $title,
                'content'               => $content,
                'sent_type'             => request()->sent_type,
                'schedule_date_time'    => request()->schedule_date_time,
                'regular_time'          => request()->regular_time,
                'user_ids'              => implode(',', request()->user_ids),
            ]
        );

        if(request()->sent_type == 'instant'):
            
            if(request()->user_ids && !in_array('all', request()->user_ids)):
                if(in_array(0, request()->user_ids)){ # For all
                    $data  =  (object) 
                    [
                        'title'                 => $title,
                        'body'                  => $content,
                        'notification_type'     => $sent_type,
                        'topic'     =>  'all',
                    ];

                    fcm()->send($data);
                    $record->is_sent = 1;
                    $record->save();
                }

                if (!in_array(0, request()->user_ids)) {
                    $users = User::select('id', 'fcm_device_token');
                    $users->whereIn('id',  request()->user_ids);
                    $users  =    $users->get();
                    $fcm_device_tokens  = data_get($users, '*.fcm_device_token');
                    $is_sent           = false;
                
                    foreach($fcm_device_tokens ?? [] as $fcm_device_token):
                        $data  =  (object) 
                                            [
                                                'title'                 => $title,
                                                'body'                  => $content,
                                                'notification_type'     => $sent_type,
                                                'fcm_device_token'     =>  $fcm_device_token,
                    
                                            ];
                        
                            $is_sent = ( fcm()->send($data)->name ?? 0 ) ? 1 : 0;
                        endforeach;
                        
                        // if($is_sent ?? 0):
                            $record->is_sent = 1;
                            $record->save();
                        // endif;
                }

                endif;
        endif;

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
                "Record added successfully"
                :
                "Record udpated successfully";
        else :
            $back_msg                            =   "Some error occured";
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
