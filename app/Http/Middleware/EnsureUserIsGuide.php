<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Guide;

class EnsureUserIsGuide
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

        // Debug: Log the user type
        \Log::info('Guide middleware - User type: ' . get_class($user));
        \Log::info('Guide middleware - User ID: ' . ($user->id ?? 'NULL'));

        // Check if the authenticated user is actually a Guide
        if (!($user instanceof \App\Models\Guide)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a guide.',
            ], 403);
        }

        // Since we're using Guide model directly for authentication,
        // the authenticated user IS the guide
        $guide = $user;

        $request->merge(['guide' => $guide]);

        return $next($request);
    }
}