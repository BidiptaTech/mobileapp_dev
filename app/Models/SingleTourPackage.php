<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class SingleTourPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'single_tour_packages';

        protected $fillable = [
        'dmc_id',
        'country_id', 
        'city_id',
        'start_date',
        'end_date',
        'adults',
        'male',
        'female',
        'children',
        'infants',
        'agent_id',
        'package_name',
        'estimated_budget',
        'package_description',
        'is_premium',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_premium' => 'boolean',
        'estimated_budget' => 'decimal:2'
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the country that owns the package.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the city that owns the package.
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the agent assigned to the package.
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * Get the DMC that created the package.
     */
    public function dmc()
    {
        return $this->belongsTo(User::class, 'dmc_id');
    }

    /**
     * Get the user who created the package.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the package.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Calculate the total number of guests.
     */
    public function getTotalGuestsAttribute()
    {
        return $this->adults + $this->children + $this->infants;
    }

    /**
     * Calculate the duration in days.
     */
    public function getDurationAttribute()
    {
        if ($this->start_date && $this->end_date) {
            return Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date)) + 1;
        }
        return 0;
    }

    /**
     * Get formatted duration string.
     */
    public function getFormattedDurationAttribute()
    {
        $duration = $this->duration;
        if ($duration <= 1) {
            return '1 Day';
        }
        return $duration . ' Days / ' . ($duration - 1) . ' Nights';
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute()
    {
        switch ($this->status) {
            case 'draft':
                return 'secondary';
            case 'pending':
                return 'warning';
            case 'confirmed':
                return 'success';
            case 'cancelled':
                return 'danger';
            default:
                return 'light';
        }
    }

    /**
     * Get formatted budget.
     */
    public function getFormattedBudgetAttribute()
    {
        if ($this->estimated_budget) {
            return 'S$' . number_format($this->estimated_budget, 2);
        }
        return 'Not specified';
    }

    /**
     * Scope for filtering by DMC.
     */
    public function scopeForDmc($query, $dmcId)
    {
        return $query->where('dmc_id', $dmcId);
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }
}