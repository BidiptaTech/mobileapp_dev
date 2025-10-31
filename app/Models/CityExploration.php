<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CityExploration extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'city_explorations';
    
    protected $fillable = [
        'city_id',
        'overview',
        'attractions',
        'food_cuisine',
        'accommodation',
        'transportation',
        'best_time_visit',
        'shopping',
        'hospitals_emergency',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'overview' => 'array',
        'attractions' => 'array',
        'food_cuisine' => 'array',
        'accommodation' => 'array',
        'transportation' => 'array',
        'best_time_visit' => 'array',
        'shopping' => 'array',
        'hospitals_emergency' => 'array',
    ];

    /**
     * Get the city that owns the exploration data.
     */
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'city_id');
    }
}
