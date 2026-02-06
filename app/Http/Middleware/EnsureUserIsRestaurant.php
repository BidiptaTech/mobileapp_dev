<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsRestaurant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: No authenticated user found.',
            ], 401);
        }

        if (!($user instanceof \App\Models\Restaurant)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a restaurant.',
            ], 403);
        }

        $request->merge(['restaurant' => $user]);

        return $next($request);
    }
}
