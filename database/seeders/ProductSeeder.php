<?php

namespace Database\Seeders;

use App\Models\Categories;
use App\Models\Product;
use App\Models\Toppings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [

            // RICE BOWL
            ['Rice Bowl', 'Blackpapper Beef', 25000],
            ['Rice Bowl', 'Blackpapper Chicken', 22000],
            ['Rice Bowl', 'BBQ Sauce Chicken', 22000],
            ['Rice Bowl', 'Oyster Sauce Beef', 28000],
            ['Rice Bowl', 'Oyster Sauce Chicken', 25000],
            ['Rice Bowl', 'Spesial Sambal Cumi Asin', 20000],
            ['Rice Bowl', 'Spesial Sambal Tetelan', 25000],
            ['Rice Bowl', 'Spesial Sambal Paru', 25000],

            // PASTA
            ['Pasta', 'Bolognese', 25000],
            ['Pasta', 'Carbonara', 28000],
            ['Pasta', 'Aglio Olio', 23000],

            // WAFFLES
            ['Waffles', 'Waffles Manis', 18000],
            ['Waffles', 'Waffles Asin', 20000],

            // CHICKEN WINGS
            ['Chicken Wings', 'Chicken Wings Blackpapper', 20000],
            ['Chicken Wings', 'Chicken Wings Barbeque', 20000],

            // SALAD
            ['Salad', 'Caesar Salad', 22000],
            ['Salad', 'Thai Salad', 25000],
            ['Salad', 'Fresh Salad', 25000],

            // SNACK ASIN
            ['Snack Asin', 'French Fries', 17000],
            ['Snack Asin', 'Potatoes Wedges', 15000],
            ['Snack Asin', 'Risoles Bolognese', 17000],
            ['Snack Asin', 'Risoles Mayo', 17000],
            ['Snack Asin', 'Spring Rolls Sayur', 15000],
            ['Snack Asin', 'Tahu Walik', 18000],
            ['Snack Asin', 'Dimsum Udang', 17000],
            ['Snack Asin', 'Dimsum Original', 15000],

            // SNACK MANIS
            ['Snack Manis', 'Donat', 10000],
            ['Snack Manis', 'Churros Coklat', 15000],
            ['Snack Manis', 'Churros Greentea', 15000],

            // LAIN LAIN
            ['Lain Lain', 'Ayam Grill', 10000],
            ['Lain Lain', 'Telur Rebus', 7000],
            ['Lain Lain', 'Thai Sauce', 5000],
            ['Lain Lain', 'Coklat Sauce', 5000],
            ['Lain Lain', 'Tiramisu Sauce', 5000],
            ['Lain Lain', 'Strawberry Sauce', 5000],
            ['Lain Lain', 'Greentea Sauce', 5000],
            ['Lain Lain', 'Nasi Putih', 5000],

            // CLASSIC
            ['Classic Coffee', 'Cappuccino', 19000],
            ['Classic Coffee', 'Caffe Latte', 19000],
            ['Classic Coffee', 'Americano', 16000],
            ['Classic Coffee', 'Espresso', 8000],

            // WHITE COFFEE
            ['White Coffee', '88 Signature', 19000],
            ['White Coffee', 'Vanilla Latte', 19000],
            ['White Coffee', 'Hazelnut Latte', 19000],
            ['White Coffee', 'Baileys Latte', 20000],
            ['White Coffee', 'Pink Berry Latte', 18000],
            ['White Coffee', 'White Lotus', 20000],

            // BLACK COFFEE
            ['Black Coffee', 'Berry Twist', 19000],
            ['Black Coffee', 'Black Orange', 19000],

            // TEA
            ['Tea', 'Early Grey Tea', 15000],
            ['Tea', 'Early Grey Milk', 18000],
            ['Tea', 'Peach Tea', 18000],
            ['Tea', 'Matcha Latte', 20000],
            ['Tea', 'Ichigo Matcha', 25000],

            // MOCKTAILS
            ['Mocktails', 'Java Sunrise', 20000],
            ['Mocktails', 'Red Berry Mood', 17000],
            ['Mocktails', 'Peach Mojito', 20000],

            // SPECIAL
            ['Special Coffee', 'Mont Blanc', 24000],
            ['Special Coffee', 'Dirty Latte', 24000],
            ['Special Coffee', 'Coconut Coffee Cloud', 23000],
            ['Special Coffee', 'Matcha Cloud', 23000],
            ['Special Coffee', 'Tiger Bomb', 26000],
            ['Special Coffee', 'Tripple Choco Nut', 22000],

            // MANUAL BREW
            ['Manual Brew', 'Manual Brew A', 15000],
            ['Manual Brew', 'Manual Brew B', 25000],
        ];

        foreach ($products as [$categoryName, $name, $price]) {

            $category = Categories::where('name', $categoryName)->first();

            Product::create([
                'id' => Str::uuid(),
                'category_id' => $category->id,
                'name' => $name,
                'description' => null,
                'price' => $price,
                'image' => null,
            ]);
        }

        // Topping untuk Donat
        $donat = Product::where('name', 'Donat')->first();

        if ($donat) {
            $toppings = Toppings::whereIn('name', [
                'Coklat',
                'Tiramisu',
                'Strawberry',
                'Greentea',
            ])->pluck('id');

            $donat->toppings()->sync($toppings);
        }
    }
}
