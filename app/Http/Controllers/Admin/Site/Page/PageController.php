<?php

namespace App\Http\Controllers\Admin\Site\Page;

use App\Http\Controllers\Controller;
use App\Models\Admin\Site\Page\Page;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class PageController extends Controller
{
    # Global 
    public $index_view                  =   "admin.site.pages.index";
    public $create_or_edit_view         =   "admin.site.pages.single";

    public $index_route                 =   "admin::pages.index";

    public $permission_key              =   "pages";
    public $table_name                  =   "pages";
    public $folder_name                 =   "pages";

    # Consturct

    protected $shared_data;

    public function __construct()
    {
        $this->shared_data          =   (object) [
            'page_title'        => "Page",
            'index_route'       => $this->index_route,
            'create_route'      => "admin::pages.create",
            'edit_route'        => "admin::pages.edit",
            'store_route'       => "admin::pages.store",
            'destroy_route'     => "admin::pages.destroy",
            'permission_key'    => $this->permission_key,
        ];
        view()->share('shared_data', $this->shared_data);
    }
    # End Consturct

    # ==> Model 
    public function eloquentModel()
    {
        return  new Page();
    }
    # ==> !Model 

    # End Global 

    # Index
    public function index()
    {

        $this->authorize('permissions', [$this->permission_key, 'view']);

        if (request()->ajax()) :
            $data       =  $this->eloquentModel()->latest()->get();

            return DataTables::of($data)
                ->addIndexColumn()

                ->addColumn('action', function ($record) {
                    $row                    =   "";

                    $page_url            = url($record->slug);
                    $row                .=  "<a href='$page_url' class='btn btn-info btn-sm mr-3' target='_blank'><i class='fa fa-eye'></i></a>";

                    $edit_route         =   route($this->shared_data->edit_route, $record->id);
                    $row                .=  "<a href='$edit_route' class='btn btn-success btn-sm'><i class='fa fa-edit'></i></a>";

                    $destroy_route      =   route($this->shared_data->destroy_route, $record->id);
                    $row                .=  "<form action='$destroy_route' method='post' class='d-inline'>
                                                    <input type='hidden' name='_token' value='" . csrf_token() . "' autocomplete='off'>
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
            'title'                         =>  "required|max:225|unique:$this->table_name,title," . request()->id,
            'slug'                         =>  "required|max:225|unique:$this->table_name,slug," . request()->id,
            'content'                        =>  "required",
            'status'                        =>  "required|in:0,1",
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
                'title'                 => request()->title,
                'slug'                 => request()->slug,
                'content'               =>  request()->content,
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
