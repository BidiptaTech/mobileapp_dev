<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class EnsureUserIsDmc
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

        if (!($user instanceof User)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a DMC.',
            ], 403);
        }

        $request->merge(['dmc' => $user]);

        return $next($request);
    }
}
