<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jobsheet extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'jobsheets'; 
    protected $guarded = [];
    
    /**
     * Get the tour associated with the jobsheet.
     */
    public function tour()
    {
        return $this->belongsTo(Tour::class, 'tour_id', 'tour_id');
    }
}
