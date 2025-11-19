<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user()->load(['guide', 'roles']);

        $stats = [
            'total_bookings' => $user->bookings()->count(),
            'completed_tours' => $user->bookings()->where('status', 'completed')->count(),
            'total_reviews' => $user->reviews()->count(),
            'favorite_tours' => $user->favorites()->count(),
        ];

        return response()->json([
            'user' => $user,
            'stats' => $stats
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'profile_image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only(['name', 'phone', 'profile_image']));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'errors' => ['current_password' => ['Current password is incorrect']]
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function deleteAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'errors' => ['password' => ['Password is incorrect']]
            ], 422);
        }

        // Check for active bookings
        $activeBookings = $user->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('tour_date', '>=', now())
            ->count();

        if ($activeBookings > 0) {
            return response()->json([
                'message' => 'Cannot delete account with active bookings'
            ], 400);
        }

        // Delete user account
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    public function bookingHistory(Request $request)
    {
        $query = auth()->user()->bookings()
            ->with(['tour', 'tour.images'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->paginate(15);

        return response()->json($bookings);
    }

    public function dashboard()
    {
        $user = auth()->user();

        $data = [
            'total_bookings' => $user->bookings()->count(),
            'pending_bookings' => $user->bookings()->where('status', 'pending')->count(),
            'upcoming_tours' => $user->bookings()
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('tour_date', '>=', now())
                ->count(),
            'completed_tours' => $user->bookings()->where('status', 'completed')->count(),
            'favorite_tours' => $user->favorites()->count(),
            'total_spent' => $user->bookings()
                ->whereIn('status', ['confirmed', 'completed'])
                ->sum('total_price'),
            'recent_bookings' => $user->bookings()
                ->with(['tour', 'tour.images'])
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_tours_list' => $user->bookings()
                ->with(['tour', 'tour.images'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('tour_date', '>=', now())
                ->orderBy('tour_date')
                ->take(5)
                ->get(),
        ];

        return response()->json($data);
    }
}
