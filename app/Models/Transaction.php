<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $table = 'transactions'; 
    protected $guarded = []; 

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');    
    }

    public function user_transaction()
    {
        return $this->belongsTo(User::class, 'credited_from');    
    }
}
