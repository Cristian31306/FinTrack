<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;

class CategoryService
{
    /**
     * Semilla de categorías por defecto para un nuevo usuario.
     */
    public function seedDefaultCategories(User $user): void
    {
        $defaults = [
            ['name' => 'Compras', 'icon' => 'ShoppingBag', 'color' => '#3b82f6'],
            ['name' => 'Comida', 'icon' => 'Utensils', 'color' => '#f97316'],
            ['name' => 'Transporte', 'icon' => 'Car', 'color' => '#22c55e'],
            ['name' => 'Hogar', 'icon' => 'Home', 'color' => '#a855f7'],
            ['name' => 'Salud', 'icon' => 'Heart', 'color' => '#ef4444'],
            ['name' => 'Servicios', 'icon' => 'Zap', 'color' => '#eab308'],
            ['name' => 'Entretenimiento', 'icon' => 'Gamepad2', 'color' => '#ec4899'],
            ['name' => 'Suscripciones', 'icon' => 'CreditCard', 'color' => '#14b8a6'],
            ['name' => 'Otros', 'icon' => 'Wallet', 'color' => '#64748b'],
        ];

        foreach ($defaults as $data) {
            Category::updateOrCreate(
                ['user_id' => $user->id, 'name' => $data['name']],
                $data
            );
        }
    }
}
