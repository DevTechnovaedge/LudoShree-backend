<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class HaodaPay
{
    protected $client_id        = env('HAODA_PAY_CLIENT_ID');
    protected $client_secret    = env('HAODA_PAY_CLIENT_SECRET');
    protected $base_url         = env('HAODA_PAY_BASE_URL');
    
    # Validate UPI
    public function validateUpi($record){
        
        # Records
            $vpa            =   $record->vpa;   # 7231876076@ptsbi
        # End Records
        
        $response = Http::withHeaders([
                                        'x-client-id'           => $this->client_id,
                                        'x-client-secret'       => $this->client_secret
                                        ])
                                        ->post("$this->base_url/upi/validate", [ 'vpa' => $vpa ]);
                                        
        return $response;
    }
    # End Validate UPI
    
    # Withdrawal Via Bank
    public function withdrawalViaBank($data){
        
        # Records
            $account_number                 =   $data->account_number;          # 31808100003035
            $account_ifsc                   =   $data->account_ifsc;            # BARB0SANJAI
            $bankname                       =   $data->bankname;                # HOADA Test Bank
            $confirm_acc_number             =   $data->confirm_acc_number;      # 31808100003035
            $requesttype                    =   $data->requesttype;             # IMPS
            $beneficiary_name               =   $data->beneficiary_name;        # HOADA Test User
            $amount                         =   $data->amount;                  # 10
            $narration                      =   $data->narration;               # Test bank transaction
            $reference                      =   $data->reference;               # test123
        # End Records
        
        $response = Http::withHeaders([
                                        'x-client-id'           => $this->client_id,
                                        'x-client-secret'       => $this->client_secret
                                        ])
                                        ->post("$this->base_url/payout/initiate", [
                                                                                    'account_number'        => $account_number, 
                                                                                    'account_ifsc'          => $account_ifsc, 
                                                                                    'bankname'              => $bankname, 
                                                                                    'confirm_acc_number'    => $confirm_acc_number, 
                                                                                    'requesttype'           => $requesttype, 
                                                                                    'beneficiary_name'      => $beneficiary_name, 
                                                                                    'amount'                => $amount, 
                                                                                    'narration'             => $narration, 
                                                                                    'reference'             => $reference, 
                                                                                ]);
                                        
        return $response;
    }
    # End Withdrawal Via Bank
    
    # Withdrawal Via UPI
    public function withdrawalViaUPI($data){
        
        # Records
            $vpa                            =   $data->vpa;                     # 7231876076@ptsbi
            $beneficiary_name               =   $data->beneficiary_name;        # Vikas Gowardhn Mehra
            $amount                         =   $data->amount;                  # 1
            $narration                      =   $data->narration;               # Testing
            $reference                      =   $data->reference;               # Haoda01
        # End Records
        
        $response = Http::withHeaders([
                                        'x-client-id'           => $this->client_id,
                                        'x-client-secret'       => $this->client_secret
                                        ])
                                        ->post("$this->base_url/upi/payout/initiate", [
                                                                                    'vpa'                   => $vpa, # '7231876076@ptsbi', 
                                                                                    'beneficiary_name'      => $beneficiary_name, # 'HOADA Test User', 
                                                                                    'amount'                => $amount, # '10', 
                                                                                    'narration'             => $narration, # 'Test bank transaction', 
                                                                                    'reference'             => $reference # 'test123', 
                                                                                ]);
                                        
        return $response;
    }
    # End Withdrawal Via UPI
    
    #   Check Status
    public function checkTransactionStatus($data){
        
        # Records
            $payout_id                            =   $data->payout_id;
        # End Records
        
        $response = Http::withHeaders([
                                        'x-client-id'           => $this->client_id,
                                        'x-client-secret'       => $this->client_secret
                                        ])
                                        ->post("$this->base_url/upi/payout/initiate", [
                                                                                        'payout_id'                   => $payout_id     #'HOAD974138602500'
                                                                                    ]);
                                        
        return $response;
    }
    # End Check Status
}
