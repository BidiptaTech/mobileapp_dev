<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Package;
use App\Models\User;
use App\Models\Agent;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageBooking extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'package_bookings';
    protected $guarded = [];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'booking_details' => 'json',
        'package' => 'json',
        'user_info' => 'json',
        'travel_dates' => 'json',
        'selected_hotels' => 'json',
        'selected_attractions' => 'json',
        'selected_guides' => 'json',
        'selected_restaurants' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the package associated with the booking
     */
    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id', 'package_id');
    }
    
    /**
     * Get the user who made the booking
     */
    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by', 'userId');
    }
    
    /**
     * Get the agent associated with the booking
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
    
    /**
     * Get the total number of travelers
     */
    public function getTotalTravelersAttribute()
    {
        if ($this->booking_details && isset($this->booking_details['adult_count']) && isset($this->booking_details['child_count'])) {
            return $this->booking_details['adult_count'] + $this->booking_details['child_count'];
        }
        return 0;
    }
    
    /**
     * Get the travel dates as a formatted string
     */
    public function getTravelDatesRangeAttribute()
    {
        if ($this->booking_details && isset($this->booking_details['itinerary']) && count($this->booking_details['itinerary']) > 0) {
            $firstDay = reset($this->booking_details['itinerary']);
            $lastDay = end($this->booking_details['itinerary']);
            
            if (isset($firstDay['date']) && isset($lastDay['date'])) {
                return $firstDay['date'] . ' - ' . $lastDay['date'];
            }
        }
        return 'Not specified';
    }
    
    /**
     * Get the duration in days
     */
    public function getDurationDaysAttribute()
    {
        if ($this->booking_details && isset($this->booking_details['itinerary'])) {
            return count($this->booking_details['itinerary']);
        }
        return 0;
    }
}
