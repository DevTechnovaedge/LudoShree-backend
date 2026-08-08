<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'rozarpay/callback',
        'rozarpay/callback/',
        '/rozarpay/callback',
        '/rozarpay/callback/',

        'cashfree/callback',
        'cashfree/callback/',
        '/cashfree/callback',
        '/cashfree/callback/',

        'upigateway/webhook',
        'upigateway/webhook/',
        '/upigateway/webhook',
        '/upigateway/webhook/',

        'upigateway/return',
        'upigateway/return/',
        '/upigateway/return',
        '/upigateway/return/',
    ];
}
