<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CrossOriginOpenerPolicy
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        return $response;
    }
}

