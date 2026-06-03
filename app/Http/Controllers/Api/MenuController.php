<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Categories::query()
            ->where('is_active', true)
            ->whereHas('products') // hanya pastikan ada produk
            ->with(['products' => function ($query) {
                $query
                    ->orderBy('name')
                    ->with([
                        'toppings' => fn($q) =>
                        $q->where('is_active', true)
                            ->orderBy('name')
                    ]);
            }])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }
}
