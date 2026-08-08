<?php

namespace App\Http\Controllers\Admin\Management;

use App\Http\Controllers\Controller;
use App\Models\Administrator;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Property;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MemberController extends Controller
{
    public function index()
    {
      	# $this->authorize('permissions', [ 'members', 'view' ]);
        $members        =   Administrator::get();
        return view("admin.pages.management.members.index", compact('members'));
    }

    public function create()
    {
        $roles              =   Role::select('id', 'name')->notSuperAdmin()->get();
        $categories         =   Category::select('id', 'name', 'status')->active()->get();

        return view("admin.pages.management.members.single", compact('roles', 'categories'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'name'                 	=>  'required',
            'email'                	=>  "required|unique:administrators,email,$request->id",
            'mobile'               	=>  'required',
         ]);

        Administrator::updateOrCreate(
            [
                'id'                => $request->id
            ],
            [
                'name'              => $request->name,
                'role_id'           => $request->role_id,
                'username'          => $request->email,
                'email'             => $request->email,
                'mobile'            => $request->mobile,
                'password'          => $request->password ? Hash::make($request->password) : $request->old_password,
                'status'            => $request->status
            ]
        );

        return redirect()->route('admin::members.index');
    }


    public function show($id)
    {
        //
    }


    public function edit(Administrator $member)
    {
        $roles          =   Role::select('id', 'name')->notSuperAdmin()->get();
       
        return view("admin.pages.management.members.single", compact('roles', 'member'));
    }

    public function update(Request $request, $id)
    {
      
    }

    public function destroy($id)
    {
       $member = Administrator::find($id);
       $member->delete();
       return back()->with('back_msg', '<div class="alert alert-success">Member deleted successfully</div>');


    }
}
