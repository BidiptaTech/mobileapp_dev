<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'agencies';

    protected $fillable = [
        'agency_id',
        'agency_name',
        'email',
        'phone',
        'country',
        'city',
        'address',
        'postal_code',
        'id_card_type',
        'card_number',
        'branches',
        'logo',
        'status',
        'dmc_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'branches' => 'array', // Cast JSON to array
        'dmc_id' => 'array', // Cast JSON to array
        'status' => 'boolean',
    ];

    protected $dates = [
        'deleted_at',
    ];

    /**
     * Get the user who created this agency
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'userId');
    }

    /**
     * Get the user who last updated this agency
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'userId');
    }

    /**
     * Scope to get only active agencies
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Get the total number of branches (including head office)
     */
    public function getTotalBranchesAttribute()
    {
        $branches = $this->branches ?? [];
        return count($branches) + 1; // +1 for head office
    }

    /**
     * Get only the branch data (excluding head office)
     */
    public function getBranchesOnlyAttribute()
    {
        return $this->branches ?? [];
    }

    /**
     * Check if agency has branches
     */
    public function hasBranches()
    {
        return !empty($this->branches);
    }

    /**
     * Add a DMC ID to the dmc_id array
     */
    public function addDmcId($dmcId)
    {
        $dmcIds = $this->getDmcIdsArray();
        if (!in_array($dmcId, $dmcIds)) {
            $dmcIds[] = $dmcId;
            $this->dmc_id = $dmcIds;
            $this->save();
        }
        return $this;
    }

    /**
     * Remove a DMC ID from the dmc_id array
     */
    public function removeDmcId($dmcId)
    {
        $dmcIds = $this->getDmcIdsArray();
        $dmcIds = array_values(array_filter($dmcIds, function($id) use ($dmcId) {
            return $id != $dmcId;
        }));
        $this->dmc_id = $dmcIds;
        $this->save();
        return $this;
    }

    /**
     * Check if a DMC has selected this agency
     */
    public function hasSelectedByDmc($dmcId)
    {
        $dmcIds = $this->getDmcIdsArray();
        return in_array($dmcId, $dmcIds);
    }

    /**
     * Get all DMC IDs that have selected this agency
     */
    public function getSelectedDmcIds()
    {
        return $this->getDmcIdsArray();
    }

    /**
     * Helper method to get dmc_id as array, handling both integer and array formats
     */
    private function getDmcIdsArray()
    {
        $dmcIds = $this->dmc_id;
        
        if (is_null($dmcIds)) {
            return [];
        }
        
        if (is_array($dmcIds)) {
            return $dmcIds;
        }
        
        if (is_string($dmcIds)) {
            $decoded = json_decode($dmcIds, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return is_array($decoded) ? $decoded : [$decoded];
            }
        }
        
        if (is_numeric($dmcIds)) {
            return [$dmcIds];
        }
        
        return [];
    }
} 