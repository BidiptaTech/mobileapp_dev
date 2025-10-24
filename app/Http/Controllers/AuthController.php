<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Order;
use App\Models\Driver;
use App\Models\Guide;
use App\Models\Guest;
use App\Models\Jobsheet;
use App\Models\Tour;
use App\Models\Vehicle;
use App\Models\City;
use App\Models\Hotel;
use App\Models\Attraction;
use App\Models\Restaurant;
use App\Models\AppManagement;
use Illuminate\Support\Facades\DB;

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
            $userType = $request->user_type;
            if (!$email || !$password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email and password are required',
                    'message' => 'Email and password are required',
                ], 400);
            }

            // Route to appropriate login function
            $driver = Driver::where('email', $email)->first();
            if ($driver && Hash::check($password, $driver->app_password) && $userType == 'driver') {
                return $this->driverLogin($request);
            }
            
            $guide = Guide::where('email', $email)->first();
            
            if ($guide && Hash::check($password, $guide->app_password) && $userType == 'guide') {
                return $this->guideLogin($request);
            }
            
            $guest = Guest::where('email', $email)->first();
            if ($guest && Hash::check($password, $guest->app_password) && $userType == 'guest') {
                return $this->guestLogin($request);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No user found with this email and password',
            ], 400);
            
            return response()->json([
                'success' => false,
                'message' => 'No user found with this email and password',
            ], 400);

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
            $driver = Driver::where('email', $email)->first();

            if (!$driver || !Hash::check($password, $driver->app_password)) {
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
                'city', 'country', 'created_by', 'status', 'dmc_id', 'guide_gender', 'guide_age',
                'app_password'
            ])
                ->where('email', $email)
                ->first();

            if (!$guide || !Hash::check($password, $guide->app_password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guide not found or invalid credentials',
                ], 404);
            }

            // Debug: Check if guide_id exists
            \Log::info('Guide ID: ' . ($guide->guide_id ?? 'NULL'));
            \Log::info('Guide getKey(): ' . $guide->getKey());
            
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
            $jobsheets = Jobsheet::select('jobsheet_id', 'driver_id', 'tour_id', 'order_id', 'vehicle_id', 'current_status','date', 'data', 'type', 'service_type', 'journey_time','driver_id')->whereNotNull('driver_id')
                ->where('date', '=', today())
                ->where('driver_id', $driver_id)
                ->get();
            $tourIds = $jobsheets
                ->pluck('tour_id')   // get all tour IDs
                ->unique()           // keep only unique ones
                ->values();

            // Add vehicle information to each jobsheet
            $jobsheetsWithVehicles = $jobsheets->map(function ($jobsheet) {
                $jobsheetData = $jobsheet->toArray();
                
                // Get order data if order_id exists
                if ($jobsheet->order_id) {
                    $order = Order::select('data')->where('booking_id', $jobsheet->order_id)->first();
                    
                    if ($order && $order->data) {
                        $orderData = is_string($order->data) ? json_decode($order->data, true) : $order->data;
                        
                        if (isset($orderData[0])) {
                            $data = $orderData[0];
                            
                            // Extract only the specified driver fields
                            $jobsheetData['order_details'] = [
                                'bookingDate' => $data['bookingDate'] ?? null,
                                'image' => $data['image'] ?? null,
                                'type' => $data['type'] ?? null,
                                'entrypickup' => $data['entrypickup'] ?? null,
                                'entrydropoff' => $data['entrydropoff'] ?? null,
                                'PickupPlaceid' => $data['PickupPlaceid'] ?? null,
                                'DropoffPlaceid' => $data['DropoffPlaceid'] ?? null,
                                'pickupdate' => $data['pickupdate'] ?? null,
                                'entrytime' => $data['entrytime'] ?? null,
                                'selectedHours' => $data['selectedHours'] ?? null,
                                'adults' => $data['adults'] ?? null,
                                'children' => $data['children'] ?? null,
                                'distance' => $data['distance'] ?? null,
                                'Night_Start_Time' => $data['Night_Start_Time'] ?? null,
                                'Night_End_Time' => $data['Night_End_Time'] ?? null,
                                'city' => $data['city'] ?? null,
                                'country' => $data['country'] ?? null,
                            ];
                        } else {
                            $jobsheetData['order_details'] = null;
                        }
                    } else {
                        $jobsheetData['order_details'] = null;
                    }
                } else {
                    $jobsheetData['order_details'] = null;
                }
                
                // Get vehicle details if vehicle_id exists
                if ($jobsheet->vehicle_id) {
                    $vehicle = Vehicle::select([
                        'vehicle_id', 'vehicle_name', 'vehicle_type', 'vehicle_model', 
                        'model_year', 'image', 'description', 'seating_capacity', 'vehicle_icon', 
                        'is_available'
                    ])
                    ->where('vehicle_id', $jobsheet->vehicle_id)
                    ->first();
                    
                    $jobsheetData['vehicle'] = $vehicle;
                } else {
                    $jobsheetData['vehicle'] = null;
                }
                
                return $jobsheetData;
            });

            $tourIds = $jobsheets->pluck('tour_id')->unique()->values();
            $customer_info = [];
            foreach($tourIds as $tourId){
                $firstOrder = Order::select('data')->where('tour_id', $tourId)->first();
                $orderData = is_string($firstOrder->data) ? json_decode($firstOrder->data, true) : $firstOrder->data;
                if($orderData && isset($orderData[0])){
                    $share_status = null; // default

                    if (!empty($orderData[0]['email'])) {
                        $guest = Guest::where('tour_id', $tourId)
                            ->where('email', $orderData[0]['email'])
                            ->first();

                        $share_status = $guest?->share_status; // null-safe access
                    }
                    $customer_info[$tourId] = [
                        'name' => $orderData[0]['fullName'] ?? '',
                        'email' => $orderData[0]['email'] ?? '',
                        'isContactShared' => $share_status,
                        'phone' => $share_status == 1 ? $orderData[0]['phone'] : 'Not Shared',
                        'address' => $orderData[0]['address1'] ?? '',
                        'state' => $orderData[0]['state'] ?? '',
                        'zip' => $orderData[0]['zip'] ?? ''
                    ];
                }
                else{
                    $customer_info[$tourId] = null;
                }
            }
            return response()->json([
                'success' => true,
                'message' => 'Driver jobsheets retrieved successfully',
                'data' => [
                    'jobsheets' => $jobsheetsWithVehicles,
                    'customer_info' => $customer_info,
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
            $jobsheets = Jobsheet::select('jobsheet_id', 'tour_id', 'order_id', 'current_status','date', 'data', 'type', 'service_type', 'journey_time', 'guide_id')->whereNotNull('guide_id')
                ->where('guide_id', $guide_id)->where('date', '=', today())
                ->get();
            

            // Add vehicle information and order details to each jobsheet
            $guideJobsheets = $jobsheets->map(function ($jobsheet) {
                $jobsheetData = $jobsheet->toArray();
                
                // Get order data if order_id exists
                if ($jobsheet->order_id) {
                    $order = Order::select('data')
                        ->where('booking_id', $jobsheet->order_id)
                        ->first();
                    
                    if ($order && $order->data) {
                        $orderData = is_string($order->data) ? json_decode($order->data, true) : $order->data;
                        
                        if (isset($orderData[0])) {
                            $data = $orderData[0];
                            
                            // Extract only the specified fields
                            $jobsheetData['order_details'] = [
                                'bookingDate' => $data['bookingDate'] ?? null,
                                'entrypickup' => $data['entrypickup'] ?? null,
                                'pickupdate' => $data['pickupdate'] ?? null,
                                'entrytime' => $data['entrytime'] ?? null,
                                'adults' => $data['adults'] ?? null,
                                'children' => $data['children'] ?? null,
                                'hours' => $data['hours'] ?? null,
                                
                            ];
                        } else {
                            $jobsheetData['order_details'] = null;
                        }
                    } else {
                        $jobsheetData['order_details'] = null;
                    }
                } else {
                    $jobsheetData['order_details'] = null;
                }
                
                return $jobsheetData;
            });

            $tourIds = $jobsheets->pluck('tour_id')->unique()->values();
            $customer_info = [];
            foreach($tourIds as $tourId){
                $firstOrder = Order::select('data')->where('tour_id', $tourId)->first();
                $orderData = is_string($firstOrder->data) ? json_decode($firstOrder->data, true) : $firstOrder->data;
                if($orderData && isset($orderData[0])){
                    $share_status = null; // default

                    if (!empty($orderData[0]['email'])) {
                        $guest = Guest::where('tour_id', $tourId)
                            ->where('email', $orderData[0]['email'])
                            ->first();

                        $share_status = $guest?->share_status; // null-safe access
                    }
                    $customer_info[$tourId] = [
                        'name' => $orderData[0]['fullName'],
                        'email' => $orderData[0]['email'],
                        'isContactShared' => $share_status,
                        'phone' => $share_status == 1 ? $orderData[0]['phone'] : 'Not Shared',
                        'address' => $orderData[0]['address1'],
                        'state' => $orderData[0]['state'],
                        'zip' => $orderData[0]['zip']
                    ];
                }
                else{
                    $customer_info[$tourId] = null;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Guide jobsheets retrieved successfully',
                'data' => [
                    'jobsheets' => $guideJobsheets,
                    'total_jobsheets' => $guideJobsheets->count(),
                    'customer_info' => $customer_info
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
                'contact', 'country_code', 'app_password'
            ])
                ->where('email', $email)
                ->first();

            if (!$guest || !Hash::check($password, $guest->app_password)) {
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

            $default_images = AppManagement::select('past_image', 'ongoing_image', 'upcoming_image')->first();

            // Generate Sanctum token
            $token = $guest->createToken('guest-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Guest authenticated successfully',
                'data' => [
                    'guest' => $guest,
                    'tour_counts' => $tourCounts,
                    'token' => $token,
                    'role' => 'guest',
                    'default_images' => $default_images
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
                'agent_id', 'tour_id', 'data', 'type', 'status', 
                'booking_id', 'reference_id', 'invoice_pdf', 'bookingType', 
                'discount', 'markup_percentage', 'cancel_reason', 'approval_file', 
                'is_approve', 'approval_id', 'actual_due_date', 'display_due_date', 
                'voucher_image', 'upload_files'
            ])
                ->without('tour')
                ->where('tour_id', $tour->tour_id)
                ->get();
            // Map orders to include hotel info
            $orders = $orders->map(function ($order) {

                // Make sure data is decoded as array
                $orderData = is_array($order->data) ? $order->data : json_decode($order->data, true);

                if ($order->type === 'hotel' && !empty($orderData) && isset($orderData[0]['hotelDetails']['hotel_id'])) {
                    $hotelId = $orderData[0]['hotelDetails']['hotel_id'];

                    // Fetch hotel from Hotel table
                    $hotel = Hotel::select('city')
                                ->where('hotel_unique_id', $hotelId)
                                ->first();

                    // Add hotel info to order
                    $order->city = $hotel->city ?? null; // fetched from Hotel table
                }
                elseif($order->type == 'attraction' && !empty($orderData) && isset($orderData[0]['AttractionId'])){
                    $attractionId = $orderData[0]['AttractionId'];
                    $attraction = Attraction::select('location')
                                ->where('attraction_id', $attractionId)
                                ->first();
                    $order->city = $attraction->location ?? null;
                }
                elseif(($order->type == 'travel_point' || $order->type == 'entry_port' || $order->type == 'exit_port' || $order->type == 'travel_hourly' || $order->type == 'local_transport') && !empty($orderData) && isset($orderData[0]['vehicles_id'])){
                    $vehiclesId = $orderData[0]['vehicles_id'];
                    $vehicles = Vehicle::select('city', 'driver_id')
                                ->where('vehicle_id', $vehiclesId)
                                ->first();
                    $order->city = $vehicles->city ?? null;
                    
                    // Get driver phone and name if driver_id exists
                    if ($vehicles && $vehicles->driver_id) {
                        $driver = Driver::select('phone', 'name')
                                    ->where('driver_id', $vehicles->driver_id)
                                    ->first();
                        $order->driver_phone = $driver->phone ?? null;
                        $order->driver_name = $driver->name ?? null;
                    }
                }
                elseif($order->type == 'restaurant' && !empty($orderData) && isset($orderData[0]['restaurantId'])){
                    $restaurantId = $orderData[0]['restaurantId'];
                    $restaurant = Restaurant::select('city')
                                ->where('restaurant_id', $restaurantId)
                                ->first();
                    $order->city = $restaurant->city ?? null;
                }
                elseif($order->type == 'guide' && !empty($orderData) && isset($orderData[0]['guide_id'])){
                    $guideId = $orderData[0]['guide_id'];
                    $guide = Guide::select('city', 'contact_no')
                                ->where('guide_id', $guideId)
                                ->first();
                    $order->city = $guide->city ?? null;
                    $order->guide_contact_no = $guide->contact_no ?? null;
                }
                elseif ($order->type == 'attraction_package' && isset($orderData[0]['package_attraction_id'])) {
                    $packageAttractionId = $orderData[0]['package_attraction_id'];
                
                    // Fetch the packaged attraction record
                    $packagedAttraction = DB::table('packaged_attractions')
                        ->where('id', $packageAttractionId)
                        ->first();
                
                    if ($packagedAttraction && !empty($packagedAttraction->attractions)) {
                        // Decode JSON array of attraction IDs
                        $attractionIds = json_decode($packagedAttraction->attractions, true);
                
                        if (is_array($attractionIds) && count($attractionIds) > 0) {
                            // Fetch the first attraction to get its city (location)
                            $firstAttraction = DB::table('attractions')
                                ->select('location')
                                ->where('id', $attractionIds[0])
                                ->first();
                
                            if ($firstAttraction) {
                                // Add the city to order data
                                $order->city = $firstAttraction->location;
                            }
                        }
                    }
                }
                
                return $order;
            });
            
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
        $guest->app_password = Hash::make($password);
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
        $driver->app_password = Hash::make($password);
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
        $guide->app_password = Hash::make($password);
        $guide->save();
        return response()->json([
            'success' => true,
            'message' => 'Guide updated successfully',
            'data' => $guide->refresh()
        ], 200);
    }

    function updateJobsheetStatus(Request $request){
        // Validate request data
        $request->validate([
            'id' => 'required|integer',
            'status' => 'required|string'
        ]);
        $jobsheet = Jobsheet::where('jobsheet_id', $request->id)->first();
        if (!$jobsheet) {
            return response()->json([
                'success' => false,
                'message' => 'Jobsheet not found with the provided ID',
                'data' => null
            ], 404);
        }
        $tour_id = $jobsheet->tour_id;
        $guestEmails = Guest::where('tour_id', $tour_id)->pluck('email')->toArray();
        
        // Update jobsheet status
        $jobsheet->current_status = $request->status;
        $jobsheet->save();
        
        // Send notification to guests if there are any
        if (!empty($guestEmails)) {
            $notificationResult = \App\Helpers\NotificationHelper::sendNotificationToGuest(
                $guestEmails,
                'Jobsheet Status Updated',
                "Your jobsheet status has been updated to: {$request->status}",
                null, // No image for now
                [
                    'type' => 'jobsheet_update',
                    'jobsheet_id' => $request->id,
                    'status' => $request->status,
                    'tour_id' => $tour_id
                ]
            );
            
            \Log::info('Notification sent to guests', ['result' => $notificationResult]);
        }
        return response()->json([
            'success' => true,
            'message' => 'Jobsheet updated successfully',
            'data' => $jobsheet->refresh()
        ], 200);
    }

    /**
     * Logout function for all user types - receives user_type from frontend
     */
    public function logout(Request $request)
    {
        try {
            $userType = $request->input('user_type');
            
            // Validate user type
            if (!$userType || !in_array($userType, ['driver', 'guide', 'guest'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Valid user_type is required (driver, guide, or guest)',
                ], 400);
            }

            // Get the authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Verify the user type matches the authenticated user
            $actualUserType = $this->getUserType($user);
            
            if ($actualUserType !== $userType) {
                return response()->json([
                    'success' => false,
                    'message' => 'User type mismatch. Expected ' . $userType . ' but authenticated as ' . $actualUserType,
                ], 400);
            }

            // Revoke the current token
            $user->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => ucfirst($userType) . ' logged out successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAccount(Request $request)
    {
        try {
            $user = $request->user();

            // Ensure the user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }
            $request->validate([
                'password' => 'required|string',
            ]);
            if (!Hash::check($request->password, $user->app_password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect password',
                ], 403);
            }
            

            // Get actual user type
            $userType = $this->getUserType($user);

            // Optionally revoke all tokens before deletion
            $user->tokens()->delete();

            // Soft delete the user (if the model uses SoftDeletes)
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => ucfirst($userType) . ' account deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper function to determine user type based on model
     */
    private function getUserType($user)
    {
        $className = class_basename(get_class($user));
        
        switch ($className) {
            case 'Driver':
                return 'driver';
            case 'Guide':
                return 'guide';
            case 'Guest':
                return 'guest';
            default:
                return 'user';
        }
    }

    public function shareContactStatusUpdate(Request $request){
        $email = $request->email;
        $guest_id = $request->guest_id;
        $share_status = $request->share_status;
        $guest = Guest::where('guest_id', $guest_id)->where('email', $email)->first();
        if(!$guest){
            return response()->json([
                'success' => false,
                'message' => 'Guest not found',
            ], 404);
        }
        if ($share_status === null) {
            return response()->json([
                'success' => false,
                'message' => 'Share status is required',
            ], 400);
        }
        $guest->share_contact = $share_status;
        $guest->save();
        return response()->json([
            'success' => true,
            'message' => 'Guest share status updated successfully',
            'data' => $guest
        ], 200);
    }

    public function exploreCities(Request $request){
        $country = $request->country;
        $city = $request->city;
        $cityImages = City::select('image')->where('country', $country)->get();
        $cityImages = $cityImages->map(function ($city) {
            return $city->image;
        });
        if($cityImages->isEmpty()){
            return response()->json([
                'success' => false,
                'message' => 'No cities found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Cities retrieved successfully',
            'data' => $cityImages
        ], 200);
    }

}
