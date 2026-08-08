<?php

namespace App\Http\Controllers;

use App\Models\Admin\Site\Page\Page;
use App\Models\ReferCodeRequest;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    public function index()
    {
        return view('home');
    }
    
    public function dynamic_pages($slug)
    {
        $page   =   Page::whereSlug($slug)->whereStatus(1)->firstOrFail();
        
        return view('dynamic-page', compact('page'));
    }
    
    public function download_apk()
    {
        $ip                 =   get_client_ip();
        $referCode          =   request()->referCode;
        
        if($referCode):
            ReferCodeRequest::updateOrCreate([
                                            'ip_address'    => $ip
                                        ],[
                                            'ip_address'    => $ip,
                                            'refer_code'    => $referCode,
                                        ]);
                                    
        endif;
        
        $downloadName = 'merifactory.apk';
        $file = public_path('assets/apk/' . $downloadName);

        if (!is_file($file)) {
            $siteApk = site_setting()->apk_file ?? null;
            if ($siteApk) {
                $file = storage_path('app/public/site/apk/' . $siteApk);
            }
        }

        if (!is_file($file)) {
            abort(404, 'APK is not available. Please upload merifactory.apk to the server.');
        }

        $headers = [
            'Content-Type' => 'application/vnd.android.package-archive',
        ];

        return response()->download($file, $downloadName, $headers);
    }
    
}
