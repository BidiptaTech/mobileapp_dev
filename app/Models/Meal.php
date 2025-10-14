<?php

namespace App\Models;

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meal extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'meals'; 
    protected $guarded = [];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id', 'restaurant_id');
    }

    public function dmc()
    {
        return $this->belongsTo(User::class, 'dmc_id', 'userId');
    }
}
