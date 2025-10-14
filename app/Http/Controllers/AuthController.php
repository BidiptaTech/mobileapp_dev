<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Driver;
use App\Models\Guide;
use App\Models\Guest;
use App\Models\Jobsheet;


class AuthController extends Controller
{
    /**
     * Main login function - routes to appropriate login method
     */
    public function login(Request $request)
    {
    
        try {
            
            $email = $request->email;
            $password = $request->password;

            if (!$email || !$password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email and password are required',
                ], 400);
            }

            // Route to appropriate login function
            if (Driver::where('email', $email)->where('app_password', $password)->first()) {
                return $this->driverLogin($request);
            } elseif (Guide::where('email', $email)->where('app_password', $password)->first()) {
                return $this->guideLogin($request);
            } elseif (Guest::where('email', $email)->where('app_password', $password)->first()) {
                return $this->guestLogin($request);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No user found with this email and password',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during authentication',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Driver login function
     */
    public function driverLogin(Request $request)
    {
        $email = $request->email;
        $password = $request->password;

        try {
            // Validate input
            if (!$email || !$password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email and password are required',
                ], 400);
            }

            // Authenticate driver
            $driver = Driver::where('email', $email)
                ->where('app_password', $password)
                ->first();

            if (!$driver) {
                return response()->json([
                    'success' => false,
                    'message' => 'Driver not found or invalid credentials',
                ], 404);
            }

            // Fetch jobsheets for this driver
            $jobsheets = Jobsheet::whereNotNull('driver_id')
                ->where('driver_id', $driver->id)
                ->get();

            // Generate Sanctum token
            $token = $driver->createToken('driver-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Driver authenticated successfully',
                'data' => [
                    'driver' => $driver,    
                    'jobsheets' => $jobsheets,
                    'total_jobsheets' => $jobsheets->count(),
                    'token' => $token
                ] 
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during driver authentication',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guide login function
     */
    public function guideLogin(Request $request)
    {
        $email = $request->email;
        $password = $request->password;

        try {
            // Validate input
            if (!$email || !$password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email and password are required',
                ], 400);
            }

            // Authenticate guide
            $guide = Guide::where('email', $email)
                ->where('app_password', $password)
                ->first();

            if (!$guide) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guide not found or invalid credentials',
                ], 404);
            }

            // Fetch jobsheets for this guide
            $jobsheets = Jobsheet::whereNotNull('guide_id')
                ->where('guide_id', $guide->id)
                ->get();

            // Generate Sanctum token
            $token = $guide->createToken('guide-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Guide authenticated successfully',
                'data' => [
                    'guide' => $guide,
                    'jobsheets' => $jobsheets,
                    'total_jobsheets' => $jobsheets->count(),
                    'token' => $token
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during guide authentication',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guest login function
     */
    public function guestLogin(Request $request)
    {
        $email = $request->email;
        $password = $request->password;

        try {
            // Validate input
            if (!$email || !$password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email and password are required',
                ], 400);
            }

            // Authenticate guest
            $guest = Guest::where('email', $email)
                ->where('app_password', $password)
                ->first();

            if (!$guest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guest not found or invalid credentials',
                ], 404);
            }

            // Fetch orders for this guest's tour_id
            $orders = Order::whereNotNull('tour_id')
                ->where('tour_id', $guest->tour_id)
                ->get();

            // Generate Sanctum token
            $token = $guest->createToken('guest-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Guest authenticated successfully',
                'data' => [
                    'guest' => $guest,
                    'bookings' => $orders,
                    'total_bookings' => $orders->count(),
                    'token' => $token
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during guest authentication',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    function updateGuest(Request $request){
        $guest = Guest::where('guest_id', $request->id)->first();
        $password = $request->password;
        $guest->app_password = $password;
        $guest->save();
        return response()->json([
            'success' => true,
            'message' => 'Guest updated successfully',
            'data' => $guest->refresh()
        ], 200);
    }

    function updateDriver(Request $request){
        $driver = Driver::where('driver_id', $request->id)->first();
        $password = $request->password;
        $driver->app_password = $password;
        $driver->save();
        return response()->json([
            'success' => true,
            'message' => 'Driver updated successfully',
            'data' => $driver->refresh()
        ], 200);
    }

    function updateGuide(Request $request){
        $guide = Guide::where('guide_id', $request->id)->first();
        $password = $request->password;
        $guide->app_password = $password;
        $guide->save();
        return response()->json([
            'success' => true,
            'message' => 'Guide updated successfully',
            'data' => $guide->refresh()
        ], 200);
    }

    function updateJobsheetStatus(Request $request){
        $jobsheet = Jobsheet::where('jobsheet_id', $request->id)->first();
        $jobsheet->current_status = $request->status;
        $jobsheet->save();
        return response()->json([
            'success' => true,
            'message' => 'Jobsheet updated successfully',
            'data' => $jobsheet->refresh()
        ], 200);
    }

}
