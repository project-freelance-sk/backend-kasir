<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Categories::query()
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = Categories::query()->create($data);

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan.',
            'data' => $category,
        ], 201);
    }

    public function show(Categories $category): JsonResponse
    {
        return response()->json(['data' => $category]);
    }

    public function update(Request $request, Categories $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $category,
        ]);
    }

    public function destroy(Categories $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Kategori masih memiliki produk. Hapus atau pindahkan produk terlebih dahulu.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}
