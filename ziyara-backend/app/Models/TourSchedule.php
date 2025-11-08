<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id', 'date', 'start_time', 'end_time', 'available_spots', 'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }
}
