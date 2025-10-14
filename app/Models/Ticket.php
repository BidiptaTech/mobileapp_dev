<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tickets';
    
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'ticket_id',
        'name',
        'description',
        'remarks',
        'terms_conditions',
        'child_price',
        'adult_price',
        'senior_adult_price',
        'child_price_nri',
        'adult_price_nri',
        'senior_adult_price_nri',
        'status',
        'created_by',
        'updated_by',
        'dmc_id',
        'attraction_id'
    ];

    // Cast decimal fields to float
    protected $casts = [
        'child_price' => 'float',
        'adult_price' => 'float',
        'senior_adult_price' => 'float',
        'child_price_nri' => 'float',
        'adult_price_nri' => 'float',
        'senior_adult_price_nri' => 'float',
        'status' => 'integer',
        'attraction_id' => 'integer',
        'dmc_id' => 'integer',
    ];

    /**
     * Get the attraction that owns the ticket
     */
    public function attraction()
    {
        return $this->belongsTo(Attraction::class, 'attraction_id', 'attraction_id');
    }

    /**
     * Get the DMC user that created the ticket
     */
    public function dmc()
    {
        return $this->belongsTo(User::class, 'dmc_id', 'userId');
    }
}
