<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        
        if (config('app.api_key') != $request->header('Api-Key') ) {
            return response(['status' => false, 'message' => 'Invalid API Key']);
        }
        return $next($request);
    }
}
