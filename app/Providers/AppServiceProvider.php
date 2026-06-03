<?php

namespace App\Providers;

use App\Models\Categories;
use App\Models\Product;
use App\Models\Toppings;
use App\Models\Transactions;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('transaction', fn (string $value) => Transactions::query()->findOrFail($value));
        Route::bind('category', fn (string $value) => Categories::query()->findOrFail($value));
        Route::bind('topping', fn (string $value) => Toppings::query()->findOrFail($value));
        Route::bind('product', fn (string $value) => Product::query()->findOrFail($value));
        Route::bind('user', fn (string $value) => User::query()->findOrFail($value));
    }
}
