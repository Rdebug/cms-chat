<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoCloseConversationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('revolution.api.base_url', 'http://localhost:8080');
        config()->set('revolution.api.token', 'test-token');
        config()->set('revolution.api.instance_id', 'test-instance');

        Http::fake([
            'http://localhost:8080/*' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_auto_close_command_closes_inactive_conversations(): void
    {
        config()->set('bot.auto_close_minutes', 1);
        config()->set('bot.auto_close_send_message', false);

        $conversation = Conversation::create([
            'whatsapp_number' => '5511555555555',
            'status' => 'queued',
            'last_message_at' => now()->subMinutes(10),
        ]);

        $this->artisan('conversations:auto-close')->assertExitCode(0);

        $conversation->refresh();
        $this->assertEquals('closed', $conversation->status);

        $systemMessages = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'system')
            ->count();

        $this->assertEquals(1, $systemMessages);
    }
}




