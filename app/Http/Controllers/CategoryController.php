<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Categories/Index', [
            'categories' => Category::where(function ($query) {
                $query->whereNull('user_id')
                      ->orWhere('user_id', auth()->id());
            })->orderBy('name')->get()
        ]);
    }

    public function create()
    {
        // Handled by modal in Index
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string',
            'color' => 'required|string|size:7',
        ]);

        $validated['user_id'] = auth()->id();

        Category::create($validated);

        return redirect()->route('categories.index')->with('success', 'Categoría creada con éxito.');
    }

    public function edit(Category $category)
    {
        if ($category->user_id !== auth()->id() && !is_null($category->user_id)) {
            abort(403);
        }
    }

    public function update(Request $request, Category $category)
    {
        if ($category->user_id !== auth()->id() && !is_null($category->user_id)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string',
            'color' => 'required|string|size:7',
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category)
    {
        if ($category->user_id !== auth()->id() && !is_null($category->user_id)) {
            abort(403, 'No puedes eliminar categorías del sistema.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Categoría eliminada.');
    }
}
