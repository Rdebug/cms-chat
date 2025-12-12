<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Sector;
use App\Services\WhatsApp\RevolutionClient;
use Carbon\Carbon;

class BotRoutingService
{
    public function __construct(
        private RevolutionClient $whatsAppClient,
        private ConversationService $conversationService
    ) {}

    /**
     * Entrada única do bot para mensagens do cliente.
     * - Comandos (menu/voltar/0, humano/atendente)
     * - Keywords (roteamento automático)
     * - Menu (fallback) + seleção numérica
     */
    public function handleIncomingClientMessage(Conversation $conversation, ?string $messageText): void
    {
        // Handoff: se já tem agente, bot não interfere.
        if ($conversation->current_agent_id !== null) {
            return;
        }

        $text = $this->normalizeText($messageText ?? '');

        // Comandos de menu/reset
        if ($text !== '' && $this->isAnyCommand($text, (array) config('bot.menu_commands', []))) {
            $this->resetToTriage($conversation);
            $this->sendInitialMenuIfAllowed($conversation);
            return;
        }

        // Comandos para falar com humano (fila Recepção)
        if ($text !== '' && $this->isAnyCommand($text, (array) config('bot.human_handoff_commands', []))) {
            $sector = $this->getOrCreateReceptionSector();
            $this->conversationService->assignSector($conversation, $sector);
            $this->sendBotText(
                $conversation,
                "Certo! Vou te encaminhar para um atendente humano. Aguarde um instante.",
                meta: ['kind' => 'handoff']
            );
            return;
        }

        // Se ainda não tem setor, tenta keywords antes do menu
        if ($conversation->current_sector_id === null) {
            $matchedSector = $this->matchSectorByKeywords($text);
            if ($matchedSector) {
                $this->conversationService->assignSector($conversation, $matchedSector);
                $this->sendBotText(
                    $conversation,
                    "Entendi! Vou te direcionar para o setor *{$matchedSector->name}*. Aguarde, em breve um atendente entrará em contato.",
                    meta: ['kind' => 'keyword_match', 'sector_id' => $matchedSector->id]
                );
                return;
            }

            // Se for número, tenta processar como menu
            if ($this->isMenuSelection($text)) {
                $this->processMenuSelection($conversation, $text);
                return;
            }

            // Fallback: menu inicial (com cooldown)
            $this->sendInitialMenuIfAllowed($conversation);
            return;
        }

        // Se já existe setor (mas sem agente), por enquanto não automatizamos.
        // Futuro: coletar dados por setor / FAQ / etc.
    }

    /**
     * Envia menu inicial para conversa sem setor
     */
    public function sendInitialMenu(Conversation $conversation): void
    {
        $sectors = Sector::where('active', true)
            ->orderBy('menu_code')
            ->get();

        if ($sectors->isEmpty()) {
            $message = "Olá! Bem-vindo ao nosso atendimento. Em breve um atendente entrará em contato.";
        } else {
            $message = "Olá! Para agilizar seu atendimento, escolha o setor digitando o número:\n\n";
            
            foreach ($sectors as $sector) {
                $message .= "{$sector->menu_code} – {$sector->name}\n";
            }
            
            $message .= "\nDigite apenas o número do setor desejado.";
        }

        $this->sendBotText($conversation, $message, meta: ['kind' => 'menu']);
    }

    /**
     * Processa resposta do menu e atribui setor
     */
    public function processMenuSelection(Conversation $conversation, string $messageText): bool
    {
        $menuCode = trim($messageText);
        
        $sector = Sector::where('active', true)
            ->where('menu_code', $menuCode)
            ->first();

        if (!$sector) {
            $this->sendInvalidOptionMessage($conversation);
            return false;
        }

        $this->conversationService->assignSector($conversation, $sector);
        
        $confirmationMessage = "Setor *{$sector->name}* selecionado com sucesso! "
            . "Aguarde, em breve um atendente entrará em contato.";

        $this->sendBotText($conversation, $confirmationMessage, meta: ['kind' => 'menu_choice', 'sector_id' => $sector->id]);
        
        return true;
    }

    /**
     * Envia mensagem de opção inválida
     */
    private function sendInvalidOptionMessage(Conversation $conversation): void
    {
        $sectors = Sector::where('active', true)
            ->orderBy('menu_code')
            ->get();

        $message = "❌ Opção inválida!\n\n";
        $message .= "Por favor, escolha um dos setores abaixo digitando apenas o número:\n\n";
        
        foreach ($sectors as $sector) {
            $message .= "{$sector->menu_code} – {$sector->name}\n";
        }

        $this->sendBotText($conversation, $message, meta: ['kind' => 'invalid_menu']);
    }

    /**
     * Verifica se conversa precisa de menu inicial
     */
    public function needsInitialMenu(Conversation $conversation): bool
    {
        return $conversation->status === 'new' && $conversation->current_sector_id === null;
    }

    /**
     * Verifica se mensagem é seleção de menu
     */
    public function isMenuSelection(string $messageText): bool
    {
        $messageText = trim($messageText);
        // Verifica se é apenas números (código do menu)
        return ctype_digit($messageText) && strlen($messageText) <= 3;
    }

    private function normalizeText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return mb_strtolower($text);
    }

    private function isAnyCommand(string $normalizedText, array $commands): bool
    {
        foreach ($commands as $cmd) {
            $cmd = $this->normalizeText((string) $cmd);
            if ($cmd !== '' && $normalizedText === $cmd) {
                return true;
            }
        }
        return false;
    }

    private function resetToTriage(Conversation $conversation): void
    {
        $conversation->current_sector_id = null;
        $conversation->current_agent_id = null;
        $conversation->status = 'new';
        $conversation->save();
    }

    private function getOrCreateReceptionSector(): Sector
    {
        $cfg = (array) config('bot.reception_sector', []);
        $slug = (string) ($cfg['slug'] ?? 'recepcao');

        $sector = Sector::where('slug', $slug)->first();
        if ($sector) {
            return $sector;
        }

        return Sector::create([
            'name' => (string) ($cfg['name'] ?? 'Recepção'),
            'slug' => $slug,
            'menu_code' => (string) ($cfg['menu_code'] ?? '99'),
            'active' => (bool) ($cfg['active'] ?? true),
        ]);
    }

    private function matchSectorByKeywords(string $normalizedText): ?Sector
    {
        if ($normalizedText === '') {
            return null;
        }

        $routes = (array) config('bot.keyword_routes', []);
        foreach ($routes as $sectorSlug => $keywords) {
            $sectorSlug = (string) $sectorSlug;
            if ($sectorSlug === '') {
                continue;
            }

            foreach ((array) $keywords as $kw) {
                $kw = $this->normalizeText((string) $kw);
                if ($kw === '') {
                    continue;
                }

                if (mb_stripos($normalizedText, $kw) !== false) {
                    $sector = Sector::where('active', true)->where('slug', $sectorSlug)->first();
                    if ($sector) {
                        return $sector;
                    }
                }
            }
        }

        return null;
    }

    private function sendInitialMenuIfAllowed(Conversation $conversation): void
    {
        if (!$this->needsInitialMenu($conversation)) {
            return;
        }

        $cooldownMinutes = max(0, (int) config('bot.menu_cooldown_minutes', 5));
        if ($cooldownMinutes === 0) {
            $this->sendInitialMenu($conversation);
            return;
        }

        $lastBot = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'bot')
            ->latest('sent_at')
            ->first();

        if ($lastBot && $lastBot->sent_at) {
            $lastAt = $lastBot->sent_at instanceof Carbon ? $lastBot->sent_at : Carbon::parse($lastBot->sent_at);
            if ($lastAt->diffInMinutes(now()) < $cooldownMinutes) {
                return;
            }
        }

        $this->sendInitialMenu($conversation);
    }

    private function sendBotText(Conversation $conversation, string $text, array $meta = []): void
    {
        $this->whatsAppClient->sendTextMessage($conversation->whatsapp_number, $text);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'bot',
            'type' => 'text',
            'body' => $text,
            'media_url' => null,
            'sent_at' => now(),
            'raw_payload' => array_merge(['bot' => true], $meta),
        ]);
    }
}

