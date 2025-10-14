<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleZoneMapping extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'from_zone_id',
        'from_zone_type',
        'to_zone_id',
        'to_zone_type',
        'private_price',
        'shared_price',
        'mapping_id',
    ];
    
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }
    
    public function fromZone()
    {
        return $this->belongsTo(Zone::class, 'from_zone_id', 'zone_id');
    }
    
    public function toZone()
    {
        return $this->belongsTo(Zone::class, 'to_zone_id', 'zone_id');
    }
}
