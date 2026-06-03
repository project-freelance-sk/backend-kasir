<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\Models\Product;
use App\Models\Toppings;
use App\Rules\ExistsModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'toppings'])
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->string('category_id')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'uuid', new ExistsModel(Categories::class)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'topping_ids' => ['nullable', 'array'],
            'topping_ids.*' => ['uuid', new ExistsModel(Toppings::class)],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']);
        }

        $product = Product::query()->create(collect($data)->except('topping_ids')->all());

        if ($request->has('topping_ids')) {
            $product->toppings()->sync($request->input('topping_ids', []));
        }

        $product->load(['category', 'toppings']);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'data' => $product,
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'toppings']);

        return response()->json(['data' => $product]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['sometimes', 'uuid', new ExistsModel(Categories::class)],
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'topping_ids' => ['nullable', 'array'],
            'topping_ids.*' => ['uuid', new ExistsModel(Toppings::class)],
        ]);

        if ($request->hasFile('image')) {
            if ($product->getRawOriginal('image')) {
                Storage::disk('public')->delete($product->getRawOriginal('image'));
            }

            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']);
        }

        $product->update(collect($data)->except('topping_ids')->all());

        if ($request->has('topping_ids')) {
            $activeToppingIds = Toppings::query()
                ->whereIn('id', $request->input('topping_ids', []))
                ->where('is_active', true)
                ->pluck('id');
            $product->toppings()->sync($activeToppingIds);
        }

        $product->load(['category', 'toppings']);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data' => $product,
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->getRawOriginal('image')) {
            Storage::disk('public')->delete($product->getRawOriginal('image'));
        }

        $product->delete();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }
}
