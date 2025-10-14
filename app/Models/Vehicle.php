<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'vehicles'; 
    protected $guarded = [];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function operationalCountry()
    {
        return $this->belongsTo(OperationalCountry::class, 'vehicle_id', 'vehicle_id');
    }
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'driver_id');
    }
    public function dmc()
    {
        return $this->belongsTo(User::class, 'dmc_id', 'userId');
    }

}
