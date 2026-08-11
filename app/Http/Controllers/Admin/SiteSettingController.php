<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

class SiteSettingController extends Controller
{
    public $permission_key              =   "site_settings";

    public function index()
    { 
        $this->authorize('permissions', [$this->permission_key, 'view']);

        $sitesetting = SiteSetting::first();

        // return $sitesetting;
        $sitesetting->address       =   json_decode($sitesetting->address);
        $sitesetting->socialLinks   =   $sitesetting->socialLinks;

        return view("admin.sitesettings.edit",compact('sitesetting'));
    }

    public function update(Request $request, $id)
    {
        $this->authorize('permissions', [$this->permission_key, 'edit']);

        $sitesetting = SiteSetting::findOrFail($id);

            $request['logo']                        =  uploadFile('site_logo', 'site');
            $request['apk_file']                    =  uploadFile('site_apk_file', 'site/apk');
            $request['fav_icon']                    =  uploadFile('site_fav_icon', 'site');
            $request['deposit_scanner_img']         =  uploadFile('site_deposit_scanner_img', 'site/deposit-scanner');
    
            
        $request->validate([
            'site_name'=>'required'
        ]);

        $update_array = $request->all();

        $sitesetting->update($update_array);
        Cache::forget('site_setting');

        return redirect()->route('admin::sitesettings.index')->with('back_msg', "Site Setting updated successfully.");
    }


}