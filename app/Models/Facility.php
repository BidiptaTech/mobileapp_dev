<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'facilities'; 
    protected $guarded = []; 

    public function categories()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

}
