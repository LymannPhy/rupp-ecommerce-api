<?php

namespace App\Http\Middleware;

use Closure; 

class RoleMiddleware
{
    public function handle($request, Closure $next, $role)
    {
        if (!auth()->user()->hasRole($role)) {
            return response()->json(['error' => 'Forbidden - Insufficient Role'], 403);
        }

        return $next($request);
    }

}
