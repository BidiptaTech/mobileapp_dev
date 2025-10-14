<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomType extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'room_type'; 
    protected $guarded = []; 

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id', 'hotel_unique_id');
    }
}

