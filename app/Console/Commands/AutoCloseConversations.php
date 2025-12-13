<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\WhatsApp\RevolutionClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoCloseConversations extends Command
{
    protected $signature = 'conversations:auto-close {--dry-run : Não altera nada, apenas mostra quantas seriam fechadas}';

    protected $description = 'Encerra conversas por inatividade (feature flag via BOT_AUTO_CLOSE_MINUTES)';

    public function __construct(private readonly RevolutionClient $whatsAppClient)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $minutes = (int) config('bot.auto_close_minutes', 0);
        if ($minutes <= 0) {
            $this->info('BOT_AUTO_CLOSE_MINUTES=0 (auto-close desabilitado).');
            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes($minutes);

        $query = Conversation::query()
            ->whereIn('status', ['queued', 'in_progress', 'waiting_client'])
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<=', $cutoff);

        $count = (int) $query->count();
        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$count} conversas seriam encerradas (cutoff: {$cutoff}).");
            return self::SUCCESS;
        }

        $sendToWhatsApp = (bool) config('bot.auto_close_send_message', false);
        $text = 'Atendimento encerrado por inatividade. Se precisar, envie uma nova mensagem para reabrir.';

        $query->orderBy('id')->chunkById(200, function ($conversations) use ($sendToWhatsApp, $text) {
            foreach ($conversations as $conversation) {
                DB::transaction(function () use ($conversation, $sendToWhatsApp, $text) {
                    /** @var Conversation $conversation */
                    $conversation->status = 'closed';
                    $conversation->current_agent_id = null;
                    $conversation->save();

                    Message::create([
                        'conversation_id' => $conversation->id,
                        'direction' => 'system',
                        'type' => 'text',
                        'body' => $text,
                        'media_url' => null,
                        'sent_at' => now(),
                        'raw_payload' => ['kind' => 'auto_close', 'by' => 'scheduler'],
                    ]);

                    $conversation->last_message_at = now();
                    $conversation->save();

                    if ($sendToWhatsApp) {
                        $this->whatsAppClient->sendTextMessage($conversation->whatsapp_number, $text);
                    }
                });
            }
        });

        $this->info("Encerradas {$count} conversas por inatividade.");
        return self::SUCCESS;
    }
}


