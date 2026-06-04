<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Categories;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;


class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Rice Bowl',
            'Pasta',
            'Waffles',
            'Snack Asin',
            'Chicken Wings',
            'Salad',
            'Snack Manis',
            'Lain Lain',
            'Classic Coffee',
            'White Coffee',
            'Black Coffee',
            'Tea',
            'Mocktails',
            'Special Coffee',
            'Manual Brew',
        ];

        foreach ($categories as $category) {
            Categories::create([
                'id' => Str::uuid(),
                'name' => $category,
            ]);
        }
    }
}
