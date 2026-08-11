<?php

use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\GameCommissionSlot;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Auth;
use App\Models\Module;
use App\Models\Slider;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

use App\Models\Notification\Notification;
use App\Services\FcmNotificationService;
use App\Services\HaodaPay;

if(!function_exists('logd')){
  function logd($message){
     Log::debug($message);
    }
}
    
// if(!function_exists('get_client_ip')){
//   // Function to get the client IP address
// function get_client_ip() {
//   return $_SERVER['REMOTE_ADDR'];
// }
// }


if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        // Try 'X-Forwarded-For' header if available (proxies or load balancers)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            
            // Search for the first valid IPv4 address
            foreach ($ips as $ip_candidate) {
                $ip_candidate = trim($ip_candidate);
                if (filter_var($ip_candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip_candidate;  // Return IPv4 address
                }
            }
        }

        // Check if 'REMOTE_ADDR' is IPv4
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;  // Return IPv4 if REMOTE_ADDR is IPv4
            }
        }

        // If only IPv6 is available, return a message
        return 'IPv4 not available, current IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}



    
if(!function_exists('fcm')){
  function fcm(){
    return new FcmNotificationService;
  }
}

if (!function_exists('safe_fcm_send')) {
  /**
   * Send FCM push without ever throwing to the caller.
   *
   * @param  object|array  $data
   * @return mixed|null
   */
  function safe_fcm_send($data)
  {
    try {
      $payload = is_array($data) ? (object) $data : $data;

      return fcm()->send($payload);
    } catch (\Throwable $e) {
      Log::warning('[FCM] safe_fcm_send failed', ['error' => $e->getMessage()]);

      return null;
    }
  }
}

if (!function_exists('safe_notify')) {
  /**
   * Deliver push + in-app notification without breaking API / money flows.
   *
   * @param  string|null            $fcmDeviceToken
   * @param  int|string|array|null  $userIds
   */
  function safe_notify(
    ?string $fcmDeviceToken,
    string $title,
    string $body,
    string $type,
    $userIds = null,
    array $context = [],
    ?string $topic = null,
    ?bool $defer = null
  ): void {
    $payload = [
      'fcmDeviceToken' => $fcmDeviceToken,
      'title' => $title,
      'body' => $body,
      'type' => $type,
      'userIds' => $userIds,
      'context' => $context,
      'topic' => $topic,
    ];

    $deliver = static function () use ($payload): void {
      try {
        if ($payload['topic']) {
          safe_fcm_send([
            'title' => $payload['title'],
            'body' => $payload['body'],
            'notification_type' => $payload['type'],
            'topic' => $payload['topic'],
          ]);
        } elseif (! empty($payload['fcmDeviceToken'])) {
          safe_fcm_send([
            'title' => $payload['title'],
            'body' => $payload['body'],
            'notification_type' => $payload['type'],
            'fcm_device_token' => $payload['fcmDeviceToken'],
          ]);
        }

        $userIds = $payload['userIds'];
        if ($userIds !== null && $userIds !== '' && $userIds !== []) {
          Notification::create([
            'user_ids' => is_array($userIds) ? implode(',', array_unique($userIds)) : (string) $userIds,
            'title' => $payload['title'],
            'content' => $payload['body'],
            'notification_type' => $payload['type'],
          ]);
        }
      } catch (\Throwable $e) {
        Log::warning('[notify] delivery failed', array_merge($payload['context'], [
          'type' => $payload['type'],
          'error' => $e->getMessage(),
        ]));
      }
    };

    $shouldDefer = $defer ?? ! app()->runningInConsole();

    if ($shouldDefer) {
      dispatch($deliver)->afterResponse();
    } else {
      $deliver();
    }
  }
}

if(!function_exists('HaodaPay')){
  function HaodaPay(){
    return new HaodaPay;
  }
}

if (!function_exists('game_commission_slot')) {
  function game_commission_slot(){
    return Cache::remember('game_commission_slot', 300, fn () => GameCommissionSlot::first());
  }
}

if (!function_exists('generate_uid')) {
  function generate_uid(){
    return 'LH'.strtoupper(str()->random(7));
    // return "LH" . random_int(10000000, 99999999);
  }
}

if (!function_exists('generate_alpa_numeric_code')) {
  function generate_alpa_numeric_code($length = 7){
    return 'LH'.strtoupper(str()->random($length));
  }
}

if (!function_exists('carbon')) {
  function carbon()
  {
    return new Carbon();
  }
}

if (!function_exists('number')) {
  function number()
  {
    return new Number;
  }
}

if (!function_exists('str')) {
  function str()
  {
    return new Str;
  }
}

if (!function_exists('sendMail')) {
  function sendMail($to, $from, $subject, $html)
  {
    $from = ($from) ? $from : env('MAIL_FROM_ADDRESS');
    $result = Mail::send(array(), array(), function ($message) use ($to, $from, $subject, $html) {
      $message->to($to)
        ->subject($subject)
        ->from($from)
        ->setBody($html, 'text/html');
    });

    return ($result) ? 1 : 0;
  }
}

# Upload Image
if (!function_exists('uploadFile')) {
  function uploadFile($file, $path = '')
  {
    $file_name              =   "";
    if (request()->hasFile($file)) :
      $img                            =   request()->file($file);
      // $file_name                      =   uniqid() . '.' . $img->extension();
      $file_name                      =   trim(str_replace('_','-', strtolower($img->getClientOriginalName())));
      $path                           .=  '/';

      // Use UploadedFile contents — never file_get_contents(request()->field),
      // which can resolve to a directory/path string and crash the request.
      Storage::disk('public')->put($path . $file_name, $img->get());
    else :
      $file_name        = request()->{'old_' . $file};

      if ($path == 'gallery_images') :
        $file_name        = request()->{str_replace('.image', '.old_image', $file)};
      endif;
    endif;
    
    return $file_name;
  }
}
# End Upload Image

# Upload Gallery Files
if (!function_exists('uploadGalleryFiles')) {
  function uploadGalleryFiles($file, $path)
  {

    $file_name_arr              =   [];
    $file_name                  =   "";

    if (request()->hasFile($file)) :
      $files                            =   request()->file($file);

      foreach ($files as $file) :
        $file_name                      =   uniqid() . '.' . $file->extension();
        $file_name_arr[]                =   $file_name;

        Storage::disk('public')->put($path . '/' . $file_name, file_get_contents($file));
      endforeach;
    else :
      $file_name        = request()->{'old_' . $file};

    endif;
    return $file_name_arr;
  }
}
# End Upload Image

if (!function_exists('get_permissions')) {
  function get_permissions($role_id = null)
  {

    $permissions = array();
    if (Auth::guard('admin')->check()) {
      $role_id = ($role_id) ? $role_id : Auth::guard('admin')->user()->role_id;
      $permission_data = Module::select('module_code', 'rr_create', 'rr_edit', 'rr_delete', 'rr_view', 'status')
        ->leftJoin('role_rights', function ($join) use ($role_id) {
          $join->on('role_rights.module_id', '=', 'modules.id');
          $join->where('role_rights.role_id', '=', $role_id);
        })
        ->where('status', 1)
        ->get();
        
      if ($permission_data) {
        foreach ($permission_data as $row) {
          $permissions[$row->module_code] = $row;
        }
      }
    }
    return $permissions;
  }
}

# Calculate Percentage
if (!function_exists('calculatePercentage')) {
  function calculatePercentage($mrp, $salePrice)
  {
    if ($mrp > $salePrice) :
      $percentage = (($mrp - $salePrice) / $mrp) * 100;
      return number_format($percentage, 2) . '% off';
    endif;
    return false;
  }
}
# Calculate Percentage
if (!function_exists('getTempUser')) {
  function getTempUser()
  {
    return  session()->get('session_id');
  }
}

# OTP API
if (!function_exists('crul')) {
  function crul($data)
  {
    
    $arr            = [];
    $method         = $data->method ?? 'GET';
    $url            = $data->url ?? '#';
    $post_data      = $data->post_data ?? null;
    $header         = $data->header ?? [ 'Content-Type: application/json' ];

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url, # Url
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_POSTFIELDS    => $post_data,
      CURLOPT_HTTPHEADER => $header,
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      $arr      = ['status' => false, 'message' => $err];
    } else {
      $result   = json_decode($response);
      $arr      = ['status' => true, 'message' => 'data fetched', 'data' => $result ];
    }
    return (object) $arr;
  }
}
# OTP API

# Site Settings
if (!function_exists('site_setting')) {
  function site_setting()
  {
    return Cache::remember('site_setting', 300, fn () => SiteSetting::first());
  }
}
# End Site Settings

if (!function_exists('custom_array_pluck')) {
  function custom_array_pluck($raw_records, $group_column_name)
  {

    $unique_keys_in_arr       = [];
    $final_records       = [];

    foreach ($raw_records ?? [] as $key => $raw_record) :
      if (!in_array($raw_record->$group_column_name, $unique_keys_in_arr)) :
        $unique_keys_in_arr[]       = $raw_record->$group_column_name;
      endif;
    endforeach;

    # Group Data

    sort($unique_keys_in_arr);

    foreach ($unique_keys_in_arr ?? [] as  $key => $unique_keys_in_item) {
      $data_arr               = [];
      foreach ($raw_records ?? [] as $raw_record) :
        if ($unique_keys_in_item == $raw_record->$group_column_name) :
          $data_arr[]               = $raw_record;
        endif;
      endforeach;
      $final_records[]          =    ['id' =>  ++$key, 'name' => $unique_keys_in_item, 'data' => $data_arr];
    }
    # End Group Data

    return $final_records;
  }

  # Send Notification
  function send_notification($user_app_token, $data)
  {
    #=====================> Notification API  <=====================# 
    $fcm_device_token_arr                =   array();

    $data                                =   (object) $data;
    $title                               =   $data->title ?? 'Notification title';
    $body                                =   $data->body ?? 'Hello I am from Your php server';

    $url                                =   "https://fcm.googleapis.com/fcm/send";
    // $user_app_token                     =   "f8SFQh2CTu2xvozOK5OR7x:APA91bFfjAIKT5OpepJb6K8Y83RO70obI5icNLso1SP3KIUXaRVNWcO7SiFBOVAEAnZjcLY1MtGMlW_7YVfqqggYpU1rM7f1ULviQCCl1lNEvPbsyesbi-46rc0UoVO_0c3DitiT8dNY";

    $serverKey                          =   'AAAAyycA9Uc:APA91bGmIL_4i0xIar0IR0r2TtkT01G9IUU69qLq1qZ63gUxoclvpazyLmHNda1cvK4Ai3fOY27Dvo25rtDLmfHJ8mlKfHxGBCd11mfWZWXxPv1R3iXJ7fIfKz_LyCfayHjfD9czh1DI';

    if (is_array($user_app_token)) :
      $fcm_device_token_arr           =   $user_app_token;
    else :
      $fcm_device_token_arr[]         =   $user_app_token;
    endif;

    $additional_data                    =  array(
      "notification_type"     =>  $data->body ?? 'Notification type',
      "user_token"            =>  'ABC', //$this->user_token,
      "order_id"              =>  '#12345678' //$this->user_token,
    );

    $notification                       =   array(
      "icon"                  =>  "logonewapp",
      'title'                 =>  $title,
      'body'                  =>  $body,
      "message"               =>  "Come at evening...",
      'sound'                 =>  'default',
      'badge'                 =>  '1',
      "data"                  =>  $additional_data,
      'content_available'     =>  true
    );

    // $arrayToSend                        =   array('to' => $user_app_token, 'notification' => $notification, 'priority' => 'high');
    $arrayToSend                        =   array('registration_ids' => $fcm_device_token_arr, 'notification' => $notification, 'priority' => 'high', 'data' => $additional_data, 'content_available' => true);

    // $arrayToSend                        =   array('notification' => $notification, 'priority' => 'high');
    $json                               =   json_encode($arrayToSend);
    $headers                            =   array();
    $headers[]                          =   'Content-Type: application/json';
    $headers[]                          =   'Authorization: key=' . $serverKey;
    $ch                                 =   curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    //Send the request
    $response                           =   curl_exec($ch);
    //Close request
    if ($response === FALSE) {
      die('FCM Send Error: ' . curl_error($ch));
    }
    curl_close($ch);
    #=====================> End Notification API  <=====================# 
    return true;
  }
  # End Send Notification

  # Challenges
  function challenges($user_id)
  {
    return GameChallenge::whereChallengerId($user_id)->orWhere('opponent_id', $user_id)->get();
  }
  # End Challenges

  # Get slider via code
	if (!function_exists('getSliderViaCode')) {
    function getSliderViaCode($slider_code){
        return Slider::with('slides:slider_id,id,mobile_image')
                            ->with('slides', function($query){
                                            $query->where('mobile_image', '!=', '');
                                            $query->where(function($sub_query){
                                                $sub_query->where('slider_for', '!=', 1);
                                              });
                                               
                                            $query->whereStatus(1);
                                            $query->select('slider_id', 'id', 'mobile_image', 'slider_url');
                                            $query->orderBy('sort_order', 'asc');
                                          })
                            ->code($slider_code)->whereStatus(1)->first();
      }
  }
# End Get slider via code

# SmsService
if(!function_exists('sms')){
  function sms(){
    return new SmsService;
  }
}
# End SmsService

 #=====================> Notification API  <=====================# 
 function push_notification($data){
  return false;

  $title                              =   $data->title;
  $body                               =   $data->body;
  $fcm_device_token_arr               =   $data->fcm_device_tokens;
  
  // print_r($fcm_device_token_arr);die;
      
  $additional_data                    =   array();
  $notification                       =   array();
  
  $url                                =   "https://fcm.googleapis.com/fcm/send";
  // $token                              =   $notify_device->device_token"];
  
  $serverKey                          =   '';
  // $title                              =   "Notification title";
  // $body                               =   "Hello I am from Your php server";
  
  $additional_data                    =  array(
                                                  "notification_type"     =>  'demo',
                                                  "user_token"            =>  'demo',
                                                  "application_id"        =>  'demo',
                                              );
                                              
  $notification                       =   array(
                                                  "icon"      => "logonewapp", 
                                                  'title'     => $title, 
                                                  'body'      => $body, 
                                                  'sound'     => 'default', 
                                                  'badge'     => '1', 
                                              );
                                              
                                              
   $arrayToSend                        =   array('registration_ids' => $fcm_device_token_arr, 'notification' => $notification, 'priority' => 'high', 'data' => $additional_data, 'content_available' => true);
  // $arrayToSend                        =   array('to' => $token, 'notification' => $notification, 'priority' => 'high', 'data' => $additional_data, 'content_available' => true);
  $json                               =   json_encode($arrayToSend);
  $headers                            =   array();
  $headers[]                          =   'Content-Type: application/json';
  $headers[]                          =   'Authorization: key=' . $serverKey;
  $ch                                 =   curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  
  //Send the request
  $response                           =   curl_exec($ch);
  //Close request
  if ($response === FALSE) {
      die('FCM Send Error: ' . curl_error($ch));
  }
  curl_close($ch);
}
#=====================> End Notification API  <=====================#
    
}


    	# Custom Pagination
      if (!function_exists('custom_pagination')) {
        function custom_pagination($items, $perPage = 10){
            
            $currentPage = request()->page ?? 1;

            $offSet = ($currentPage * $perPage) - $perPage;
            
            if(count($items) > $perPage):
                $itemsForCurrentPage = array_slice($items, $offSet, $perPage, true);
            endif;
            
            $items_with_pagination = new LengthAwarePaginator($itemsForCurrentPage ?? $items, count($items), $perPage, $currentPage);
            return $items_with_pagination;
        }
     }
    # End Custom Pagination

    # Unlock Game Challenge

    function lock_game_challenge($game_challenege){
      
      if(!$game_challenege):
        return false;
      endif;

      // Quiet save: lock is internal concurrency, must NOT fire socket broadcasts
      // (those use ShouldBroadcastNow and can hang/slow the accept API).
      $game_challenege->is_lock  = 1;
      $game_challenege->saveQuietly();
    }

    function unlock_game_challenge($game_challenege){
      
      if(!$game_challenege):
        return false;
      endif;

      $game_challenege->is_lock  = 0;
      $game_challenege->saveQuietly();
    }

    # King (Daddy King) cross-platform sync

    if (!function_exists('king_enabled')) {
      /**
       * Integration master switch: env flag + admin pause toggle.
       */
      function king_enabled(): bool
      {
        if (!config('king.enabled')) {
          return false;
        }

        try {
          return !cache()->get('king:paused', false);
        } catch (\Throwable $e) {
          return true;
        }
      }
    }

    if (!function_exists('king_ws_alive')) {
      /**
       * True when the king:listen daemon reported activity recently.
       */
      function king_ws_alive(): bool
      {
        try {
          $lastAlive = (int) cache()->get('king:alive_at', 0);
        } catch (\Throwable $e) {
          return false;
        }

        return $lastAlive > 0 && (time() - $lastAlive) <= (int) config('king.alive_ttl', 30);
      }
    }

    if (!function_exists('is_king_ghost_user')) {
      /**
       * @param  \App\Models\User|int|null  $user
       */
      function is_king_ghost_user($user): bool
      {
        if (!$user) {
          return false;
        }

        if (is_numeric($user)) {
          $user = \App\Models\User::find($user);
        }

        return $user ? ((int) ($user->is_king_player ?? 0) === 1) : false;
      }
    }
    # End King