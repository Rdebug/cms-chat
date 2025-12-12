<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $query = Conversation::query()
            ->with(['currentSector', 'currentAgent'])
            ->orderBy('last_message_at', 'desc');

        // Para agentes, filtrar por setor
        if ($user->isAgent() && $user->sector_id) {
            $query->where('current_sector_id', $user->sector_id);
        }

        $stats = [
            'total_open' => (clone $query)->whereIn('status', ['new', 'queued', 'in_progress', 'waiting_client'])->count(),
            'total_closed_today' => Conversation::whereDate('updated_at', today())
                ->whereIn('status', ['closed', 'archived'])
                ->count(),
        ];

        if ($user->isAgent()) {
            $stats['my_sector_open'] = Conversation::where('current_sector_id', $user->sector_id)
                ->whereIn('status', ['new', 'queued', 'in_progress'])
                ->count();
        }

        $recentConversations = $query
            ->take(10)
            ->get()
            ->map(fn($conv) => [
                'id' => $conv->id,
                'whatsapp_number' => $conv->whatsapp_number,
                'client_name' => $conv->client_name,
                'status' => $conv->status,
                'sector' => $conv->currentSector?->name,
                'agent' => $conv->currentAgent?->name,
                'last_message_at' => $conv->last_message_at?->diffForHumans(),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentConversations' => $recentConversations,
        ]);
    }
}
