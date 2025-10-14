<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use App\Models\User;


class Enquiry extends Model
{
    use HasFactory;
    protected $table = 'enquiry_comments'; 
    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(Order::class, 'booking_id', 'booking_id');
    }
    public function tour()
    {
        return $this->belongsTo(Order::class, 'tour_id', 'tour_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'userId');
    }

    public function display()
    {
        return $this->belongsTo(Tour::class, 'tour_id', 'tour_id');
    }
    public function dmc()
    {
        return $this->belongsTo(User::class, 'dmcId', 'userId');
    }

}
