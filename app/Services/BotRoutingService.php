<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Sector;
use App\Services\Ai\AiRoutingService;
use App\Services\WhatsApp\RevolutionClient;

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
            $this->sendInitialMenuAndMark($conversation);
            return;
        }

        // Comandos para falar com humano (fila Recepção)
        if ($text !== '' && $this->isAnyCommand($text, (array) config('bot.human_handoff_commands', []))) {
            $sector = $this->getOrCreateReceptionSector();
            $this->conversationService->assignSector($conversation, $sector);
            $this->markBotState($conversation, 'handoff');
            $this->sendBotText(
                $conversation,
                "Certo! Vou te encaminhar para um atendente humano. Aguarde um instante.",
                meta: ['kind' => 'handoff']
            );
            return;
        }

        // Se ainda não tem setor, manda o menu apenas na 1ª mensagem e depois trabalha com texto/keywords
        if ($conversation->current_sector_id === null) {
            // Primeira mensagem: envia menu 1x por conversa
            if ($this->needsInitialMenu($conversation) && !$this->hasSentMenu($conversation)) {
                $this->sendInitialMenuAndMark($conversation);
            }

            // Se for número, tenta processar como menu
            if ($this->isMenuSelection($text)) {
                if ($this->processMenuSelection($conversation, $text)) {
                    $this->markBotState($conversation, 'handoff');
                }
                return;
            }

            // Keywords: tentar encaminhar automaticamente sem spam
            $matched = $this->matchSectorsByKeywords($text);
            if ($matched->count() === 1) {
                $sector = $matched->first();
                $this->conversationService->assignSector($conversation, $sector);
                $this->markBotState($conversation, 'handoff');
                $this->sendBotText(
                    $conversation,
                    "Entendi! Vou te direcionar para o setor *{$sector->name}*. Aguarde, em breve um atendente entrará em contato.",
                    meta: ['kind' => 'keyword_match', 'sector_id' => $sector->id]
                );
                return;
            }

            if ($matched->count() > 1) {
                $this->sendClarifySectorMessage($conversation, $matched->values()->all());
                return;
            }

            // IA (opcional): tentativa de classificar setor quando keywords falham
            if ($text !== '' && (bool) config('bot.ai_routing_enabled', false)) {
                if ($this->tryAiRouting($conversation, $text)) {
                    return;
                }
            }

            // Sem match e sem escolha: não reenviar menu automaticamente.
            // No máximo, um lembrete com cooldown para orientar o cliente.
            $this->sendTriageNudgeIfAllowed($conversation);
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
            
            $message .= "\nDigite apenas o número do setor desejado *ou digite sua dúvida* (ex.: \"preciso de boleto\").";
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
        $conversation->bot_state = 'idle';
        $conversation->bot_last_prompt_at = null;
        $conversation->bot_menu_sent_at = null;
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

    private function matchSectorsByKeywords(string $normalizedText): \Illuminate\Support\Collection
    {
        $matches = collect();
        if ($normalizedText === '') {
            return $matches;
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
                        $matches->push($sector);
                    }
                    break;
                }
            }
        }

        return $matches->unique('id')->values();
    }

    private function hasSentMenu(Conversation $conversation): bool
    {
        $state = (string) ($conversation->bot_state ?? 'idle');
        if ($state === 'menu_sent') {
            return true;
        }

        return $conversation->bot_menu_sent_at !== null;
    }

    private function sendInitialMenuAndMark(Conversation $conversation): void
    {
        $this->sendInitialMenu($conversation);
        $conversation->bot_state = 'menu_sent';
        $conversation->bot_menu_sent_at = now();
        $conversation->bot_last_prompt_at = now();
        $conversation->save();
    }

    private function sendTriageNudgeIfAllowed(Conversation $conversation): void
    {
        // Evita ficar respondendo a cada mensagem quando o cliente não escolhe setor.
        $cooldownMinutes = max(0, (int) config('bot.menu_cooldown_minutes', 5));
        if ($cooldownMinutes === 0) {
            return;
        }

        $last = $conversation->bot_last_prompt_at;
        if ($last && $last->diffInMinutes(now()) < $cooldownMinutes) {
            return;
        }

        $this->sendBotText(
            $conversation,
            "Para escolher, responda com o *número do setor* acima. Se preferir, digite *menu* para ver as opções novamente ou descreva sua dúvida.",
            meta: ['kind' => 'triage_nudge']
        );

        $conversation->bot_last_prompt_at = now();
        $conversation->save();
    }

    private function sendClarifySectorMessage(Conversation $conversation, array $sectors): void
    {
        $message = "Entendi sua dúvida, mas preciso confirmar o setor. Escolha uma opção digitando o número:\n\n";
        foreach ($sectors as $sector) {
            if ($sector instanceof Sector) {
                $message .= "{$sector->menu_code} – {$sector->name}\n";
            }
        }
        $message .= "\nOu digite *menu* para ver todas as opções.";

        $this->sendBotText($conversation, $message, meta: ['kind' => 'clarify_sector']);
        $conversation->bot_last_prompt_at = now();
        $conversation->save();
    }

    private function markBotState(Conversation $conversation, string $state): void
    {
        $conversation->bot_state = $state;
        $conversation->save();
    }

    private function tryAiRouting(Conversation $conversation, string $normalizedText): bool
    {
        // Throttle básico para não chamar IA a cada mensagem
        $cooldownMinutes = max(0, (int) config('bot.menu_cooldown_minutes', 5));
        $last = $conversation->bot_last_prompt_at;
        if ($cooldownMinutes > 0 && $last && $last->diffInMinutes(now()) < $cooldownMinutes) {
            return false;
        }

        $sectors = Sector::where('active', true)
            ->orderBy('menu_code')
            ->get(['slug', 'name', 'menu_code'])
            ->map(fn (Sector $s) => [
                'slug' => $s->slug,
                'name' => $s->name,
                'menu_code' => $s->menu_code,
            ])
            ->values()
            ->all();

        /** @var AiRoutingService $ai */
        $ai = app(AiRoutingService::class);
        $result = $ai->classifySector($normalizedText, $sectors);

        // Marca para throttle (mesmo se falhar)
        $conversation->bot_last_prompt_at = now();
        $conversation->save();

        if (!$result) {
            return false;
        }

        $sectorSlug = isset($result['sector_slug']) && is_string($result['sector_slug']) ? $result['sector_slug'] : null;
        $clarifying = isset($result['clarifying_question']) && is_string($result['clarifying_question'])
            ? $result['clarifying_question']
            : null;

        if ($sectorSlug) {
            $sector = Sector::where('active', true)->where('slug', $sectorSlug)->first();
            if (!$sector) {
                return false;
            }

            $this->conversationService->assignSector($conversation, $sector);
            $this->markBotState($conversation, 'handoff');

            $this->sendBotText(
                $conversation,
                "Entendi! Vou te direcionar para o setor *{$sector->name}*. Aguarde, em breve um atendente entrará em contato.",
                meta: ['kind' => 'ai_match', 'sector_id' => $sector->id, 'confidence' => $result['confidence'] ?? null]
            );

            return true;
        }

        if ($clarifying) {
            $this->sendBotText($conversation, $clarifying, meta: ['kind' => 'ai_clarify', 'confidence' => $result['confidence'] ?? null]);
            return true;
        }

        return false;
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

