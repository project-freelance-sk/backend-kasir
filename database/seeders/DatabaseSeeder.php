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

        $coffee = Categories::query()->create(['name' => 'Coffee', 'is_active' => true]);
        $nonCoffee = Categories::query()->create(['name' => 'Non-Coffee', 'is_active' => true]);

        $extraShot = Toppings::query()->create(['name' => 'Extra Shot', 'price' => 5000]);
        $oatMilk = Toppings::query()->create(['name' => 'Oat Milk', 'price' => 8000]);
        $boba = Toppings::query()->create(['name' => 'Boba', 'price' => 6000]);

        $espresso = Product::query()->create([
            'category_id' => $coffee->id,
            'name' => 'Espresso',
            'description' => 'Single shot espresso.',
            'price' => 18000,
        ]);
        $espresso->toppings()->sync([$extraShot->id, $oatMilk->id]);

        $latte = Product::query()->create([
            'category_id' => $coffee->id,
            'name' => 'Cafe Latte',
            'description' => 'Espresso with steamed milk.',
            'price' => 28000,
        ]);
        $latte->toppings()->sync([$extraShot->id, $oatMilk->id]);

        $matcha = Product::query()->create([
            'category_id' => $nonCoffee->id,
            'name' => 'Matcha Latte',
            'description' => 'Premium matcha with milk.',
            'price' => 30000,
        ]);
        $matcha->toppings()->sync([$oatMilk->id, $boba->id]);
    }
}
