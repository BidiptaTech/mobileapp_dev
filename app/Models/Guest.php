<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Tour;
use App\Models\Order;

class Guest extends Model
{
    use HasFactory, SoftDeletes, HasApiTokens;
    
    protected $table = 'guests'; 
    protected $guarded = []; 
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the tour associated with the guest
     */
    public function tour()
    {
        return $this->belongsTo(Tour::class, 'tour_id', 'tour_id');
    }

    /**
     * Get the name of the unique identifier for the model for Sanctum tokens
     */
    public function getKeyName()
    {
        return 'guest_id';
    }
}

