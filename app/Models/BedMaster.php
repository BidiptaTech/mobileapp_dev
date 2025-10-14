<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BedMaster extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'beds_master'; 
    protected $guarded = [];

    public function hotel(){
        return $this->belongsTo(Hotel::class, 'hotel_id', 'hotel_unique_id');
    }
}
