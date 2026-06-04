<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Toppings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ToppingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $toppings = [
            ['Vanilla Custard Filling', 0],
            ['Coklat', 5000],
            ['Tiramisu', 5000],
            ['Strawberry', 5000],
            ['Greentea', 5000],
        ];

        foreach ($toppings as [$name, $price]) {
            Toppings::create([
                'id' => Str::uuid(),
                'name' => $name,
                'price' => $price,
                'is_active' => true,
            ]);
        }
    }
}
