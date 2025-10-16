<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Guest;

class EnsureUserIsGuest
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: No authenticated user found.',
            ], 401);
        }

        // Since we're using Guest model directly for authentication,
        // the authenticated user IS the guest
        $guest = $user;

        $request->merge(['guest' => $guest]);

        return $next($request);
    }
}