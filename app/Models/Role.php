<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Traits\HasRoles;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends SpatieRole
{
    use HasRoles;
    use SoftDeletes;
    protected $table = 'roles'; 
    protected $guarded = []; 
}
