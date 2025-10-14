<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PackagedAttraction extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'packaged_attractions';
    protected $guarded = [];

    /**
     * Get the attractions as an array
     */
    public function getAttractionsArrayAttribute()
    {
        return json_decode($this->attractions, true) ?? [];
    }
    
    /**
     * Get the gallery images as an array
     */
    public function getGalleryImagesArrayAttribute()
    {
        return json_decode($this->image, true) ?? [];
    }
    
    /**
     * Get the additional images as an array
     */
    public function getAdditionalImagesArrayAttribute()
    {
        return json_decode($this->additional_images, true) ?? [];
    }
    
    /**
     * Get all attractions related to this package
     */
    public function attractionsList()
    {
        $attractionIds = $this->getAttractionsArrayAttribute();
        return Attraction::whereIn('id', $attractionIds)->get();
    }
    
    /**
     * Find packaged attractions containing a specific attraction ID
     * 
     * @param int|string $attractionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function findByAttractionId($attractionId)
    {
        // Convert to string for consistent comparison
        $attractionId = (string) $attractionId;
        
        // Use whereJsonContains for proper JSON searching
        // Return the query builder instead of calling get()
        return self::whereJsonContains('attractions', $attractionId);
    }
}
