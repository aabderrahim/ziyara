<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_number', 'tour_id', 'user_id', 'tour_date', 'number_of_participants',
        'total_price', 'status', 'payment_status', 'payment_method', 'special_requests',
        'cancellation_reason', 'cancelled_at',
    ];

    protected $casts = [
        'tour_date' => 'date',
        'cancelled_at' => 'datetime',
        'total_price' => 'decimal:2',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
