<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Restaurant;
use App\Models\City;
use App\Models\Country;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class RestaurantController extends Controller
{

    public function registerRestaurant(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ]);
    }

    /**
     * Restaurant login with Sanctum token authentication.
     */
    public function restaurantLogin(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $restaurant = Restaurant::where('email', $request->email)->first();
            
            if (!$restaurant || !Hash::check($request->password, $restaurant->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // Revoke existing tokens (optional: single device login) or create new token
            $restaurant->tokens()->where('name', 'restaurant-token')->delete();
            $token = $restaurant->createToken('restaurant-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Restaurant logged in successfully',
                'data' => $restaurant,
                'token' => $token,
                'role' => 'restaurant',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error logging in restaurant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restaurant logout - revokes current Sanctum token.
     */
    public function restaurantLogout(Request $request)
    {
        try {
            $restaurant = $request->user();

            if (!$restaurant || !($restaurant instanceof Restaurant)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not authenticated',
                ], 401);
            }

            // Revoke the current access token
            $restaurant->tokens()->where('name', 'restaurant-token')->delete();
            $token = $restaurant->createToken('restaurant-token')->plainTextToken;

            return response()->json(['success' => true, 'message' => 'Restaurant logged out successfully', 'data' => $restaurant, 'token' => $token], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during logout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}