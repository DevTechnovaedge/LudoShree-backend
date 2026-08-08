<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\GameChallenge\Transaction;
use App\Models\GameChallenge\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CashFreeController extends Controller
{
    // private const baseUrl = "https://sandbox.cashfree.com/pg/orders";
    private const baseUrl = "https://api.cashfree.com/pg/orders";

    public $cashfree_api_key = "";
    public $cashfree_api_secret = "";

    public function __construct()
    {
        $this->cashfree_api_key  = site_setting()->cashfree_api_key;
        $this->cashfree_api_secret  = site_setting()->cashfree_api_secret;
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

          $user_id   =    request()->user_id;
          $amount   =    request()->amount;
          $transactionId   =    request()->transactionId;
          $user = User::find($user_id);
               
          $url = self::baseUrl;

          $headers = array(
               "Content-Type: application/json",
               "x-api-version: 2023-08-01",
               "x-client-id: ".$this->cashfree_api_key,
               "x-client-secret: ".$this->cashfree_api_secret
          );

          $data = json_encode([
               'order_id' =>  $transactionId,
               'order_amount' => $amount,
               "order_currency" => "INR",
               "customer_details" => [
                    "customer_id" => 'customer_'.rand(111111111,999999999),
                    "customer_name" => $user->name,
                    "customer_email" => $user->email,
                    "customer_phone" => "$user->mobile",
               ],
               "order_meta" => [
                   "return_url" => url('cashfree/return/?order_id={order_id}'),
                   "payment_methods" => "upi",
                    // "return_url" => ('https://technovaedge.in/ludo-shree/cashfree/return/?order_id={order_id}&order_token={order_token}')

               ]
          ]);

          $curl = curl_init($url);

          curl_setopt($curl, CURLOPT_URL, $url);
          curl_setopt($curl, CURLOPT_POST, true);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
          curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

          $resp = curl_exec($curl);

          curl_close($curl);
          $res = json_decode($resp);

          if ($res && isset($res->payment_session_id)) {
              $paymentSessionId = $res->payment_session_id;
              return view('payment-gateway.cashfree.pay', compact('paymentSessionId'));
          } else {
              return "Error: Payment session ID not found.";
          }

          
     }

     public function return()
     {
        $order_id = request()->order_id;
        // $this->status($order_id);

        $record = Transaction::whereTxnId($order_id)->first();

          return view('payment-gateway.cashfree.callback', compact('record'));
     }

    # Status
    public function status($order_id){

        $client_id = $this->cashfree_api_key;
        $client_secret = $this->cashfree_api_secret;

        $url =  $url = self::baseUrl."/$order_id";

        $headers = [
            "accept: application/json",
            "x-client-id: $client_id",
            "x-client-secret: $client_secret",
            "x-api-version: 2022-01-01"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($result && isset($result['order_status'])) {

            $transactionStatus  = $result['order_status'];
            $status = 0;
            $transaction = Transaction::whereTxnId($order_id)->first();
            $user = User::find($transaction->user_id);

            switch($transactionStatus):
                case 'PAID':
                    $status = 1;
                    $user->game_wallet_amount = $user->game_wallet_amount + $transaction->amount;
                    $user->save();

                    # ===========================================================================
        #   Notification
        # ===========================================================================
                safe_notify(
                    $user->fcm_device_token,
                    'Amount deposited successfully',
                    'Amount deposited ( ₹'.$transaction->amount.' ) successfully to game wallet.',
                    'credit',
                    $user->id,
                    ['user_id' => $user->id, 'context' => 'cashfree_callback']
                );

            # Notification

            # ===========================================================================
            #   Notification
            # ===========================================================================

                    break;
                case 'FAILED':
                    $status = 2;
                    break;
                case 'PENDING':
                    $status = 0;
                break;
                case 'ACTIVE':
                    $status = 0;
                break;
                case 'EXPIRED':
                    $status = 2;
                    break;
                case 'CANCELLED':
                    $status = 2;
                    break;
            endswitch;
            
            $transaction->status = $status;
            $transaction->payment_info = 'Cashfree Payment Gateway';
            $transaction->save();
          
            Wallet::whereTransactionId($transaction->id)->update(['win_and_game_total_amount' => $user->total_wallet_amount, 'status' => $status, 'remark' => 'Deposit Fund Via Cashfree Payment Gateway']);

            // echo "Order Status: " . $result['order_status'];
        } else {
            // echo "Failed to fetch order status.";
        }
    }

    public function callback()
    {
        $data = request()->all(); // Convert request to an array
    
        // Log::info('Cashfree Callback Data:', $data);
        
         $data = request()->all(); // Convert request to an array
    
        $status = $data['data']['payment']['payment_status'] ?? '';
        $transactionId = $data['data']['order']['order_id'] ?? '';
       // Log::info('Cashfree Payment ID:', ['id' => $transactionId]);
        // Log::info('Cashfree Payment Status:', ['status' => $status]);
    
        $transaction = Transaction::whereTxnId($transactionId)->first();

        if(!$transaction):
            return "No transaction founded.";
        endif;

        if($transaction->status == 1):
            return ;
        endif;

        $transaction->payment_info = "Cashfree Payment ID : {$transactionId}";
        
        $walletStatus = 0;
        $arr = [];
        
        $user = User::find($transaction->user_id);

        if ($status === 'SUCCESS') {
            $walletStatus = 1;
            $transaction->status = 1;
            $user->game_wallet_amount = $user->game_wallet_amount + $transaction->amount;
            $user->save();

                # ===========================================================================
        #   Notification
        # ===========================================================================
        safe_notify(
            $user->fcm_device_token,
            'Amount deposited successfully',
            'Amount deposited ( ₹'.$transaction->amount.' ) successfully to game wallet.',
            'credit',
            $user->id,
            ['user_id' => $user->id, 'context' => 'cashfree_webhook']
        );

    # Notification

    # ===========================================================================
    #   Notification
    # ===========================================================================

        } elseif ($status === 'FAILED') {
            $walletStatus = 2;
            $transaction->status = 2;
        } elseif ($status === 'DROPPED') {  // New condition for dropped payments
            $walletStatus = 2;  // Optional: Differentiate dropped transactions
            $transaction->status = 2;
        } else {
            $walletStatus = 2;
            $transaction->status = 2;
        }
        
        $transaction->save();

        Wallet::whereTransactionId($transaction->id)->update(['win_and_game_total_amount' => $user->total_wallet_amount, 'status' => $walletStatus, 'remark' => "Deposit Fund Via Cashfree Payment Gateway (Order id : {$transactionId})"]);

        return response()->json(['status' => 'success']);
    }
}
