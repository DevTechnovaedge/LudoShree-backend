<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveUser
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
        if (request()->is('api/*')):
            if (!auth('api')->check() ||  auth('api')->user()->status != 1):
                return response(['status' => false, 'message' => 'User not active']);
            endif;
        endif;

        return $next($request);
    }
}
