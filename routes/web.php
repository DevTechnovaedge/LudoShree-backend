<?php

use App\Events\DemoEvent;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentGateway\CashFreeController;
use App\Http\Controllers\PaymentGateway\RozarPayController;
use App\Http\Controllers\PaymentGateway\UpiGatewayController;
use App\Models\ReferCodeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
# View
Route::view('/', 'home');
Route::view('landing-page', 'landing-page');
Route::view('about', 'about');

Route::get('event', function () {
    event(new DemoEvent(''));

    return 'Event Trigger';
});

Route::get('test', function () {
    // return crul((object) [
    //                         'method'    => 'POST',
    //                         'url'       => 'https://api.quickekyc.com/api/v1/aadhaar-v2/generate-otp',
    //                         'post_data' => json_encode([
    //                                                         'key'           => env('QUICKY_EKYC_API'),
    //                                                         'id_number'     => '417204186468'
    //                                                     ])
    //                     ]);

    // return crul((object) [
    //                         'method'    => 'POST',
    //                         'url'       => 'https://api.quickekyc.com/api/v1/aadhaar-v2/submit-otp',
    //                         'post_data' => json_encode([
    //                                                         'key'            => env('QUICKY_EKYC_API'),
    //                                                         'request_id'     => '1582536',
    //                                                         'otp'            => '541266'
    //                                                     ])
    //                     ]);

    // return crul((object) [ 'method' => 'GET', 'url' => 'https://jsonplaceholder.typicode.com/posts' ]);
    // return crul((object) [ 'method' => 'POST', 'url' => 'https://jsonplaceholder.typicode.com/posts', 'post_data' => json_encode([ 'title' => 'foo', 'body' => 'bar', 'userId' => 1 ]) ]);
});

Route::get('ip', function () {
    // return $_SERVER;
    // return request()->ip();
});

Route::get('refer-code-verify', [HomeController::class, 'refer_code_verify']);

/*
| OLD RapidAPI test routes (deprecated). Admin uses LK Game API via AdminController@view_ludo_king_result.
|
| Route::get('game-result', function () {
|     $curl = curl_init();
|     curl_setopt_array($curl, [
|         CURLOPT_URL => 'https://ludo-king-room-code-api.p.rapidapi.com/game-status/67190a7c00dab24624f8f6af',
|         ...
|     ]);
|     ...
| });
|
| Route::get('roomcode-api', function () { ... RapidAPI game-checkroom ... });
*/

// LK Game API test: GET /game-status/{gameId} — pass ?game_id=693f01f066affd266cb7c436
Route::get('game-result', function () {
    $gameId = request('game_id', '693f01f066affd266cb7c436');
    $lk = app(\App\Services\LkGameApiService::class);
    $raw = $lk->gameStatus($gameId);

    return response()->json($raw ?? ['error' => 'Unable to fetch game status']);
});

Route::get('play-online', function () {

    $ip = get_client_ip();
    $referCode = request()->referCode;

    if ($referCode):
        ReferCodeRequest::updateOrCreate([
            'ip_address' => $ip,
        ], [
            'ip_address' => $ip,
            'refer_code' => $referCode,
        ]);

    endif;

    return redirect('https://merifactory.com/web');
});

Route::get('cron', function () {
    // Always reconcile QR deposits even if schedule mutex skipped the command.
    Artisan::call('upi:sync-pending-deposits');
    Artisan::call('schedule:run');

    return 'Worked';
});

Route::get('key-generate', function () {
    return Artisan::call('key:generate');
});

# Clear Cache
Route::get('clear-cache', function () {
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');

    return 'Cache Cleared';
});
# End Clear Cache

# Link Storage
Route::get('link-storage', function () {
    Artisan::call('storage:link');

    return 'Storage link created';
});
# End Link Storage

# Delete Public Storage
Route::get('delete-public-storage', function () {
    $deleted = false;

    $dir = dirname(__DIR__, 1).'/public/storage';

    if (is_dir($dir)) {
        rmdir($dir);
        $deleted = true;
    }

    return response()->json(['deleted' => $deleted]);
});
# End Delete Public Storage

Auth::routes();

Route::get('download-apk', [HomeController::class, 'download_apk']);

Route::get('withdrawal', [PaymentController::class, 'withdrawal']);

Route::get('push-notification', function () {
    $users = User::select('fcm_device_token')->find([638, 362]);

    $fcm_device_tokens = data_get($users, '*.fcm_device_token');

    if (! $fcm_device_tokens):
        return 'Please provide fcm tokens';
    endif;

    // foreach ($fcm_device_tokens ?? [] as $fcm_device_token):
    $data = (object)
        [
            'title' => 'Demo Title',
            'body' => 'Demo body',
            'notification_type' => 'test',
            // 'fcm_device_token'     =>  $fcm_device_token,
            'topic' => 'all',

        ];
    safe_fcm_send($data);
    // endforeach;

    return 'Sent';
});

# CashFree
Route::prefix('cashfree')->group(function () {
    //    Route::get('', [CashFreeController::class, 'index']);
    //    Route::post('pay', [CashFreeController::class, 'pay']);
    //    Route::any('return', [CashFreeController::class, 'return']);
    //    Route::any('callback', [CashFreeController::class, 'callback']);

    Route::get('pay', [CashFreeController::class, 'pay']);
    Route::any('return', [CashFreeController::class, 'return']);
    Route::any('callback', [CashFreeController::class, 'callback']);
});

# RozarPay
Route::prefix('rozarpay')->group(function () {
    // Route::get('', [RozarPayController::class, 'index']);
    // Route::post('pay', [RozarPayController::class, 'pay']);
    // Route::any('return', [RozarPayController::class, 'return']);

    Route::get('pay', [RozarPayController::class, 'pay']);
    Route::post('create-order', [RozarPayController::class, 'create_order']);
    Route::any('return', [RozarPayController::class, 'return']);
    Route::any('callback', [RozarPayController::class, 'callback']);
});

# UPI Gateway (ekqr.in / Upigateway)
Route::prefix('upigateway')->group(function () {
    Route::any('webhook', [UpiGatewayController::class, 'webhook']);
    Route::any('return', [UpiGatewayController::class, 'return']);
});

# Dynamic Pages
Route::view('about-us', 'pages.about-us');
Route::view('contact-us', 'pages.contact-us');
Route::get('{slug}', [HomeController::class, 'dynamic_pages']);
