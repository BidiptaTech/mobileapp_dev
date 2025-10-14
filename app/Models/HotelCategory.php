<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelCategory extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'hotel_categories'; 
    protected $guarded = []; 
}
