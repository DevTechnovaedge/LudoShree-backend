<?php

namespace App\Http\Controllers\Admin\Site\Media;

use App\Http\Controllers\Controller;
use App\Models\Admin\Site\Media\Category;
use App\Models\Admin\Site\Media\Media;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class MediaController extends Controller
{
    # Global 
    public $index_view                  =   "admin.site.media.media.index";
    public $create_or_edit_view         =   "admin.site.media.media.single";

    public $index_route                 =   "admin::site-media.index";

    public $permission_key              =   "site_media";
    public $table_name                  =   "site_media";
    public $folder_name                 =   "site";

    # Consturct

    protected $shared_data;

    public function __construct()
    {
        $this->shared_data          =   (object) [
            'page_title'        => "Media",
            'index_route'       => $this->index_route,
            'create_route'      => "admin::site-media.create",
            'edit_route'        => "admin::site-media.edit",
            'store_route'       => "admin::site-media.store",
            'destroy_route'     => "admin::site-media.destroy",
            'permission_key'    => $this->permission_key,
        ];
        view()->share('shared_data', $this->shared_data);
    }
    # End Consturct

    # ==> Model 
    public function eloquentModel()
    {
        return  new Media();
    }
    # ==> !Model 

    # End Global 

    # Index
    public function index()
    {

        $this->authorize('permissions', [$this->permission_key, 'view']);

        if (request()->ajax()) :
            $data       =  $this->eloquentModel()->with('category')->latest()->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('generated_link', function($record){
                    return "<div class='copy-text-container'><span class='copy-text pr-3'>$record->generated_link</span><i class='fa fa-copy copy-btn text-primary'></i></div>";
                })
                ->addColumn('action', function ($record) {
                    $row                    =   "";

                    $row                .=  "<a href='$record->generated_link' class='btn btn-info btn-sm mr-3' target='_blank'><i class='fa fa-eye'></i></a>";

                    $edit_route         =   route($this->shared_data->edit_route, $record->id);
                    $row                .=  "<a href='$edit_route' class='btn btn-success btn-sm'><i class='fa fa-edit'></i></a>";

                    $destroy_route      =   route($this->shared_data->destroy_route, $record->id);
                    $row                .=  "<form action='$destroy_route' method='post' class='d-inline'>
                                                    <input type='hidden' name='_token' value='".csrf_token()."' autocomplete='off'>
                                                    <input type='hidden' name='_method' value='DELETE'>
                                                    <button type='button' class='btn btn-danger btn-sm ml-2' onclick='deleteRecord(this)'><i class='fa fa-trash'></i></button>
                                                </form>";

                    return $row;
                })
                ->rawColumns(['generated_link', 'status_view', 'action'])
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
            'category_id'                   =>  "required",
            'title'                         =>  "required|max:225|unique:$this->table_name,title," . request()->id,
            'media_file'                    =>  "file|max:20480",
            'status'                        =>  "required|in:0,1",
        ]);
    }
    # End Validation : Step 1

    # Custom Create Or Update : Step 2
    public function updateOrCreate()
    {
        
        # Folder Name ( Category )
        $category            =   Category::active()->find(request()->category_id);

        $dynamic_folder_name    = str()->slug($category->title);

        # End Folder Name ( Category )

        # Media File
        $media_file                 =   uploadFile(request(), 'media_file', "$this->folder_name/$dynamic_folder_name");
        # End Media File

        $generated_link              =   asset("storage/$this->folder_name/$dynamic_folder_name/$media_file");

        $record = $this->eloquentModel()->updateOrCreate(
            [
                'id'                    => request()->id
            ],
            [
                'title'                 => request()->title,
                'media_file'            => $media_file,
                'media_size'            => isset(request()->media_file) ? number()->fileSize(request()->media_file->getSize(),  precision: 2) : request()->media_size,
                'media_extension'       =>isset(request()->media_file) ? request()->media_file->extension() : request()->media_extension ,
                'generated_link'         => $generated_link,
                'category_id'           => request()->category_id,
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
