<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Alimentación', 'icon' => 'Utensils', 'color' => '#f43f5e'],
            ['name' => 'Transporte', 'icon' => 'Car', 'color' => '#3b82f6'],
            ['name' => 'Servicios', 'icon' => 'Zap', 'color' => '#f59e0b'],
            ['name' => 'Entretenimiento', 'icon' => 'Gamepad2', 'color' => '#a855f7'],
            ['name' => 'Compras', 'icon' => 'ShoppingBag', 'color' => '#10b981'],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create($category);
        }
    }
}
