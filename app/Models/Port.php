<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Port extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'port_name',
        'type',
        'country',
        'city_id',
        'latitude',
        'longitude',
        'distance',
        'status',
        'port_id',
    ];

    /**
     * Get the country that owns the port.
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country', 'name');
    }

    /**
     * Get the city that owns the port.
     */
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'city_id');
    }
} 