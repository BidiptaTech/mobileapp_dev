<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attraction extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'attractions'; 
    protected $guarded = [];

    protected $casts = [
        'dmc_id' => 'array',
        'zone_assignments' => 'array', // Will store DMC-zone mappings like [{"dmc_id": 4, "zone_id": "zone1"}, {"dmc_id": 17, "zone_id": "zone2"}]
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'userId');
    }

    public function companyname()
    {
        return $this->belongsTo(User::class, 'dmc_id', 'userId');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'attraction_id', 'attraction_id');
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
     * Check if a DMC has selected this attraction
     */
    public function hasSelectedByDmc($dmcId)
    {
        $dmcIds = $this->getDmcIdsArray();
        return in_array($dmcId, $dmcIds);
    }

    /**
     * Get all DMC IDs that have selected this attraction
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
        $dmcId = $this->dmc_id;
        
        if (is_null($dmcId)) {
            return [];
        }
        
        if (is_array($dmcId)) {
            return $dmcId;
        }
        
        if (is_string($dmcId)) {
            $decoded = json_decode($dmcId, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return is_array($decoded) ? $decoded : [$decoded];
            }
        }
        
        if (is_numeric($dmcId)) {
            return [$dmcId];
        }
        
        return [];
    }

    /**
     * Get zone assignment for a specific DMC
     */
    public function getZoneForDmc($dmcId)
    {
        $assignments = $this->zone_assignments ?? [];
        
        foreach ($assignments as $assignment) {
            if (isset($assignment['dmc_id']) && $assignment['dmc_id'] == $dmcId) {
                return $assignment['zone_id'] ?? null;
            }
        }
        
        return null;
    }

    /**
     * Set zone assignment for a specific DMC
     */
    public function setZoneForDmc($dmcId, $zoneId = null)
    {
        $assignments = $this->zone_assignments ?? [];
        
        // Remove existing assignment for this DMC
        $assignments = array_filter($assignments, function($assignment) use ($dmcId) {
            return !isset($assignment['dmc_id']) || $assignment['dmc_id'] != $dmcId;
        });
        
        // Add new assignment if zoneId is provided
        if ($zoneId) {
            $assignments[] = [
                'dmc_id' => $dmcId,
                'zone_id' => $zoneId
            ];
        }
        
        $this->zone_assignments = array_values($assignments);
        $this->save();
        
        return $this;
    }

    /**
     * Check if attraction is assigned to any zone by a specific DMC
     */
    public function isAssignedToZoneByDmc($dmcId)
    {
        return !is_null($this->getZoneForDmc($dmcId));
    }

    /**
     * Get all DMCs that have assigned this attraction to zones
     */
    public function getAssignedDmcs()
    {
        $assignments = $this->zone_assignments ?? [];
        return array_column($assignments, 'dmc_id');
    }

    /**
     * Remove zone assignment for a specific zone (regardless of DMC)
     */
    public function removeZoneAssignment($zoneId)
    {
        $assignments = $this->zone_assignments ?? [];
        
        // Remove all assignments for the specified zone
        $assignments = array_filter($assignments, function($assignment) use ($zoneId) {
            return !isset($assignment['zone_id']) || $assignment['zone_id'] != $zoneId;
        });
        
        $this->zone_assignments = array_values($assignments);
        $this->save();
        
        return $this;
    }
}
