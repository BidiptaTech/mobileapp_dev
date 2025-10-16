<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Driver;

class EnsureUserIsDriver
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // If no user logged in (token invalid)
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: No authenticated user found.',
            ], 401);
        }

        // Debug: Log the user type
        \Log::info('Driver middleware - User type: ' . get_class($user));
        \Log::info('Driver middleware - User ID: ' . ($user->id ?? 'NULL'));

        // Check if the authenticated user is actually a Driver
        if (!($user instanceof \App\Models\Driver)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a driver.',
            ], 403);
        }

        // Since we're using Driver model directly for authentication,
        // the authenticated user IS the driver
        $driver = $user;

        // Attach driver to request (optional, for convenience)
        $request->merge(['driver' => $driver]);

        return $next($request);
    }
}