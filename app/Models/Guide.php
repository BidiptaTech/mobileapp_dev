<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GuideLanguage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Guide extends Model
{
    use HasFactory, SoftDeletes, HasApiTokens;
    
    protected $table = 'guides'; 
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'userId');
    }

    public function languages()
    {
        return $this->hasMany(GuideLanguage::class, 'guide_id', 'guide_id');
    }

    protected $casts = [
        'close_days' => 'array',
        'close_dates' => 'array',
    ];

    /**
     * Get the name of the unique identifier for the model for Sanctum tokens
     */
    public function getKeyName()
    {
        return 'guide_id';
    }
}
