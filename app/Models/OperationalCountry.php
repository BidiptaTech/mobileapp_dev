<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalCountry extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'operational_countries'; 
    protected $guarded = [];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
