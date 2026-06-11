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

        if (!($user instanceof Guest)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a guest.',
            ], 403);
        }

        $request->merge(['guest' => $user]);

        return $next($request);
    }
}