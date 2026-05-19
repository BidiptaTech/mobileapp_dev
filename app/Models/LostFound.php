<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LostFound extends Model
{
    use HasFactory;

    protected $table = 'lost_found';

    public $timestamps = false;

    protected $fillable = [
        'tour_id',
        'dmc_id',
        'subject',
        'description',
        'phone',
        'email',
        'comments',
        'images',
        'guest_images',
        'resolved',
    ];

    protected $casts = [
        'tour_id' => 'integer',
        'dmc_id' => 'integer',
        'images' => 'array',
        'guest_images' => 'array',
        'resolved' => 'boolean',
    ];
}
