<?php

use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\ShopSettingController;
use App\Http\Controllers\Api\Admin\ToppingController as AdminToppingController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    $loginMiddleware = app()->environment('testing') ? [] : ['throttle:10,1'];

    Route::post('login', [AuthController::class, 'login'])->middleware($loginMiddleware);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('menu', [MenuController::class, 'index']);

    Route::middleware('role:admin,cashier')->group(function () {
        Route::get('settings/pos', [ShopSettingController::class, 'pos']);

        Route::get('transactions', [TransactionController::class, 'index']);
        Route::post('transactions', [TransactionController::class, 'store']);
        Route::get('transactions/{transaction}/receipt', [TransactionController::class, 'receipt']);
        Route::post('transactions/{transaction}/void', [TransactionController::class, 'void']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::apiResource('categories', AdminCategoryController::class);
        Route::apiResource('products', AdminProductController::class);
        Route::apiResource('toppings', AdminToppingController::class);
        Route::apiResource('users', AdminUserController::class);

        Route::get('settings', [ShopSettingController::class, 'show']);
        Route::put('settings', [ShopSettingController::class, 'update']);

        Route::get('reports/sales', [ReportController::class, 'sales']);
        Route::get('reports/transactions', [ReportController::class, 'transactions']);
    });
});
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok'
    ]);
});
