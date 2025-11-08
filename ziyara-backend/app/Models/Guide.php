<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guide extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'bio', 'languages', 'certifications', 'experience_years', 'specialties',
        'is_verified', 'is_available', 'rating', 'total_tours', 'profile_image',
    ];

    protected $casts = [
        'languages' => 'array',
        'certifications' => 'array',
        'specialties' => 'array',
        'is_verified' => 'boolean',
        'is_available' => 'boolean',
        'rating' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tours()
    {
        return $this->hasMany(Tour::class, 'guide_id');
    }
}
