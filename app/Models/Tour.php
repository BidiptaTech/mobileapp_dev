<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use App\Models\Enquiry;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tour extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'tours'; 
    protected $guarded = []; 
    
    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static $TourStatus = [
        '0' => 'Not Started',
        '1' => 'Skip',
        '2' => 'In Progress',
        '3' => 'Completed',
        '4' => 'Cancel',
    ];

    public function canProceedToNextStep($currentStatus, $nextStep)
    {
        if ($currentStatus == 1 || $currentStatus == 3) {
            return true; 
        }
        return false; 
    }

    public function booking()
    {
        return $this->hasMany(Order::class, 'tour_id', 'tour_id'); // A room can have multiple rates
    }

    public function enquiries()
    {
        return $this->hasMany(Enquiry::class, 'tour_id', 'tour_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function dmc()
    {
        return $this->belongsTo(User::class, 'dmc_id', 'user_id');
    }

    public function masterDmc()
    {
        return $this->belongsTo(User::class, 'master_dmc_id', 'user_id');
    }
}
