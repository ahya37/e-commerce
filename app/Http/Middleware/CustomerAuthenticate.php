<?php

namespace App\Http\Middleware;

use Closure;

class CustomerAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // jika guard customer belum login
        if (!auth()->guard('customer')->check()) {
            // maka redirect ke halaman login
            return redirect(route('customer.login'));
        }
        return $next($request);
    }
}
