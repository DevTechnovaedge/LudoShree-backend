<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\RoleRight;
use App\Models\Module;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class RoleController extends Controller
{   
    public function __construct(Request $request)
    {
        $this->middleware(function ($request, $next) {

            $role_id = auth('admin')->user()->role_id;
            
            $permissions = get_permissions($role_id);

            $action_method = $request->route()->getActionMethod();
            if(isset($permissions['roles'])){
                $perms = $permissions['roles'];
                $role_perms = $permissions['role-permissions'];
                if ($perms['rr_view']==1 && $action_method=='index') { }
                else if ($perms['rr_create']==1 && ($action_method=='create' || $action_method=='store')) { }
                else if ($perms['rr_edit']==1 && ($action_method=='edit' || $action_method=='update')) { }
                else if ($perms['rr_delete']==1 && $action_method=='destroy') { }
                else if ($role_perms['rr_edit']==1 && ($action_method=='role_permission' || $action_method=='role_permission_update')) { }
                else { return redirect('/admin'); }
            }
            else { return redirect('/admin'); }

            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Role::latest()->get();
        return view("admin.roles.index",compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view("admin.roles.create");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $request->validate([
            'name'=>'required'
        ]);

        $role  = $request->all();

        Role::create($role);

        return redirect()->route('admin::roles.index')->with('success','Role created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        try {
            $role = Role::findOrFail($id);

            return view("admin.roles.edit",compact('role'));
        }
        catch (ModelNotFoundException $e)
        {
            return redirect()->route('admin::roles.index')->with('error','Role Not Found');
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try{

            $role = Role::findOrFail($id);

            $request->validate([
                'name'=>'required'
            ]);

            $update_array = $request->all();

            $role->update($update_array);

            return redirect()->route('admin::roles.index')->with('success','Role updated successfully.');
        }
        catch (ModelNotFoundException $e){
            return redirect()->route('admin::roles.index')->with('error','Role Not Found.');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try
        {
            $role = Role::findOrFail($id);

            $role->delete();

            return redirect()->route('admin::roles.index')->with('success','Role deletd successfully.');

        }
        catch(ModelNotFoundException $e)
        {
            return redirect()->route('admin::roles.index')->with('error','Role Not Found');
        }
    }

    public function role_permission($id)
    {
        try {
            $role = Role::findOrFail($id);

            $module_list = Module::select('modules.id as m_id','module_name','module_code','perm_create','perm_edit','perm_delete','perm_view','rr_create', 'rr_edit', 'rr_delete', 'rr_view', 'module_id', 'role_id',"role_rights.id as role_right_id")
            //->leftJoin("role_rights","role_rights.module_id", '=',"modules.id")
            ->leftJoin('role_rights', function($join) use ($id) {
              $join->on('role_rights.module_id', '=', 'modules.id');
              $join->where('role_rights.role_id', '=', $id);
            })
            ->get();

            //return response()->json([$module_list]);exit;

            return view("admin.roles.role_permission",compact('role','module_list'));
        }
        catch (ModelNotFoundException $e)
        {
            return redirect()->route('admin::roles.index')->with('error','Role Not Found');
        }
    }

    public function role_permission_update(Request $request)
    {
        try {

            $role_id = $request->role_id;
            $module_array = $request->module;
            $module_ids = $request->module_id;

            $role = Role::findOrFail($request->role_id);

            if ($module_ids) {
                foreach ($module_ids as $mk => $module_id) {
                    $role_right = RoleRight::where('role_id',$role_id)->where('module_id',$module_id)->first();
                    $rr_create = 0;
                    $rr_edit = 0;
                    $rr_delete = 0;
                    $rr_view = 0;
                    if (isset($module_array[$module_id]['rr_create'])) {
                        $rr_create = 1;
                    }
                    if (isset($module_array[$module_id]['rr_edit'])) {
                        $rr_edit = 1;
                    }
                    if (isset($module_array[$module_id]['rr_delete'])) {
                        $rr_delete = 1;
                    }
                    if (isset($module_array[$module_id]['rr_view'])) {
                        $rr_view = 1;
                    }
                    if ($role_right) {
                        $array = array('rr_create' => $rr_create, 'rr_edit' => $rr_edit, 'rr_delete' => $rr_delete, 'rr_view' => $rr_view);
                        RoleRight::where('role_id',$role_id)->where('module_id',$module_id)->update($array);
                    } else {
                        $array = array('rr_create' => $rr_create, 'rr_edit' => $rr_edit, 'rr_delete' => $rr_delete, 'rr_view' => $rr_view, 'role_id' => $role_id, 'module_id' => $module_id);
                        RoleRight::create($array);
                    }
                }
            }

            return redirect()->route('admin::role_permission',$role_id)->with('success','Role Permission Updated.');
        }
        catch (ModelNotFoundException $e)
        {
            return redirect()->route('admin::roles.index')->with('error','Role Not Found');
        }
    }
}
