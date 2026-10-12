<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Agent;

class EnsureUserIsAgent
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

        if (!($user instanceof Agent)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not an agent.',
            ], 403);
        }

        $request->merge(['agent' => $user]);

        return $next($request);
    }
}
