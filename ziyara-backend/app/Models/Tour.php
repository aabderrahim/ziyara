<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tour extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'short_description', 'duration', 'max_participants',
        'price', 'difficulty_level', 'category_id', 'guide_id', 'location', 'meeting_point',
        'included_items', 'excluded_items', 'requirements', 'status', 'featured', 'images'
    ];

    protected $casts = [
        'included_items' => 'array',
        'excluded_items' => 'array',
        'requirements' => 'array',
        'images' => 'array',
        'featured' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function guide()
    {
        return $this->belongsTo(User::class, 'guide_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function schedules()
    {
        return $this->hasMany(TourSchedule::class);
    }

    public function images()
    {
        return $this->hasMany(TourImage::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}