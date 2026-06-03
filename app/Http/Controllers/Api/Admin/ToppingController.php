<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Toppings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToppingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $toppings = Toppings::query()
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $toppings->items(),
            'meta' => [
                'current_page' => $toppings->currentPage(),
                'last_page' => $toppings->lastPage(),
                'per_page' => $toppings->perPage(),
                'total' => $toppings->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $topping = Toppings::query()->create($data);

        return response()->json([
            'message' => 'Topping berhasil ditambahkan.',
            'data' => $topping,
        ], 201);
    }

    public function show(Toppings $topping): JsonResponse
    {
        return response()->json(['data' => $topping]);
    }

    public function update(Request $request, Toppings $topping): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $topping->update($data);

        return response()->json([
            'message' => 'Topping berhasil diperbarui.',
            'data' => $topping,
        ]);
    }

    public function destroy(Toppings $topping): JsonResponse
    {
        if ($topping->products()->exists()) {
            return response()->json([
                'message' => 'Topping masih digunakan produk. Nonaktifkan saja.',
            ], 422);
        }
        $topping->delete();
        return response()->json(['message' => 'Topping berhasil dihapus.']);
    }
}
