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

            // Generate Sanctum token
            $token = $driver->createToken('driver-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Driver authenticated successfully',
                'data' => [
                    'driver' => $driver,
                    'token' => $token,
                    'role' => 'driver'
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

            // Authenticate guide with selected fields
            $guide = Guide::select([
                'guide_id', 'name', 'email', 'contact_no', 'description', 'image', 
                'deleted_at', 'created_at', 'updated_at', 'is_active', 'government_license_no', 
                'license_image', 'license_exp_date', 'certified', 'experience_years', 
                'service_type', 'approval', 'close_days', 'close_dates', 'salutation', 
                'city', 'country', 'created_by', 'status', 'dmc_id', 'guide_gender', 'guide_age'
            ])
                ->where('email', $email)
                ->where('app_password', $password)
                ->first();

            if (!$guide) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guide not found or invalid credentials',
                ], 404);
            }

            // Debug: Check if guide_id exists
            \Log::info('Guide ID: ' . ($guide->guide_id ?? 'NULL'));
            \Log::info('Guide getKey(): ' . $guide->getKey());
            
            // Generate Sanctum token
            $token = $guide->createToken('guide-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Guide authenticated successfully',
                'data' => [
                    'guide' => $guide,
                    'token' => $token,
                    'role' => 'guide'
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
     * Get driver jobsheets
     */
    public function getDriverJobsheets(Request $request)
    {
        try {
            $driver_id = $request->driver_id; // from middleware
            $auth_user = auth()->user();

            if($auth_user->driver_id != $driver_id){
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Fetch jobsheets for this driver with vehicle information
            $jobsheets = Jobsheet::whereNotNull('driver_id')
                ->where('driver_id', $driver_id)
                ->get();

            // Add vehicle information to each jobsheet
            $jobsheetsWithVehicles = $jobsheets->map(function ($jobsheet) {
                $jobsheetData = $jobsheet->toArray();
                
                // Get vehicle details if vehicle_id exists
                if ($jobsheet->vehicle_id) {
                    $vehicle = Vehicle::select([
                        'id', 'vehicle_id', 'vehicle_name', 'vehicle_type', 'vehicle_model', 
                        'model_year', 'image', 'description', 'seating_capacity', 'vehicle_icon', 
                        'is_available', 'dmc_id', 'driver_id', 'created_by'
                    ])
                    ->where('vehicle_id', $jobsheet->vehicle_id)
                    ->first();
                    
                    $jobsheetData['vehicle'] = $vehicle;
                } else {
                    $jobsheetData['vehicle'] = null;
                }
                
                return $jobsheetData;
            });

            return response()->json([
                'success' => true,
                'message' => 'Driver jobsheets retrieved successfully',
                'data' => [
                    'jobsheets' => $jobsheetsWithVehicles,
                    'total_jobsheets' => $jobsheetsWithVehicles->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving driver jobsheets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get guide jobsheets
     */
    public function getGuideJobsheets(Request $request)
    {
        try {
            $guide_id = $request->guide_id; // from middleware
            $auth_user = auth()->user();
            if($auth_user->guide_id != $guide_id){
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Fetch jobsheets for this guide with vehicle information
            $jobsheets = Jobsheet::whereNotNull('guide_id')
                ->where('guide_id', $guide_id)
                ->get();

            // Add vehicle information and order hours to each jobsheet
            $jobsheetsWithVehicles = $jobsheets->map(function ($jobsheet) {
                $jobsheetData = $jobsheet->toArray();
                
                // Get vehicle details if vehicle_id exists
                if ($jobsheet->vehicle_id) {
                    $vehicle = Vehicle::select([
                        'id', 'vehicle_id', 'vehicle_name', 'vehicle_type', 'vehicle_model', 
                        'model_year', 'image', 'description', 'seating_capacity', 'vehicle_icon', 
                        'is_available', 'dmc_id', 'driver_id', 'created_by'
                    ])
                    ->where('vehicle_id', $jobsheet->vehicle_id)
                    ->first();
                    
                    $jobsheetData['vehicle'] = $vehicle;
                } else {
                    $jobsheetData['vehicle'] = null;
                }
                
                // Get order hours if order_id exists
                
                if ($jobsheet->order_id) {
                    $order = Order::select('data')
                        ->where('booking_id', $jobsheet->order_id)
                        ->first();
                        
                    
                    if ($order && $order->data) {
                        $orderData = is_string($order->data) ? json_decode($order->data, true) : $order->data;
                        
                        // Extract hours from order data
                        if (isset($orderData[0]['hours'])) {
                            $jobsheetData['hours'] = $orderData[0]['hours'];
                        } else {
                            $jobsheetData['hours'] = null;
                        }
                    } else {
                        $jobsheetData['hours'] = null;
                    }
                } else {
                    $jobsheetData['hours'] = null;
                }
                
                return $jobsheetData;
            });

            return response()->json([
                'success' => true,
                'message' => 'Guide jobsheets retrieved successfully',
                'data' => [
                    'jobsheets' => $jobsheetsWithVehicles,
                    'total_jobsheets' => $jobsheetsWithVehicles->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving guide jobsheets',
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
                'contact', 'country_code'
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

            // Get all tours for this guest's tour_id to calculate counts
            $tours = Tour::where('tour_id', $guest->tour_id)->get();
            
            $now = now();
            
            // Calculate tour counts based on check_in_time and check_out_time
            $pastTours = $tours->filter(function ($tour) use ($now) {
                return $tour->check_out_time && $tour->check_out_time < $now;
            })->count();
            
            $ongoingTours = $tours->filter(function ($tour) use ($now) {
                return $tour->check_in_time && $tour->check_out_time && 
                       $tour->check_in_time <= $now && $tour->check_out_time >= $now;
            })->count();
            
            $upcomingTours = $tours->filter(function ($tour) use ($now) {
                return $tour->check_in_time && $tour->check_in_time > $now;
            })->count();
            
            $totalTours = $tours->count();
            
            // Create tour counts object
            $tourCounts = [
                'past' => $pastTours,
                'ongoing' => $ongoingTours,
                'upcoming' => $upcomingTours,
                'total' => $totalTours
            ];

            // Generate Sanctum token
            $token = $guest->createToken('guest-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Guest authenticated successfully',
                'data' => [
                    'guest' => $guest,
                    'tour_counts' => $tourCounts,
                    'token' => $token,
                    'role' => 'guest'
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
    
    public function getGuestBookings(Request $request){
        $guest = $request->guest; // from middleware
        $status_type = $request->status_type;
        $now = now();
        
        // Get tours based on status_type using check_in_time and check_out_time
        $toursQuery = Tour::where('tour_id', $guest->tour_id);
        
        if($status_type == 'past'){
            $toursQuery->where('check_out_time', '<', $now);
        } else if($status_type == 'ongoing'){
            $toursQuery->where('check_in_time', '<=', $now)
                      ->where('check_out_time', '>=', $now);
        } else if($status_type == 'upcoming'){
            $toursQuery->where('check_in_time', '>', $now);
        }
        
        $tours = $toursQuery->get();
        
        // Map over tours and get all orders for each tour
        $toursWithOrders = $tours->map(function ($tour) {
            // Get all orders for this tour (without the tour relationship to avoid duplication)
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
        
        return response()->json([
            'success' => true,
            'message' => 'Guest bookings retrieved successfully',
            'data' => [
                'tours' => $toursWithOrders,
                'total_tours' => $toursWithOrders->count()
            ]
        ], 200);
    }

    function updateGuest(Request $request){
        $guest = $request->guest; // from middleware
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
        $driver = $request->driver; // from middleware
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
        $guide = $request->guide; // from middleware
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
