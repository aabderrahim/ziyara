<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Tour;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index()
    {
        $favorites = Favorite::with(['tour', 'tour.category', 'tour.guide', 'tour.images'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        return response()->json($favorites);
    }

    public function toggle($tourId)
    {
        // Check if tour exists
        $tour = Tour::findOrFail($tourId);

        $favorite = Favorite::where('user_id', auth()->id())
            ->where('tour_id', $tourId)
            ->first();

        if ($favorite) {
            // Remove from favorites
            $favorite->delete();
            return response()->json([
                'message' => 'Removed from favorites',
                'is_favorite' => false
            ]);
        } else {
            // Add to favorites
            Favorite::create([
                'user_id' => auth()->id(),
                'tour_id' => $tourId,
            ]);
            return response()->json([
                'message' => 'Added to favorites',
                'is_favorite' => true
            ], 201);
        }
    }

    public function check($tourId)
    {
        $isFavorite = Favorite::where('user_id', auth()->id())
            ->where('tour_id', $tourId)
            ->exists();

        return response()->json(['is_favorite' => $isFavorite]);
    }

    public function destroy($tourId)
    {
        $favorite = Favorite::where('user_id', auth()->id())
            ->where('tour_id', $tourId)
            ->first();

        if (!$favorite) {
            return response()->json(['message' => 'Favorite not found'], 404);
        }

        $favorite->delete();

        return response()->json(['message' => 'Removed from favorites']);
    }
}
