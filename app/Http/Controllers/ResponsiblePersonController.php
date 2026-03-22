<?php

namespace App\Http\Controllers;

use App\Models\ResponsiblePerson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResponsiblePersonController extends Controller
{
    public function index(Request $request): Response
    {
        $people = ResponsiblePerson::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('ResponsiblePeople/Index', [
            'people' => $people,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ResponsiblePeople/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $request->user()->responsiblePeople()->create($validated);

        return redirect()->route('responsible-people.index')->with('success', 'Responsable creado.');
    }

    public function edit(Request $request, ResponsiblePerson $responsiblePerson): Response
    {
        $this->authorizePerson($request, $responsiblePerson);

        return Inertia::render('ResponsiblePeople/Edit', [
            'person' => $responsiblePerson,
        ]);
    }

    public function update(Request $request, ResponsiblePerson $responsiblePerson): RedirectResponse
    {
        $this->authorizePerson($request, $responsiblePerson);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $responsiblePerson->update($validated);

        return redirect()->route('responsible-people.index')->with('success', 'Responsable actualizado.');
    }

    public function destroy(Request $request, ResponsiblePerson $responsiblePerson): RedirectResponse
    {
        $this->authorizePerson($request, $responsiblePerson);
        $responsiblePerson->delete();

        return redirect()->route('responsible-people.index')->with('success', 'Responsable eliminado.');
    }

    private function authorizePerson(Request $request, ResponsiblePerson $person): void
    {
        abort_unless($person->user_id === $request->user()->id, 403);
    }
}
