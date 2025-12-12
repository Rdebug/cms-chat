<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with('sector')->orderBy('name');

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $users = $query->get()->map(fn($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'sector' => $user->sector?->name,
            'active' => $user->active,
        ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['role', 'active']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', User::class);

        $sectors = Sector::where('active', true)->orderBy('name')->get()->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ]);

        return Inertia::render('Users/Form', [
            'sectors' => $sectors,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'sector_id' => $request->role === 'agent' ? $request->sector_id : null,
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('users.index')->with('success', 'Usuário criado com sucesso!');
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $sectors = Sector::where('active', true)->orderBy('name')->get()->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ]);

        return Inertia::render('Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'sector_id' => $user->sector_id,
                'active' => $user->active,
            ],
            'sectors' => $sectors,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'sector_id' => $request->role === 'agent' ? $request->sector_id : null,
            'active' => $request->boolean('active'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Usuário atualizado com sucesso!');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        // Não permite excluir a si mesmo
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'Você não pode excluir seu próprio usuário.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuário excluído com sucesso!');
    }
}
