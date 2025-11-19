<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with(['tour', 'tour.images'])
            ->where('user_id', auth()->id());

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('tour_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('tour_date', '<=', $request->to_date);
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($bookings);
    }

    public function show($id)
    {
        $booking = Booking::with(['tour', 'tour.guide', 'tour.images', 'payment'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json($booking);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tour_id' => 'required|exists:tours,id',
            'tour_date' => 'required|date|after_or_equal:today',
            'number_of_participants' => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tour = Tour::findOrFail($request->tour_id);

        // Check if tour is active
        if ($tour->status !== 'active') {
            return response()->json(['message' => 'This tour is not available'], 400);
        }

        // Check max participants
        if ($request->number_of_participants > $tour->max_participants) {
            return response()->json([
                'message' => "Maximum {$tour->max_participants} participants allowed"
            ], 400);
        }

        // Check if schedule is available
        $schedule = TourSchedule::where('tour_id', $request->tour_id)
            ->where('date', $request->tour_date)
            ->where('status', 'available')
            ->first();

        if (!$schedule) {
            return response()->json(['message' => 'No available schedule for this date'], 400);
        }

        if ($schedule->available_spots < $request->number_of_participants) {
            return response()->json([
                'message' => "Only {$schedule->available_spots} spots available"
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Create booking
            $booking = Booking::create([
                'booking_number' => 'BK-' . strtoupper(uniqid()),
                'tour_id' => $request->tour_id,
                'user_id' => auth()->id(),
                'tour_date' => $request->tour_date,
                'number_of_participants' => $request->number_of_participants,
                'total_price' => $tour->price * $request->number_of_participants,
                'status' => 'pending',
                'payment_status' => 'pending',
                'special_requests' => $request->special_requests,
            ]);

            // Update available spots
            $schedule->decrement('available_spots', $request->number_of_participants);

            // Check if schedule is now full
            if ($schedule->available_spots <= 0) {
                $schedule->update(['status' => 'full']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking->load('tour')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Booking failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel($id, Request $request)
    {
        $booking = Booking::where('user_id', auth()->id())->findOrFail($id);

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Booking is already cancelled'], 400);
        }

        if ($booking->status === 'completed') {
            return response()->json(['message' => 'Cannot cancel completed booking'], 400);
        }

        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Update booking status
            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->cancellation_reason,
                'cancelled_at' => now(),
            ]);

            // Restore available spots
            $schedule = TourSchedule::where('tour_id', $booking->tour_id)
                ->where('date', $booking->tour_date)
                ->first();

            if ($schedule) {
                $schedule->increment('available_spots', $booking->number_of_participants);
                if ($schedule->status === 'full') {
                    $schedule->update(['status' => 'available']);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Booking cancelled successfully',
                'booking' => $booking
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cancellation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirm($id)
    {
        $booking = Booking::findOrFail($id);

        // Only admin or guide can confirm
        if (!auth()->user()->hasRole('admin') && $booking->tour->guide_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($booking->status !== 'pending') {
            return response()->json(['message' => 'Only pending bookings can be confirmed'], 400);
        }

        $booking->update(['status' => 'confirmed']);

        return response()->json([
            'message' => 'Booking confirmed successfully',
            'booking' => $booking
        ]);
    }

    public function complete($id)
    {
        $booking = Booking::findOrFail($id);

        // Only admin or guide can complete
        if (!auth()->user()->hasRole('admin') && $booking->tour->guide_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($booking->status !== 'confirmed') {
            return response()->json(['message' => 'Only confirmed bookings can be completed'], 400);
        }

        $booking->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Booking completed successfully',
            'booking' => $booking
        ]);
    }

    public function statistics()
    {
        $userId = auth()->id();

        $stats = [
            'total_bookings' => Booking::where('user_id', $userId)->count(),
            'pending_bookings' => Booking::where('user_id', $userId)->where('status', 'pending')->count(),
            'confirmed_bookings' => Booking::where('user_id', $userId)->where('status', 'confirmed')->count(),
            'completed_bookings' => Booking::where('user_id', $userId)->where('status', 'completed')->count(),
            'cancelled_bookings' => Booking::where('user_id', $userId)->where('status', 'cancelled')->count(),
            'total_spent' => Booking::where('user_id', $userId)
                ->whereIn('status', ['confirmed', 'completed'])
                ->sum('total_price'),
            'upcoming_tours' => Booking::where('user_id', $userId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('tour_date', '>=', now())
                ->count(),
        ];

        return response()->json($stats);
    }
}
