<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    public function index(Request $request, $tourId)
    {
        $query = Review::with(['user'])
            ->where('tour_id', $tourId)
            ->where('is_approved', true);

        // Filter by rating
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->latest()->paginate(10);

        // Calculate rating distribution
        $ratingDistribution = Review::where('tour_id', $tourId)
            ->where('is_approved', true)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $avgRating = Review::where('tour_id', $tourId)
            ->where('is_approved', true)
            ->avg('rating');

        return response()->json([
            'reviews' => $reviews,
            'avg_rating' => round($avgRating, 1),
            'total_reviews' => Review::where('tour_id', $tourId)->where('is_approved', true)->count(),
            'rating_distribution' => $ratingDistribution,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'tour_id' => 'required|exists:tours,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if booking belongs to user
        $booking = Booking::where('id', $request->booking_id)
            ->where('user_id', auth()->id())
            ->where('tour_id', $request->tour_id)
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Invalid booking'], 400);
        }

        // Check if booking is completed
        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'Can only review completed bookings'], 400);
        }

        // Check if review already exists
        $existingReview = Review::where('booking_id', $request->booking_id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'Review already exists for this booking'], 400);
        }

        $review = Review::create([
            'booking_id' => $request->booking_id,
            'tour_id' => $request->tour_id,
            'user_id' => auth()->id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => false, // Requires admin approval
        ]);

        return response()->json([
            'message' => 'Review submitted successfully and awaiting approval',
            'review' => $review
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $review = Review::where('user_id', auth()->id())->findOrFail($id);

        // Cannot update approved reviews
        if ($review->is_approved) {
            return response()->json(['message' => 'Cannot update approved review'], 400);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $review->update($request->only(['rating', 'comment']));

        return response()->json([
            'message' => 'Review updated successfully',
            'review' => $review
        ]);
    }

    public function destroy($id)
    {
        $review = Review::where('user_id', auth()->id())->findOrFail($id);

        // Cannot delete approved reviews
        if ($review->is_approved) {
            return response()->json(['message' => 'Cannot delete approved review'], 400);
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully']);
    }

    public function myReviews()
    {
        $reviews = Review::with(['tour', 'tour.images'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        return response()->json($reviews);
    }

    // Admin methods
    public function approve($id)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $review = Review::findOrFail($id);
        $review->update([
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Review approved successfully',
            'review' => $review
        ]);
    }

    public function pending()
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $reviews = Review::with(['user', 'tour', 'booking'])
            ->where('is_approved', false)
            ->latest()
            ->paginate(15);

        return response()->json($reviews);
    }
}
