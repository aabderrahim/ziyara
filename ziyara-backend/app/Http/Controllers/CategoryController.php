<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::where('is_active', true);

        // Get only parent categories
        if ($request->has('parent_only') && $request->parent_only) {
            $query->whereNull('parent_id');
        }

        // Get categories with their children
        if ($request->has('with_children') && $request->with_children) {
            $query->with('children');
        }

        // Get categories with tour count
        if ($request->has('with_tour_count') && $request->with_tour_count) {
            $query->withCount('tours');
        }

        $categories = $query->orderBy('order')->get();

        return response()->json($categories);
    }

    public function show($slug)
    {
        $category = Category::with(['children', 'tours' => function($q) {
            $q->where('status', 'active')->take(12);
        }])
        ->where('slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();

        return response()->json($category);
    }

    public function store(Request $request)
    {
        // Only admin can create categories
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'icon' => $request->icon,
            'image' => $request->image,
            'parent_id' => $request->parent_id,
            'order' => $request->order ?? 0,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

    public function update(Request $request, $id)
    {
        // Only admin can update categories
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['name', 'description', 'icon', 'image', 'parent_id', 'order', 'is_active']);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    public function destroy($id)
    {
        // Only admin can delete categories
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category = Category::findOrFail($id);

        // Check if category has tours
        if ($category->tours()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with existing tours'
            ], 400);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories'
            ], 400);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    public function popular()
    {
        $categories = Category::where('is_active', true)
            ->withCount('tours')
            ->having('tours_count', '>', 0)
            ->orderBy('tours_count', 'desc')
            ->take(6)
            ->get();

        return response()->json($categories);
    }
}
