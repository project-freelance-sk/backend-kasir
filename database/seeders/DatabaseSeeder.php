<?php

namespace Database\Seeders;

use App\Models\Categories;
use App\Models\Product;
use App\Models\ShopSetting;
use App\Models\Toppings;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@cafe.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Kasir',
            'email' => 'kasir@cafe.test',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        ShopSetting::query()->create([
            'shop_name' => 'Kasir Cafe',
            'address' => 'Jl. Contoh No. 1',
            'receipt_footer' => 'Terima kasih!',
            'tax_percent' => 0,
            'service_charge_percent' => 0,
        ]);

        $this->call(CategorySeeder::class);
        $this->call(ToppingSeeder::class);
        $this->call(ProductSeeder::class);
    }
}
