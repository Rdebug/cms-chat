<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use App\Http\Requests\StoreSectorRequest;
use App\Http\Requests\UpdateSectorRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SectorController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Sector::class);

        $query = Sector::query()->orderBy('menu_code');

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $sectors = $query->get()->map(fn($sector) => [
            'id' => $sector->id,
            'name' => $sector->name,
            'slug' => $sector->slug,
            'menu_code' => $sector->menu_code,
            'active' => $sector->active,
            'users_count' => $sector->users()->count(),
        ]);

        return Inertia::render('Sectors/Index', [
            'sectors' => $sectors,
            'filters' => $request->only(['active']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Sector::class);

        return Inertia::render('Sectors/Form');
    }

    public function store(StoreSectorRequest $request)
    {
        Sector::create([
            'name' => $request->name,
            'slug' => $request->slug ?: Str::slug($request->name),
            'menu_code' => $request->menu_code,
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('sectors.index')->with('success', 'Setor criado com sucesso!');
    }

    public function edit(Sector $sector)
    {
        $this->authorize('update', $sector);

        return Inertia::render('Sectors/Form', [
            'sector' => [
                'id' => $sector->id,
                'name' => $sector->name,
                'slug' => $sector->slug,
                'menu_code' => $sector->menu_code,
                'active' => $sector->active,
            ],
        ]);
    }

    public function update(UpdateSectorRequest $request, Sector $sector)
    {
        $sector->update([
            'name' => $request->name,
            'slug' => $request->slug ?: Str::slug($request->name),
            'menu_code' => $request->menu_code,
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('sectors.index')->with('success', 'Setor atualizado com sucesso!');
    }

    public function destroy(Sector $sector)
    {
        $this->authorize('delete', $sector);

        // Verifica se há conversas ou usuários usando este setor
        if ($sector->conversations()->exists() || $sector->users()->exists()) {
            return redirect()->back()->with('error', 'Não é possível excluir setor com conversas ou usuários vinculados.');
        }

        $sector->delete();

        return redirect()->route('sectors.index')->with('success', 'Setor excluído com sucesso!');
    }
}
