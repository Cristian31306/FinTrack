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
            ['name' => '🛒 Compras', 'icon' => 'shopping-cart', 'color' => '#3b82f6'],
            ['name' => '🍔 Comida', 'icon' => 'utensils', 'color' => '#f97316'],
            ['name' => '🚗 Transporte', 'icon' => 'car', 'color' => '#22c55e'],
            ['name' => '🏠 Hogar', 'icon' => 'home', 'color' => '#a855f7'],
            ['name' => '🏥 Salud', 'icon' => 'heart', 'color' => '#ef4444'],
            ['name' => '💡 Servicios', 'icon' => 'lightbulb', 'color' => '#eab308'],
            ['name' => '🎭 Entretenimiento', 'icon' => 'tv', 'color' => '#ec4899'],
            ['name' => '📱 Suscripciones', 'icon' => 'credit-card', 'color' => '#14b8a6'],
            ['name' => '💳 Otros', 'icon' => 'help-circle', 'color' => '#64748b'],
        ];

        foreach ($defaults as $data) {
            Category::updateOrCreate(
                ['user_id' => $user->id, 'name' => $data['name']],
                $data
            );
        }
    }
}
