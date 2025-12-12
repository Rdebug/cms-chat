<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Services\BotRoutingService;
use App\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private ConversationService $conversationService,
        private BotRoutingService $botRoutingService
    ) {}

    /**
     * Processa webhook recebido da Revolution API
     */
    public function handle(Request $request)
    {
        try {
            // Valida assinatura do webhook se configurada
            if (config('revolution.api.webhook_secret')) {
                $this->validateWebhookSignature($request);
            }

            $payload = $request->all();

            $summary = [
                'event' => $payload['event'] ?? null,
                'instance' => $payload['instance'] ?? null,
                'remoteJid' => $payload['data']['key']['remoteJid'] ?? null,
                'fromMe' => $payload['data']['key']['fromMe'] ?? null,
                'messageType' => $payload['data']['messageType'] ?? null,
            ];
            Log::info('WhatsApp webhook received', $summary);

            if (config('bot.log_full_webhook_payload')) {
                Log::debug('WhatsApp webhook full payload', ['payload' => $payload]);
            }

            // Evolution envia muitos eventos (call, chats.update, messages.update, etc).
            // Nosso sistema precisa apenas de mensagens novas (messages.upsert).
            if (isset($payload['event']) && is_string($payload['event']) && $payload['event'] !== 'messages.upsert') {
                return response()->json(['status' => 'ignored', 'event' => $payload['event']], 200);
            }

            // Normaliza payload da Revolution API
            $event = $this->normalizePayload($payload);

            if (!$event) {
                // Se chegou aqui, é um payload que não sabemos processar. Evitamos warning para não poluir log.
                return response()->json(['status' => 'ignored'], 200);
            }

            // Processa apenas mensagens recebidas (não enviadas)
            if ($event['type'] !== 'message' || $event['direction'] !== 'incoming') {
                return response()->json(['status' => 'ignored'], 200);
            }

            $number = $this->extractNumber($event['from']);
            $messageText = $event['body'] ?? '';
            $messageType = $event['message_type'] ?? 'text';
            $messageId = $event['message_id'] ?? null;
            $mediaUrl = $event['media_url'] ?? null;
            $pushName = $event['push_name'] ?? null;

            // Busca ou cria conversa
            $conversation = $this->conversationService->findOrCreateConversationByNumber($number);

            // Atualiza último horário de mensagem
            $conversation->update(['last_message_at' => now()]);

            // Atualiza nome do cliente (melhor esforço)
            if ($pushName && !$conversation->client_name) {
                $conversation->update(['client_name' => $pushName]);
            }

            // Registra mensagem
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'client',
                'type' => $messageType,
                'body' => $messageText ?: null,
                'media_url' => $mediaUrl,
                'sent_at' => now(),
                'raw_payload' => $payload,
            ]);

            $this->botRoutingService->handleIncomingClientMessage($conversation, $messageText);

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Error processing WhatsApp webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Valida assinatura do webhook
     */
    private function validateWebhookSignature(Request $request): void
    {
        $secret = config('revolution.api.webhook_secret');
        $signature = $request->header('X-Webhook-Signature');
        
        // Implementação básica - ajustar conforme documentação da Revolution API
        if (!$signature || !Hash::check($request->getContent(), $signature)) {
            throw new \InvalidArgumentException('Invalid webhook signature');
        }
    }

    /**
     * Normaliza payload da Revolution API para formato padrão
     */
    private function normalizePayload(array $payload): ?array
    {
        // Evolution API/Manager costuma enviar:
        // {
        //   "event": "messages.upsert",
        //   "instance": "...",
        //   "data": { "key": {...}, "message": {...}, "messageType": "conversation", ... },
        //   "sender": "55...@s.whatsapp.net",
        //   ...
        // }
        if (isset($payload['event']) && is_string($payload['event']) && isset($payload['data']) && is_array($payload['data'])) {
            // Só processamos eventos de mensagem. Outros eventos (ex.: chats.update) devem ser ignorados.
            if (!str_starts_with($payload['event'], 'messages.')) {
                return null;
            }

            $data = $payload['data'];
            // Em alguns eventos o "data" pode ser uma lista. Para messages.* esperamos um objeto/array com "key".
            if (!isset($data['key']) || !is_array($data['key'])) {
                return null;
            }

            $fromMe = (bool) (($data['key']['fromMe'] ?? false));

            // No Evolution, "sender" costuma ser o número da instância (o SEU WhatsApp).
            // A chave correta da conversa é o chat remoto: key.remoteJid (contato @s.whatsapp.net ou grupo @g.us).
            $chatJid = $data['key']['remoteJid'] ?? null;
            if (!$chatJid || !is_string($chatJid)) {
                return null;
            }
            $from = $chatJid;

            $body = null;
            if (isset($data['message']['conversation'])) {
                $body = $data['message']['conversation'];
            } elseif (isset($data['message']['extendedTextMessage']['text'])) {
                $body = $data['message']['extendedTextMessage']['text'];
            } elseif (isset($data['message']['imageMessage']['caption'])) {
                $body = $data['message']['imageMessage']['caption'];
            } elseif (isset($data['message']['videoMessage']['caption'])) {
                $body = $data['message']['videoMessage']['caption'];
            }

            $mediaUrl = null;
            // Se você habilitar envio de base64/url no Evolution, adapte aqui conforme seu setup.
            if (isset($data['message']['imageMessage']['url'])) {
                $mediaUrl = $data['message']['imageMessage']['url'];
            } elseif (isset($data['message']['videoMessage']['url'])) {
                $mediaUrl = $data['message']['videoMessage']['url'];
            } elseif (isset($data['message']['audioMessage']['url'])) {
                $mediaUrl = $data['message']['audioMessage']['url'];
            } elseif (isset($data['message']['documentMessage']['url'])) {
                $mediaUrl = $data['message']['documentMessage']['url'];
            }

            $messageId = $data['key']['id'] ?? ($data['id'] ?? null);

            $messageType = $this->detectMessageType($data);

            return [
                'type' => 'message',
                'direction' => $fromMe ? 'outgoing' : 'incoming',
                // "from" aqui representa a chave do chat/conversa
                'from' => $from,
                'body' => $body,
                'message_type' => $messageType,
                'message_id' => $messageId,
                'media_url' => $mediaUrl,
                'raw_event' => $payload['event'],
                // Metadados úteis para debug/identificação (não usado como chave de conversa)
                'sender' => $payload['sender'] ?? null,
                'push_name' => $data['pushName'] ?? null,
            ];
        }

        // Formato alternativo: 'event' pode vir como array (fallback)
        if (isset($payload['event']) && is_array($payload['event'])) {
            $event = $payload['event'];

            $from = $event['from'] ?? null;
            if (!$from && isset($event['key']['remoteJid'])) {
                $from = $event['key']['remoteJid'];
            }

            $body = $event['body'] ?? null;
            if (!$body && isset($event['message']['conversation'])) {
                $body = $event['message']['conversation'];
            } elseif (!$body && isset($event['message']['extendedTextMessage']['text'])) {
                $body = $event['message']['extendedTextMessage']['text'];
            }

            $messageId = $event['id'] ?? null;
            if (!$messageId && isset($event['key']['id'])) {
                $messageId = $event['key']['id'];
            }

            $mediaUrl = null;
            if (isset($event['message']['imageMessage']['url'])) {
                $mediaUrl = $event['message']['imageMessage']['url'];
            } elseif (isset($event['message']['videoMessage']['url'])) {
                $mediaUrl = $event['message']['videoMessage']['url'];
            } elseif (isset($event['message']['audioMessage']['url'])) {
                $mediaUrl = $event['message']['audioMessage']['url'];
            } elseif (isset($event['message']['documentMessage']['url'])) {
                $mediaUrl = $event['message']['documentMessage']['url'];
            }

            return [
                'type' => $event['type'] ?? 'message',
                'direction' => $event['direction'] ?? (($event['fromMe'] ?? false) ? 'outgoing' : 'incoming'),
                'from' => $from,
                'body' => $body,
                'message_type' => $this->detectMessageType($event),
                'message_id' => $messageId,
                'media_url' => $mediaUrl,
            ];
        }

        // Fallback para estrutura direta
        if (isset($payload['from']) || isset($payload['key'])) {
            $from = $payload['from'] ?? null;
            if (!$from && isset($payload['key']['remoteJid'])) {
                $from = $payload['key']['remoteJid'];
            }
            
            $body = $payload['body'] ?? null;
            if (!$body && isset($payload['message']['conversation'])) {
                $body = $payload['message']['conversation'];
            }
            
            $messageId = $payload['id'] ?? null;
            if (!$messageId && isset($payload['key']['id'])) {
                $messageId = $payload['key']['id'];
            }
            
            return [
                'type' => 'message',
                'direction' => isset($payload['fromMe']) && $payload['fromMe'] ? 'outgoing' : 'incoming',
                'from' => $from,
                'body' => $body,
                'message_type' => $this->detectMessageType($payload),
                'message_id' => $messageId,
                'media_url' => $payload['mediaUrl'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Detecta tipo de mensagem
     */
    private function detectMessageType(array $event): string
    {
        // Evolution envia messageType (ex: "conversation")
        if (isset($event['messageType']) && is_string($event['messageType'])) {
            return match ($event['messageType']) {
                'conversation', 'extendedTextMessage' => 'text',
                'imageMessage' => 'image',
                'videoMessage' => 'video',
                'audioMessage' => 'audio',
                'documentMessage' => 'document',
                default => 'other',
            };
        }

        if (isset($event['message'])) {
            $message = $event['message'];
            
            if (isset($message['imageMessage'])) return 'image';
            if (isset($message['videoMessage'])) return 'video';
            if (isset($message['audioMessage'])) return 'audio';
            if (isset($message['documentMessage'])) return 'document';
        }

        return 'text';
    }

    /**
     * Extrai número do formato da API
     */
    private function extractNumber(string $from): string
    {
        // Remove @s.whatsapp.net se presente
        return str_replace('@s.whatsapp.net', '', $from);
    }
}
