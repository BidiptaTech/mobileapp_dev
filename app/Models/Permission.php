<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property string|null $feature_roles
 */
class Permission extends SpatiePermission
{
    protected $table = 'permissions';

    protected $guarded = [];
}
