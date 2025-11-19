<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TourController extends Controller
{
    public function index(Request $request)
    {
        $query = Tour::with(['category', 'guide', 'images'])
            ->where('status', 'active');

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by difficulty
        if ($request->has('difficulty_level')) {
            $query->where('difficulty_level', $request->difficulty_level);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Filter by location
        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        // Featured tours
        if ($request->has('featured') && $request->featured) {
            $query->where('featured', true);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $tours = $query->paginate($perPage);

        return response()->json($tours);
    }

    public function show($slug)
    {
        $tour = Tour::with(['category', 'guide', 'images', 'reviews' => function($q) {
            $q->where('is_approved', true)->latest()->take(5);
        }, 'schedules' => function($q) {
            $q->where('date', '>=', now())->where('status', 'available');
        }])
        ->where('slug', $slug)
        ->where('status', 'active')
        ->firstOrFail();

        // Calculate average rating
        $avgRating = $tour->reviews()->where('is_approved', true)->avg('rating');
        $tour->avg_rating = round($avgRating, 1);
        $tour->total_reviews = $tour->reviews()->where('is_approved', true)->count();

        return response()->json($tour);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:255',
            'duration' => 'required|integer|min:1',
            'max_participants' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'difficulty_level' => 'required|in:easy,moderate,hard',
            'category_id' => 'required|exists:categories,id',
            'location' => 'nullable|string',
            'meeting_point' => 'nullable|string',
            'included_items' => 'nullable|array',
            'excluded_items' => 'nullable|array',
            'requirements' => 'nullable|array',
            'images' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tour = Tour::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description,
            'short_description' => $request->short_description,
            'duration' => $request->duration,
            'max_participants' => $request->max_participants,
            'price' => $request->price,
            'difficulty_level' => $request->difficulty_level,
            'category_id' => $request->category_id,
            'guide_id' => auth()->id(),
            'location' => $request->location,
            'meeting_point' => $request->meeting_point,
            'included_items' => $request->included_items,
            'excluded_items' => $request->excluded_items,
            'requirements' => $request->requirements,
            'status' => 'draft',
            'featured' => false,
            'images' => $request->images,
        ]);

        return response()->json([
            'message' => 'Tour created successfully',
            'tour' => $tour
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $tour = Tour::findOrFail($id);

        // Check if user is authorized to update
        if ($tour->guide_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'short_description' => 'nullable|string|max:255',
            'duration' => 'sometimes|required|integer|min:1',
            'max_participants' => 'sometimes|required|integer|min:1',
            'price' => 'sometimes|required|numeric|min:0',
            'difficulty_level' => 'sometimes|required|in:easy,moderate,hard',
            'category_id' => 'sometimes|required|exists:categories,id',
            'location' => 'nullable|string',
            'meeting_point' => 'nullable|string',
            'included_items' => 'nullable|array',
            'excluded_items' => 'nullable|array',
            'requirements' => 'nullable|array',
            'status' => 'sometimes|in:draft,active,inactive',
            'images' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'title', 'description', 'short_description', 'duration',
            'max_participants', 'price', 'difficulty_level', 'category_id',
            'location', 'meeting_point', 'included_items', 'excluded_items',
            'requirements', 'status', 'images'
        ]);

        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $tour->update($data);

        return response()->json([
            'message' => 'Tour updated successfully',
            'tour' => $tour
        ]);
    }

    public function destroy($id)
    {
        $tour = Tour::findOrFail($id);

        // Check if user is authorized to delete
        if ($tour->guide_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tour->delete();

        return response()->json(['message' => 'Tour deleted successfully']);
    }

    public function featured()
    {
        $tours = Tour::with(['category', 'guide', 'images'])
            ->where('status', 'active')
            ->where('featured', true)
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get();

        return response()->json($tours);
    }

    public function popular()
    {
        $tours = Tour::with(['category', 'guide', 'images'])
            ->where('status', 'active')
            ->withCount('bookings')
            ->orderBy('bookings_count', 'desc')
            ->take(8)
            ->get();

        return response()->json($tours);
    }
}
