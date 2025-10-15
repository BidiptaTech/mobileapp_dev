<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Driver;
use App\Models\Guide;
use App\Models\Guest;
use App\Models\Jobsheet;
use App\Models\Tour;
use App\Models\Vehicle;


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

            // Fetch jobsheets for this driver with vehicle information
            $jobsheets = Jobsheet::whereNotNull('driver_id')
                ->where('driver_id', $driver->id)
                ->get();

            // Add vehicle information to each jobsheet
            $jobsheetsWithVehicles = $jobsheets->map(function ($jobsheet) {
                $jobsheetData = $jobsheet->toArray();
                
                // Get vehicle details if vehicle_id exists
                if ($jobsheet->vehicle_id) {
                    $vehicle = Vehicle::select([
                        'id', 'vehicle_id', 'vehicle_name', 'vehicle_type', 'vehicle_model', 
                        'model_year', 'image', 'description', 'seating_capacity', 'vehicle_icon', 
                        'is_available', 'dmc_id', 'driver_id', 'is_active', 'created_by'
                    ])
                    ->where('vehicle_id', $jobsheet->vehicle_id)
                    ->first();
                    
                    $jobsheetData['vehicle'] = $vehicle;
                } else {
                    $jobsheetData['vehicle'] = null;
                }
                
                return $jobsheetData;
            });

            // Generate Sanctum token
            $token = $driver->createToken('driver-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Driver authenticated successfully',
                'data' => [
                    'driver' => $driver,    
                    'jobsheets' => $jobsheetsWithVehicles,
                    'total_jobsheets' => $jobsheetsWithVehicles->count(),
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

            // Authenticate guest (exclude timestamps from query)
            $guest = Guest::select([
                'id', 'guest_id', 'tour_id', 'guest_name', 'email', 
                'contact', 'country_code', 'app_password'
            ])
                ->where('email', $email)
                ->where('app_password', $password)
                ->first();

            if (!$guest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guest not found or invalid credentials',
                ], 404);
            }

            // First, get all tours for this guest's tour_id (exclude timestamps)
            $tours = Tour::select([
                'id', 'unique_tour_id', 'destination', 'adult', 'child', 
                'check_in_time', 'check_out_time', 'infant', 'agent_id', 
                'male_count', 'female_count', 'child_ages', 'tour_id', 
                'hotel', 'attraction', 'travel', 'restaurent', 'guide', 
                'status', 'port', 'assign_guide_id', 'display_id', 
                'assign_driver_id', 'payment_details', 'is_approve', 
                'tour_status', 'city', 'dmc_id', 'multi_enq_id', 'auto_cancel_date'
            ])
                ->where('tour_id', $guest->tour_id)
                ->get();
            
            // Then map over tours and get all orders for each tour
            $toursWithOrders = $tours->map(function ($tour) {
                // Get all orders for this tour (without the tour relationship and timestamps)
                $orders = Order::select([
                    'id', 'agent_id', 'tour_id', 'data', 'type', 'status', 
                    'booking_id', 'reference_id', 'invoice_pdf', 'bookingType', 
                    'discount', 'markup_percentage', 'cancel_reason', 'approval_file', 
                    'is_approve', 'approval_id', 'actual_due_date', 'display_due_date', 
                    'voucher_image', 'upload_files'
                ])
                    ->without('tour')
                    ->where('tour_id', $tour->tour_id)
                    ->get();
                
                // Add orders to tour object
                $tourData = $tour->toArray();
                $tourData['orders'] = $orders;
                $tourData['total_orders'] = $orders->count();
                
                return $tourData;
            });

            // Generate Sanctum token
            $token = $guest->createToken('guest-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Guest authenticated successfully',
                'data' => [
                    'guest' => $guest,
                    'tours' => $toursWithOrders,
                    'total_tours' => $toursWithOrders->count(),
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
