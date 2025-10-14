<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuideLanguage extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'guide_languages'; 
    protected $guarded = [];
}
