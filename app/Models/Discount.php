<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'discounts'; 
    protected $guarded = []; 

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }
}
