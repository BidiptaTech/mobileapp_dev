<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'cities'; 
    protected $guarded = [];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

}
