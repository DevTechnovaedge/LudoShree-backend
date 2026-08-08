<?php

namespace App\Http\Controllers\Admin\Site\Media;

use App\Http\Controllers\Controller;
use App\Models\Admin\Site\Media\Category;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CategoryController extends Controller
{
    # Global 
    public $index_view                  =   "admin.site.media.category.index";
    public $create_or_edit_view         =   "admin.site.media.category.single";

    public $index_route                 =   "admin::site-media-categories.index";

    public $permission_key              =   "site_media_categories";
    public $table_name                  =   "site_media_category";
    public $folder_name                 =   "site";

    # Consturct

    protected $shared_data;

    public function __construct()
    {
        $this   ->shared_data          =   (object) [
            'page_title'        => "Media Category",
            'index_route'       => $this->index_route,
            'create_route'      => "admin::site-media-categories.create",
            'edit_route'        => "admin::site-media-categories.edit",
            'store_route'       => "admin::site-media-categories.store",
            'destroy_route'     => "admin::site-media-categories.destroy",
            'permission_key'    => $this->permission_key,
        ];
        view()->share('shared_data', $this->shared_data);
    }
    # End Consturct

    # ==> Model 
    public function eloquentModel()
    {
        return  new Category();
    }
    # ==> !Model 

    # End Global 

    # Index
    public function index()
    {

        $this->authorize('permissions', [$this->permission_key, 'view']);

        if (request()->ajax()) :
            $data       =  $this->eloquentModel()->latest()->withoutGlobalScope('active');

            $data       =   $data->get()->makeVisible(['status_view']);

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function ($record) {
                    $row                    =   "";

                           $edit_route         =   route($this->shared_data->edit_route, $record->id);
                        $row                .=  "<a href='$edit_route' class='btn btn-info btn-sm'><i class='fa fa-edit'></i></a>";

                        $destroy_route      =   route($this->shared_data->destroy_route, $record->id);
                        $row                .=  "<form action='$destroy_route' method='post' class='d-inline'>
                                                    <input type='hidden' name='_token' value='".csrf_token()."' autocomplete='off'>
                                                    <input type='hidden' name='_method' value='DELETE'>
                                                    <button type='button' class='btn btn-danger btn-sm ml-2' onclick='deleteRecord(this)'><i class='fa fa-trash'></i></button>
                                                </form>";

                    return $row;
                })
                ->rawColumns(['status_view', 'action'])
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
            'title'                      =>  "required|max:225|unique:$this->table_name,title," . request()->id,
            'status'                    =>  "required|in:0,1",
        ]);
    }
    # End Validation : Step 1

    # Custom Create Or Update : Step 2
    public function updateOrCreate()
    {
        
        $record = $this->eloquentModel()->updateOrCreate(
            [
                'id'                    => request()->id
            ],
            [
                'title'                  => request()->title,
                'status'                => request()->status
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
