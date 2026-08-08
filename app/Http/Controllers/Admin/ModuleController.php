<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Module;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class ModuleController extends Controller
{
    public function __construct(Request $request)
    {

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $modules = Module::latest()->get();
        return view("admin.modules.index",compact('modules'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view("admin.modules.create");
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
            'module_name'=>'required',
            'module_code'=>'required'
        ]);

        $module  = $request->all();
        $module['perm_create']  = ($request->perm_create)?1:0;
        $module['perm_edit']  = ($request->perm_edit)?1:0;
        $module['perm_delete']  = ($request->perm_delete)?1:0;
        $module['perm_view']  = ($request->perm_view)?1:0;
        $module['status']  = ($request->status==1)?1:0;

        Module::create($module);

        return redirect()->route('admin::modules.index')->with('success','Module created successfully.');
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
            $module = Module::findOrFail($id);

            return view("admin.modules.edit",compact('module'));
        }
        catch (ModelNotFoundException $e)
        {
            return redirect()->route('admin::modules.index')->with('error','Module Not Found');
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

            $module = Module::findOrFail($id);

            $request->validate([
                'module_name'=>'required',
                'module_code'=>'required'
            ]);

            $update_array = $request->all();

            $update_array['perm_create']  = ($request->perm_create)?1:0;
            $update_array['perm_edit']  = ($request->perm_edit)?1:0;
            $update_array['perm_delete']  = ($request->perm_delete)?1:0;
            $update_array['perm_view']  = ($request->perm_view)?1:0;
            $update_array['status']  = ($request->status==1)?1:0;


            $module->update($update_array);

            return redirect()->route('admin::modules.index')->with('success','Module updated successfully.');
        }
        catch (ModelNotFoundException $e){
            return redirect()->route('admin::modules.index')->with('error','Module Not Found.');
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
            $module = Module::findOrFail($id);

            $module->delete();

            return redirect()->route('admin::modules.index')->with('success','Module deletd successfully.');

        }
        catch(ModelNotFoundException $e)
        {
            return redirect()->route('admin::modules.index')->with('error','Module Not Found');
        }
    }
}
