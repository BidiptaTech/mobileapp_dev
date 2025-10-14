<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rate extends Model
{
    use SoftDeletes;
    protected $table = 'rates'; 
    protected $guarded = []; 

    public function user()
    {
        return $this->belongsTo(User::class, 'dmc_id', 'userId');
    }
}
