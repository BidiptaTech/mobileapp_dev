<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
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
            $dmcIds = is_array($restaurant->dmc_id)
                ? $restaurant->dmc_id
                : json_decode($restaurant->dmc_id, true);
            $users = User::whereIn('userId', $dmcIds)
                ->select('userId', 'name', 'email')
                ->get();
            $dmcDetails = $users->toArray();
            $restaurant_data = array(
                'restaurant_id' => $restaurant->restaurant_id,
                'restaurant_name' => $restaurant->name,
                'restaurant_email' => $restaurant->email,
                'dmcDetails' => $dmcDetails
            );

            return response()->json([
                'success' => true,
                'message' => 'Restaurant logged in successfully',
                'data' => $restaurant_data,
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
        $filter = $request->header('Type');

        if (!in_array($filter, ['past', 'upcoming', 'ongoing'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'The Type header is required and must be one of: past, upcoming, ongoing',
            ], 422);
        }

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
        $filtered = collect();

        foreach ($orders as $order) {
            $data = is_array($order->data) ? $order->data : json_decode($order->data, true);
            $bookingDate = $data[0]['bookingDate'] ?? null;
            $dmcId = $data[0]['dmc_id'] ?? null;
            $dmc = User::select('name', 'userId', 'email')->where('userId', $dmcId)->first();
            if (!$bookingDate) {
                continue;
            }

            $date = Carbon::parse($bookingDate)->startOfDay();

            $match = match ($filter) {
                'past' => $date->lt($today),
                'ongoing' => $date->isSameDay($today),
                'upcoming' => $date->gt($today),
                default => false,
            };

            if ($match) {
                $order->dmc = $dmc;
                $filtered->push($order);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Restaurant orders fetched successfully',
            'data' => $filtered->values(),
        ], 200);
    }

    /**
     * Redeem voucher code - marks the order as redeemed.
     * Only the restaurant that owns the order can redeem it.
     */
    public function redeemVoucherCode(Request $request)
    {
        $request->validate([
            'order_id' => 'required',
            'restaurant_id' => 'required',
        ]);

        $restaurant = Restaurant::where('restaurant_id', $request->restaurant_id)->first();
        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found',
            ], 404);
        }

        $order = Order::where('booking_id', $request->order_id)->where('type', 'restaurant')->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // Verify the order belongs to this restaurant
        $data = is_array($order->data) ? $order->data : json_decode($order->data, true);
        $orderRestaurantId = $data[0]['restaurantId'] ?? null;
        if ((int) $orderRestaurantId !== (int) $restaurant->restaurant_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to redeem this order',
            ], 403);
        }

        // Check if already redeemed
        $redeemedColumn = Order::REDEEMED_COLUMN;
        if (!empty($order->{$redeemedColumn})) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher has already been redeemed',
            ], 422);
        }

        $order->update([
            $redeemedColumn => 1,
            Order::REDEEMED_BY_COLUMN => $restaurant->restaurant_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Voucher redeemed successfully',
        ], 200);
    }

    //DELETE rESTAURANTS ACCOUNT
    public function deleteRestaurantAccount(Request $request)
    {   
        try{
        $request->validate([
            'password' => 'required',
            'restaurant_id' => 'required',
        ]);
        $restaurant = Restaurant::where('restaurant_id', $request->restaurant_id)->first();
        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found',
            ], 404);
        }
        // Support both password and app_password; trim to fix whitespace/truncation
        $hashedPassword = $restaurant->password ?? '';

        if (empty($hashedPassword) || 
            !Hash::check($request->password, $hashedPassword)) {

            return response()->json([
                'success' => false,
                'message' => 'Incorrect password',
            ], 403);
        }
        // Soft delete (Restaurant model uses SoftDeletes - sets deleted_at)
        $restaurant->delete();
        return response()->json([
            'success' => true,
            'message' => 'Restaurant account deleted successfully',
        ], 200);
    }catch(\Exception $e){
        return response()->json([
            'success' => false,
            'message' => 'Error deleting restaurant account',
            'error' => $e->getMessage(),
        ], 500);
    }
}}