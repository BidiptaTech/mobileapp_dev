<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppManagement extends Model
{
    protected $table = 'app_management';
    
    protected $fillable = [
        'past_image',
        'upcoming_image',
        'ongoing_image',
    ];
}
