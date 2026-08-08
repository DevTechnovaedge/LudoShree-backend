<?php

namespace App\Http\Controllers\CustomAuth;
use App\Http\Controllers\Controller;
use App\Models\Administrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    public function index()
    {
    	return view("admin.login");
    }
 
    public function login_process(Request $request)
        {

            $validator = Validator::make($request->all(),[
                'username' => 'required|max:255',
                'password' => 'required',
            ]);
    
            if($validator->fails()){
                return response()->json([
                    'status'=>false,
                    'message'=>implode(", ",$validator->messages()->all())
                ]);
            }
            
            $user   =   Administrator::where("username", $request->username)->where('status', 1)->first();
            
            if($user){
                
                if (Hash::check($request->password, $user->password)) {
                    
                    auth('admin')->login($user);
                    
                    if(auth('admin')->check()):
                        // return redirect('admin');
                        return redirect()->route('admin::dashboard');
                    else:
                        return back()->with('back_msg', 'Some error occured');
                    endif;

                }else{
                    return back()->with('back_msg', 'Invalid password');
                }
        }else{
            return back()->with('back_msg', 'User not found');
        }
    }

    # Change Password
    public function change_password(){
        $validator    =   Validator::make(request()->all(), [
            'old_password'              => 'required',
            'new_password'              => 'required|different:old_password|min:6',
            'password_confirmation'     => 'required|same:new_password|min:6',
        ]);

        if ($validator->fails()) :
            $arr                                =   array('status' => false, 'message' => $validator->errors()->first());
            return response()->json($arr);
        endif;

        # Check Current Password 
        if (!Hash::check(request()->old_password, auth('admin')->user()->password)) {
            $arr    =   array('status' => false, 'message' => 'Old password is incorrect');
            return response()->json($arr);
        }
        # End Check Current Password 

        // Update the password
        $user = auth('admin')->user();
        $user->password = Hash::make(request()->new_password);
        $user->save();

        $arr                                =   array('status' => true, 'message' => 'Password changed successfullly');
        return response()->json($arr);
    }
    # End Change Password

        public function logout(){
            auth('admin')->logout();
            return redirect('admin');
        }
}