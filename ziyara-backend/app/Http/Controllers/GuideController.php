<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GuideController extends Controller
{
    public function index(Request $request)
    {
        $query = Guide::with(['user'])
            ->where('is_verified', true)
            ->where('is_available', true);

        // Filter by language
        if ($request->has('language')) {
            $query->whereJsonContains('languages', $request->language);
        }

        // Filter by specialty
        if ($request->has('specialty')) {
            $query->whereJsonContains('specialties', $request->specialty);
        }

        // Filter by minimum rating
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        // Sort by rating
        if ($request->has('sort_by_rating') && $request->sort_by_rating) {
            $query->orderBy('rating', 'desc');
        }

        $guides = $query->paginate(12);

        return response()->json($guides);
    }

    public function show($id)
    {
        $guide = Guide::with(['user', 'tours' => function($q) {
            $q->where('status', 'active')->with('images')->take(6);
        }])
        ->where('is_verified', true)
        ->findOrFail($id);

        // Get guide's reviews through their tours
        $reviews = \App\Models\Review::whereIn('tour_id', $guide->tours->pluck('id'))
            ->where('is_approved', true)
            ->with(['user'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'guide' => $guide,
            'reviews' => $reviews
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bio' => 'required|string|max:2000',
            'languages' => 'required|array',
            'languages.*' => 'string',
            'certifications' => 'nullable|array',
            'experience_years' => 'required|integer|min:0',
            'specialties' => 'nullable|array',
            'profile_image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        // Check if user is already a guide
        if ($user->guide) {
            return response()->json(['message' => 'User is already registered as a guide'], 400);
        }

        $guide = Guide::create([
            'user_id' => $user->id,
            'bio' => $request->bio,
            'languages' => $request->languages,
            'certifications' => $request->certifications,
            'experience_years' => $request->experience_years,
            'specialties' => $request->specialties,
            'profile_image' => $request->profile_image,
            'is_verified' => false, // Requires admin verification
            'is_available' => true,
            'rating' => null,
            'total_tours' => 0,
        ]);

        // Assign guide role
        $user->addRole('guide');

        return response()->json([
            'message' => 'Guide registration submitted successfully. Awaiting verification.',
            'guide' => $guide
        ], 201);
    }

    public function update(Request $request)
    {
        $guide = auth()->user()->guide;

        if (!$guide) {
            return response()->json(['message' => 'User is not a guide'], 404);
        }

        $validator = Validator::make($request->all(), [
            'bio' => 'sometimes|required|string|max:2000',
            'languages' => 'sometimes|required|array',
            'certifications' => 'nullable|array',
            'experience_years' => 'sometimes|required|integer|min:0',
            'specialties' => 'nullable|array',
            'is_available' => 'nullable|boolean',
            'profile_image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $guide->update($request->only([
            'bio', 'languages', 'certifications', 'experience_years',
            'specialties', 'is_available', 'profile_image'
        ]));

        return response()->json([
            'message' => 'Guide profile updated successfully',
            'guide' => $guide
        ]);
    }

    public function dashboard()
    {
        $guide = auth()->user()->guide;

        if (!$guide) {
            return response()->json(['message' => 'User is not a guide'], 404);
        }

        $userId = auth()->id();

        $data = [
            'total_tours' => \App\Models\Tour::where('guide_id', $userId)->count(),
            'active_tours' => \App\Models\Tour::where('guide_id', $userId)->where('status', 'active')->count(),
            'total_bookings' => \App\Models\Booking::whereHas('tour', function($q) use ($userId) {
                $q->where('guide_id', $userId);
            })->count(),
            'upcoming_bookings' => \App\Models\Booking::whereHas('tour', function($q) use ($userId) {
                $q->where('guide_id', $userId);
            })
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('tour_date', '>=', now())
            ->count(),
            'pending_bookings' => \App\Models\Booking::whereHas('tour', function($q) use ($userId) {
                $q->where('guide_id', $userId);
            })
            ->where('status', 'pending')
            ->count(),
            'total_earnings' => \App\Models\Booking::whereHas('tour', function($q) use ($userId) {
                $q->where('guide_id', $userId);
            })
            ->where('payment_status', 'paid')
            ->sum('total_price'),
            'average_rating' => $guide->rating,
            'recent_bookings' => \App\Models\Booking::with(['user', 'tour'])
                ->whereHas('tour', function($q) use ($userId) {
                    $q->where('guide_id', $userId);
                })
                ->latest()
                ->take(5)
                ->get(),
        ];

        return response()->json($data);
    }

    public function myTours()
    {
        $tours = \App\Models\Tour::with(['category', 'images'])
            ->where('guide_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($tours);
    }

    public function myBookings(Request $request)
    {
        $query = \App\Models\Booking::with(['user', 'tour'])
            ->whereHas('tour', function($q) {
                $q->where('guide_id', auth()->id());
            });

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('tour_date', '>=', $request->from_date);
        }

        $bookings = $query->orderBy('tour_date', 'desc')->paginate(15);

        return response()->json($bookings);
    }

    // Admin methods
    public function verify($id)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $guide = Guide::findOrFail($id);
        $guide->update(['is_verified' => true]);

        return response()->json([
            'message' => 'Guide verified successfully',
            'guide' => $guide
        ]);
    }

    public function pending()
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $guides = Guide::with('user')
            ->where('is_verified', false)
            ->latest()
            ->paginate(15);

        return response()->json($guides);
    }
}
