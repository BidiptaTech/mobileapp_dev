<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;
    protected $table = 'countries'; 
    protected $guarded = []; 

    protected $fillable = [
        'name',
        'country_code',
        'currency',
        'is_active',
        'tax_percentage',
        'gateway_percentage',
        'commission_percentage',
        'card_type',
        'card_length',
        'min_length',
        'max_length',
        'header_pdf',
        'footer_pdf'
    ];

     // Relationship with City
     public function cities()
     {
         return $this->hasMany(City::class, 'country_id', 'id');
     }

     public function getcities()
    {
        return $this->hasMany(City::class);
    }

}
