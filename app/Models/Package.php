<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Attraction;
use App\Models\PackageBooking;

class Package extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'packages'; 
    protected $guarded = []; 
    protected $casts = [
        'selected_hotels' => 'json',
        'selected_attractions' => 'json',
        'selected_guide' => 'json',
        'selected_restaurants' => 'json',
        'gallery_images' => 'json',
        'start_date' => 'date',
        'expire_date' => 'date',
        'available_dates' => 'array',
        'is_featured' => 'boolean',
        'attraction_with_transfer' => 'boolean',
        'entry_port' => 'boolean',
        'exit_port' => 'boolean',
        'price_adult' => 'decimal:2',
        'price_senior' => 'decimal:2',
        'price_child' => 'decimal:2',
        'rating' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $dates = [
        'start_date',
        'expire_date',
        'created_at',
        'updated_at'
    ];

    protected $fillable = [
        'package_id',
        'title',
        'destination',
        'city',
        'category',
        'duration_days',
        'package_type',
        'description',
        'price_adult',
        'price_senior',
        'price_child',
        'max_pax',
        'selected_hotels',
        'selected_attractions',
        'selected_guide',
        'selected_restaurants',
        'max_hotels',
        'max_attractions',
        'max_restaurants',
        'attraction_with_transfer',
        'transfer_notes',
        'entry_port',
        'exit_port',
        'main_image',
        'gallery_images',
        'start_date',
        'expire_date',
        'inclusions',
        'exclusions',
        'terms_conditions',
        'status',
        'created_by',
        'updated_by',
        'itinerary',
        'dmc_id',
        'child_max_age'
    ];

    const CATEGORIES = [
        'Adventure',
        'Cultural',
        'Family',
        'Luxury',
        'Nature',
        'Religious',
        'Shopping',
        'Sightseeing',
        'Beach',
        'City Break'
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive(Builder $query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured(Builder $query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByDestination(Builder $query, $destination)
    {
        return $query->where('destination', $destination);
    }

    public function scopeByCategory(Builder $query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByPriceRange(Builder $query, $min, $max)
    {
        return $query->whereBetween('price_adult', [$min, $max]);
    }

    public function scopeSearch(Builder $query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('destination', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getPriceFromAttribute()
    {
        return $this->price_adult;
    }

    public function getDurationAttribute()
    {
        return $this->duration_days . 'D/' . ($this->duration_days - 1) . 'N';
    }

    public function getMainImageUrlAttribute()
    {
        if ($this->main_image) {
            return asset('storage/' . $this->main_image);
        }
        
        // Return default image based on destination
        $defaultImages = [
            'Singapore' => 'https://images.unsplash.com/photo-1525625293386-3f8f99389edd?w=400&h=250&fit=crop',
            'Malaysia' => 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?w=400&h=250&fit=crop',
            'Thailand' => 'https://images.unsplash.com/photo-1552465011-b4e21bf6e79a?w=400&h=250&fit=crop',
            'Indonesia' => 'https://images.unsplash.com/photo-1537953773345-d172ccf13cf1?w=400&h=250&fit=crop',
            'Vietnam' => 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?w=400&h=250&fit=crop',
            'Philippines' => 'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?w=400&h=250&fit=crop'
        ];
        
        return $defaultImages[$this->destination] ?? 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=400&h=250&fit=crop';
    }

    public function getGalleryImageUrlsAttribute()
    {
        if ($this->gallery_images) {
            return array_map(function ($image) {
                return asset('storage/' . $image);
            }, $this->gallery_images);
        }
        return [];
    }

    // Mutators
    public function setSelectedHotelsAttribute($value)
    {
        $this->attributes['selected_hotels'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setSelectedAttractionsAttribute($value)
    {
        $this->attributes['selected_attractions'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setSelectedGuideAttribute($value)
    {
        $this->attributes['selected_guide'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setSelectedRestaurantsAttribute($value)
    {
        $this->attributes['selected_restaurants'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setGalleryImagesAttribute($value)
    {
        $this->attributes['gallery_images'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setAvailableDatesAttribute($value)
    {
        $this->attributes['available_dates'] = is_array($value) ? json_encode($value) : $value;
    }

    // Helper methods
    public function incrementViews()
    {
        $this->increment('views_count');
    }

    public function updateRating($newRating)
    {
        $totalRating = ($this->rating * $this->reviews_count) + $newRating;
        $this->reviews_count += 1;
        $this->rating = $totalRating / $this->reviews_count;
        $this->save();
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isDraft()
    {
        return $this->status === 'draft';
    }

    /**
     * Get all bookings for this package
     */
    public function bookings()
    {
        return $this->hasMany(PackageBooking::class, 'package_id', 'package_id');
    }

    public function getSelectedHotelsDetails()
    {
        if (!$this->selected_hotels) {
            return collect([]);
        }

        $hotelIds = [];
        $hotelNames = [];
        
        foreach ($this->selected_hotels as $hotel) {
            if (isset($hotel['id']) && is_string($hotel['id']) && !empty($hotel['id'])) {
                // Clean the ID - remove any non-alphanumeric characters except hyphens and underscores
                $cleanId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $hotel['id']);
                if (!empty($cleanId)) {
                    $hotelIds[] = $cleanId;
                }
            }
            if (isset($hotel['name']) && !empty($hotel['name'])) {
                $hotelNames[] = $hotel['name'];
            }
        }

        $query = Hotel::query();
        
        if (!empty($hotelIds)) {
            $query->where(function($q) use ($hotelIds) {
                $q->whereIn('hotel_unique_id', $hotelIds)
                  ->orWhereIn('id', $hotelIds);
            });
        }
        
        if (!empty($hotelNames)) {
            if (!empty($hotelIds)) {
                $query->orWhereIn('name', $hotelNames);
            } else {
                $query->whereIn('name', $hotelNames);
            }
        }

        return $query->get();
    }

    public function getSelectedAttractionsDetails()
    {
        if (!$this->selected_attractions) {
            return collect([]);
        }

        $attractionIds = [];
        $attractionNames = [];
        
        foreach ($this->selected_attractions as $attraction) {
            if (isset($attraction['id']) && is_string($attraction['id']) && !empty($attraction['id'])) {
                // Clean the ID - remove any non-alphanumeric characters except hyphens and underscores
                $cleanId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $attraction['id']);
                if (!empty($cleanId)) {
                    $attractionIds[] = $cleanId;
                }
            }
            if (isset($attraction['name']) && !empty($attraction['name'])) {
                $attractionNames[] = $attraction['name'];
            }
        }

        $query = Attraction::query();
        
        if (!empty($attractionIds)) {
            $query->where(function($q) use ($attractionIds) {
                $q->whereIn('attraction_id', $attractionIds)
                  ->orWhereIn('id', $attractionIds);
            });
        }
        
        if (!empty($attractionNames)) {
            if (!empty($attractionIds)) {
                $query->orWhereIn('name', $attractionNames);
            } else {
                $query->whereIn('name', $attractionNames);
            }
        }

        return $query->get();
    }
}
