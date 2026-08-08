<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\GameChallenge\Transaction;
use App\Models\GameChallenge\Wallet;
use App\Models\Notification\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RozarPayController extends Controller
{
    public $rozarpay_api_key = "";
    public $rozarpay_api_secret = "";

    public function __construct()
    {
        $this->rozarpay_api_key  = site_setting()->rozarpay_api_key;
        $this->rozarpay_api_secret  = site_setting()->rozarpay_api_secret;
    }

    public function index()
    {
        return view('payment-gateway.rozarpay.index');
    }

    public function pay()
    {
        $validator = Validator::make(request()->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|integer|gt:0',
            'transactionId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        $data =  [];

        $user_id   =    request()->user_id;
        $amount   =    request()->amount;
        $transactionId   =    request()->transactionId;
        $user = User::find($user_id);

        return view('payment-gateway.rozarpay.pay', compact('user', 'transactionId', 'amount'));
    }

    public function return_old()
    {
        $transactionId = request()->transaction_id;

        $record = Transaction::whereTxnId($transactionId)->first();

        return view('payment-gateway.rozarpay.callback', compact('record'));
    }

    public function return()
    {
        $transactionId = request()->transaction_id;
        $status = request()->status;

        $record = Transaction::whereTxnId($transactionId)->first();

        if ($status):
            $record->status = $status;
            $record->save();

            Wallet::whereTransactionId($record->id)->update(['status' => 5, 'remark' => "Deposit Fund Via Rozarpay Payment Gateway"]);
        endif;

        return view('payment-gateway.rozarpay.callback', compact('record'));
    }

    # Status
    public function status($paymentId, $transactionId)
    {
        // Get API credentials from .env
        $apiKey = $this->rozarpay_api_key;
        $apiSecret = $this->rozarpay_api_secret;

        // Razorpay API URL
        $url = "https://api.razorpay.com/v1/payments/{$paymentId}";

        // Send GET request to Razorpay API
        $response = Http::withBasicAuth($apiKey, $apiSecret)->get($url);

        // Check if request was successful
        if ($response->successful()) {
            $paymentData = $response->json();

            // Check payment status
            $status = $paymentData['status']; // Possible values: 'created', 'authorized', 'captured', 'failed', etc.

            $transaction = Transaction::whereTxnId($transactionId)->first();
            $transaction->payment_info = "Rozarpay Payment ID : {$paymentId}";

            $walletStatus = 0;
            $arr = [];

            $user = User::find($transaction->user_id);

            if ($status === 'captured' || $status === 'authorized') {
                $walletStatus = 1;
                $transaction->status = 1;
                $user->game_wallet_amount = $user->game_wallet_amount + $transaction->amount;
                $user->save();

                # ===========================================================================
                #   Notification
                # ===========================================================================
                $notification_title        =   'Amount deposited successfully';
                $notification_body        =   'Amount deposited ( ₹' . $transaction->amount . ' ) successfully to game wallet.';
                $notification_type        =   'credit';

                $fcm_data  =  (object)
                [
                    'title'                 => $notification_title,
                    'body'                  => $notification_body,
                    'notification_type'     => $notification_type,
                    'fcm_device_token'      =>  $user->fcm_device_token,
                ];

                fcm()->send($fcm_data);

                Notification::create([
                    'user_ids'              => $user->id,
                    'title'                 => $notification_title,
                    'content'               => $notification_body,
                    'notification_type'     => $notification_type,
                ]);

                # Notification

                # ===========================================================================
                #   Notification
                # ===========================================================================

                $arr = ['status' => 'success', 'message' => 'Payment is successful.'];
            } elseif ($status === 'failed') {
                $walletStatus = 2;
                $transaction->status = 2;
                $arr = ['status' => 'failed', 'message' => 'Payment failed.'];
            } else {
                $walletStatus = 2;
                $transaction->status = 2;
                $arr = ['status' => 'pending', 'message' => 'Payment is pending.'];
            }

            $transaction->save();

            Wallet::whereTransactionId($transaction->id)->update(['win_and_game_total_amount' => $user->total_wallet_amount, 'status' => $walletStatus, 'remark' => 'Deposit Fund Via Rozarpay Payment Gateway']);

            return response()->json($arr);
        } else {
            // Handle API error
            return response()->json(['status' => 'error', 'message' => 'Failed to fetch payment details.'], 500);
        }
    }

    public function callback()
    {
        $data = request()->all(); // Convert request to an array

        if (!isset($data['payload'])): return ; endif;

        // Correctly access nested values using array notation
        $payment_id = $data['payload']['payment']['entity']['id'] ?? '';
        $status = $data['payload']['payment']['entity']['status'] ?? '';
        $transactionId = $data['payload']['payment']['entity']['description'] ?? '';

        $transaction = Transaction::whereTxnId($transactionId)->first();
        if(!$transaction): return; endif;
        // Log::info('Razorpay Payment Transaction Id :', ['transactionId' => $transactionId]);
        if($transaction->status == 1): return ; endif;

        $transaction->payment_info = "Rozarpay Payment ID : {$payment_id}";
        
        $walletStatus = 0;
        $arr = [];

        $user = User::find($transaction->user_id);

        // if ($status === 'captured' || $status === 'authorized') {
        if ($status === 'captured') {
            $walletStatus = 1;
            $transaction->status = 1;
            $user->game_wallet_amount = $user->game_wallet_amount + $transaction->amount;
            $user->save();

            # ===========================================================================
            #   Notification
            # ===========================================================================
            $notification_title        =   'Amount deposited successfully';
            $notification_body        =   'Amount deposited ( ₹' . $transaction->amount . ' ) successfully to game wallet.';
            $notification_type        =   'credit';

            $fcm_data  =  (object)
            [
                'title'                 => $notification_title,
                'body'                  => $notification_body,
                'notification_type'     => $notification_type,
                'fcm_device_token'      =>  $user->fcm_device_token,
            ];

            fcm()->send($fcm_data);

            Notification::create([
                'user_ids'              => $user->id,
                'title'                 => $notification_title,
                'content'               => $notification_body,
                'notification_type'     => $notification_type,
            ]);

            # Notification

            # ===========================================================================
            #   Notification
            # ===========================================================================

            $arr = ['status' => 'success', 'message' => 'Payment is successful.'];
        } elseif ($status === 'failed') {
            $walletStatus = 2;
            $transaction->status = 2;
        } else {
            $walletStatus = 2;
            $transaction->status = 2;
        }

        $transaction->save();

        Wallet::whereTransactionId($transaction->id)->update(['win_and_game_total_amount' => $user->total_wallet_amount, 'status' => $walletStatus, 'remark' => "Deposit Fund Via Rozarpay Payment Gateway (Order id : {$transactionId})"]);

        return response()->json(['status' => 'success']);
    }

    # Create Order
    public function create_order()
    {
        $amount = request()->amount;
        $transaction_id = request()->transaction_id;

        // Get API credentials from .env
        $apiKey = site_setting()->rozarpay_api_key;
        $apiSecret = site_setting()->rozarpay_api_secret;


        // Razorpay API endpoint
        $url = "https://api.razorpay.com/v1/orders";

        // Order details
        $data = [
            "amount" => $amount, // Amount in paise (₹1.01)
            "currency" => "INR",
            "receipt" => $transaction_id,
            // "notes" => [
            //     "key1" => "value3",
            //     "key2" => "value2"
            // ]
        ];

        // Send POST request to Razorpay API
        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->withHeaders([
                'Content-Type' => 'application/json'
            ])
            ->post($url, $data);

        // Return response as JSON
        return response()->json($response->json());
    }
}
