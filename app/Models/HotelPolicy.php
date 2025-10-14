<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class HotelPolicy extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'hotel_policy'; 
    protected $guarded = []; 
    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_unique_id', 'hotel_id');
    }
}
