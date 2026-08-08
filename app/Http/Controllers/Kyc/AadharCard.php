<?php

namespace App\Http\Controllers\Kyc;

use App\Http\Controllers\Controller;

class AadharCard extends Controller
{
    // private const baseUrl = "https://sandbox.cashfree.com/verification/offline-aadhaar/otp";
    private const baseUrl = "https://api.cashfree.com/verification/offline-aadhaar/otp";

    public $cashfree_api_key = "";
    public $cashfree_api_secret = "";

    public function __construct()
    {
        $this->cashfree_api_key  = site_setting()->cashfree_api_key;
        $this->cashfree_api_secret  = site_setting()->cashfree_api_secret;
    }
}