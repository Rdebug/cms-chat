<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Sector;
use App\Models\TransferLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    private const REOPEN_TIMEOUT_MINUTES = 120;

    /**
     * Busca ou cria uma conversa pelo número do WhatsApp
     */
    public function findOrCreateConversationByNumber(string $number): Conversation
    {
        // Busca última conversa ativa
        $conversation = Conversation::where('whatsapp_number', $number)
            ->whereIn('status', ['new', 'queued', 'in_progress', 'waiting_client'])
            ->latest('last_message_at')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        // Busca última conversa fechada para verificar timeout
        $lastClosed = Conversation::where('whatsapp_number', $number)
            ->whereIn('status', ['closed', 'archived'])
            ->latest('last_message_at')
            ->first();

        // Se última conversa fechada há menos de REOPEN_TIMEOUT_MINUTES, reabre
        if ($lastClosed && $lastClosed->last_message_at) {
            $minutesSinceLastMessage = Carbon::now()->diffInMinutes($lastClosed->last_message_at);
            if ($minutesSinceLastMessage < self::REOPEN_TIMEOUT_MINUTES) {
                $lastClosed->status = 'new';
                $lastClosed->current_agent_id = null;
                $lastClosed->save();
                return $lastClosed;
            }
        }

        // Cria nova conversa
        return Conversation::create([
            'whatsapp_number' => $number,
            'status' => 'new',
        ]);
    }

    /**
     * Atribui setor à conversa
     */
    public function assignSector(Conversation $conversation, Sector $sector): void
    {
        DB::transaction(function () use ($conversation, $sector) {
            $conversation->current_sector_id = $sector->id;
            
            if ($conversation->status === 'new') {
                $conversation->status = 'queued';
            }
            
            $conversation->save();
        });
    }

    /**
     * Atribui agente à conversa
     */
    public function assignAgent(Conversation $conversation, User $agent): void
    {
        if (!$agent->isAgent()) {
            throw new \InvalidArgumentException('User must be an agent');
        }

        DB::transaction(function () use ($conversation, $agent) {
            $conversation->current_agent_id = $agent->id;
            $conversation->status = 'in_progress';
            $conversation->save();
        });
    }

    /**
     * Transfere conversa para outro setor/agente
     */
    public function transferConversation(
        Conversation $conversation,
        Sector $toSector,
        ?User $toAgent = null,
        ?string $note = null
    ): void {
        DB::transaction(function () use ($conversation, $toSector, $toAgent, $note) {
            $fromSectorId = $conversation->current_sector_id;
            $fromAgentId = $conversation->current_agent_id;

            $conversation->current_sector_id = $toSector->id;
            $conversation->current_agent_id = $toAgent?->id;
            $conversation->status = 'queued';
            $conversation->save();

            // Registra transferência
            TransferLog::create([
                'conversation_id' => $conversation->id,
                'from_sector_id' => $fromSectorId,
                'to_sector_id' => $toSector->id,
                'from_agent_id' => $fromAgentId,
                'to_agent_id' => $toAgent?->id,
                'note' => $note,
            ]);
        });
    }

    /**
     * Encerra conversa
     */
    public function closeConversation(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation) {
            $conversation->status = 'closed';
            $conversation->save();
        });
    }

    /**
     * Arquiva conversa
     */
    public function archiveConversation(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation) {
            $conversation->status = 'archived';
            $conversation->save();
        });
    }
}

