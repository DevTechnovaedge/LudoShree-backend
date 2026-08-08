<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

# Login Via Otp
Route::get('ip', function () {
    return request()->ip();
});

Route::post('send-otp', [ApiController::class, 'send_otp'])->middleware('throttle:otp');
// Route::post('send-otp', [ApiController::class, 'send_otp']);
Route::post('verify-otp', [ApiController::class, 'verify_otp']);
Route::post('register', [ApiController::class, 'register']);
# End Login Via Otp


Route::middleware('auth:api')->group(function () {

    Route::middleware(['active', 'throttle:api'])->group(function () {
        Route::get('home', [ApiController::class, 'home']);
        // No throttle — clients may burst-refetch after websocket challenge.changed events.
        Route::get('game-table', [ApiController::class, 'game_table'])->withoutMiddleware(['throttle:api']);
        Route::post('challenge', [ApiController::class, 'challenge']);
        Route::get('wallet-history', [ApiController::class, 'wallet_history']);
        Route::get('notifications', [ApiController::class, 'notifications']);
        Route::get('my-challenges', [ApiController::class, 'my_challenges']);
        Route::get('referrals', [ApiController::class, 'referrals']);
        Route::get('game-challenge', [ApiController::class, 'game_challenge']);

        Route::get('leaderboard', [ApiController::class, 'leaderboard']);

        # Payment & Transaction
        Route::post('transfer', [ApiController::class, 'transfer']);
        Route::post('deposit-request', [ApiController::class, 'deposit_request']);
        Route::post('transaction-status', [ApiController::class, 'transaction_status']);

        Route::post('financial/store', [ApiController::class, 'store_financial_details']);
        Route::get('financial/list', [ApiController::class, 'financial_list']);
        Route::post('financial/remove', [ApiController::class, 'financial_remove']);
        # End Payment & Transaction

        Route::post('transfer_win_to_game_amount', [ApiController::class, 'transfer_win_to_game_amount']);

        # Cashier Panel
        Route::get('cashier/withdrawals', [ApiController::class, 'cashier_withdrawals']);
        Route::post('cashier/withdrawal-action', [ApiController::class, 'cashier_withdrawal_action']);
        # End Cashier Panel
    });

    # Profile
    Route::post('update-profile', [ApiController::class, 'update_profile'])->middleware('auth:api');
    Route::post('update-kyc', [ApiController::class, 'update_kyc'])->middleware('auth:api')->middleware('throttle:aadhar-kyc');
    Route::post('verify-aadhar-card-otp', [ApiController::class, 'verify_aadhar_card_otp'])->middleware('throttle:verify-aadhar-kyc');
    Route::get('profile', [ApiController::class, 'profile'])->middleware('auth:api');
    # End Profile
});
