<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Handle quick login with predefined users (development/testing)
     */
    public function quickLogin(Request $request, string $type): RedirectResponse
    {
        $email = match($type) {
            'admin' => 'admin@exemplo.com',
            'agent' => 'agent@exemplo.com',
            default => null,
        };

        if (!$email) {
            abort(404);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $sectorId = null;
            
            // Se for agente, cria um setor padrão se não existir
            if ($type === 'agent') {
                $sector = Sector::where('slug', 'atendimento')->first();
                
                if (!$sector) {
                    $sector = Sector::create([
                        'name' => 'Atendimento',
                        'slug' => 'atendimento',
                        'menu_code' => '1',
                        'active' => true,
                    ]);
                }
                
                $sectorId = $sector->id;
            }
            
            // Cria o usuário se não existir
            $user = User::create([
                'name' => $type === 'admin' ? 'Administrador' : 'Atendente',
                'email' => $email,
                'password' => Hash::make('123456'),
                'role' => $type === 'admin' ? 'admin' : 'agent',
                'sector_id' => $sectorId,
                'active' => true,
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
