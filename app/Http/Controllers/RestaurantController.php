<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\City;
use App\Models\Country;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
    public function getRestaurantDetails(Request $request)
    {
        $restaurant = $request->user();
        $restaurantDetails = Restaurant::where('restaurant_id', $restaurant->restaurant_id)->first();
        if (!$restaurantDetails) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Restaurant details fetched successfully',
            'data' => $restaurantDetails,
        ], 200);
    }

    /**
     * Get all orders where type is 'restaurant' and data includes the current restaurant_id.
     */
    public function getRestaurantOrders(Request $request)
    {
        $restaurant = $request->user();

        if (!$restaurant || !($restaurant instanceof Restaurant)) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not authenticated',
            ], 401);
        }

        $restaurantId = (int) $restaurant->restaurant_id;

        $orders = Order::where('type', 'restaurant')
            ->where(function ($query) use ($restaurantId) {
                $patterns = [
                    '%"restaurantId":' . $restaurantId . ',%',
                    '%"restaurantId":' . $restaurantId . '}%',
                    '%"restaurantId": ' . $restaurantId . ',%',
                    '%"restaurantId": ' . $restaurantId . '}%',
                ];
                // PostgreSQL: cast JSON to text for LIKE; MySQL: LIKE works on JSON directly
                $dataColumn = DB::connection()->getDriverName() === 'pgsql' ? '(data::text)' : 'data';
                $query->whereRaw(
                    "({$dataColumn} LIKE ? OR {$dataColumn} LIKE ? OR {$dataColumn} LIKE ? OR {$dataColumn} LIKE ?)",
                    $patterns
                );
            })
            ->orderByDesc('created_at')
            ->get();

        $today = Carbon::today();

        $past = collect();
        $ongoing = collect();
        $upcoming = collect();

        foreach ($orders as $order) {
            $data = is_array($order->data) ? $order->data : json_decode($order->data, true);
            $bookingDate = $data[0]['bookingDate'] ?? null;

            if (!$bookingDate) {
                continue;
            }

            $date = Carbon::parse($bookingDate)->startOfDay();

            if ($date->lt($today)) {
                $past->push($order);
            } elseif ($date->isSameDay($today)) {
                $ongoing->push($order);
            } else {
                $upcoming->push($order);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Restaurant orders fetched successfully',
            'data' => [
                'past' => $past->values(),
                'ongoing' => $ongoing->values(),
                'upcoming' => $upcoming->values(),
            ],
        ], 200);
    }

}