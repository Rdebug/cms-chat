<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookWhatsAppBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Evita exceção no RevolutionClient e bloqueia qualquer request externo
        config()->set('revolution.api.base_url', 'http://localhost:8080');
        config()->set('revolution.api.token', 'test-token');
        config()->set('revolution.api.instance_id', 'test-instance');

        Http::fake([
            'http://localhost:8080/*' => Http::response(['ok' => true], 200),
            'https://api.openai.com/*' => Http::response(['ok' => true], 200),
        ]);
    }

    private function payload(string $remoteJid, string $text): array
    {
        return [
            'event' => 'messages.upsert',
            'instance' => 'test-instance',
            'data' => [
                'key' => [
                    'remoteJid' => $remoteJid,
                    'fromMe' => false,
                    'id' => 'MSG_1',
                ],
                'message' => [
                    'conversation' => $text,
                ],
                'messageType' => 'conversation',
            ],
            'sender' => '5511000000000@s.whatsapp.net',
        ];
    }

    public function test_menu_is_sent_only_once_on_first_message(): void
    {
        Sector::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'menu_code' => '1', 'active' => true]);
        Sector::create(['name' => 'Cadastro', 'slug' => 'cadastro', 'menu_code' => '2', 'active' => true]);

        $remote = '5511999999999@s.whatsapp.net';

        $this->postJson('/webhook/whatsapp', $this->payload($remote, 'oi'))->assertOk();
        $this->postJson('/webhook/whatsapp', $this->payload($remote, 'mais uma msg'))->assertOk();

        $conversation = Conversation::where('whatsapp_number', '5511999999999')->firstOrFail();
        $botMessages = Message::where('conversation_id', $conversation->id)->where('direction', 'bot')->get();

        // menu 1x, sem reenviar automaticamente
        $this->assertCount(1, $botMessages);
        $this->assertStringContainsString('ou digite sua dúvida', (string) $botMessages->first()->body);
    }

    public function test_menu_command_resends_menu(): void
    {
        Sector::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'menu_code' => '1', 'active' => true]);

        $remote = '5511888888888@s.whatsapp.net';

        $this->postJson('/webhook/whatsapp', $this->payload($remote, 'oi'))->assertOk();
        $this->postJson('/webhook/whatsapp', $this->payload($remote, 'menu'))->assertOk();

        $conversation = Conversation::where('whatsapp_number', '5511888888888')->firstOrFail();
        $botMessages = Message::where('conversation_id', $conversation->id)->where('direction', 'bot')->get();

        $this->assertCount(2, $botMessages);
    }

    public function test_keyword_routes_assign_sector_and_queue(): void
    {
        $financeiro = Sector::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'menu_code' => '1', 'active' => true]);

        $remote = '5511777777777@s.whatsapp.net';
        $this->postJson('/webhook/whatsapp', $this->payload($remote, 'preciso de boleto'))->assertOk();

        $conversation = Conversation::where('whatsapp_number', '5511777777777')->firstOrFail();
        $conversation->refresh();

        $this->assertEquals($financeiro->id, $conversation->current_sector_id);
        $this->assertEquals('queued', $conversation->status);

        $botMessages = Message::where('conversation_id', $conversation->id)->where('direction', 'bot')->get();
        $this->assertTrue($botMessages->count() >= 1);
    }

    public function test_bot_does_not_respond_when_conversation_has_agent(): void
    {
        Sector::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'menu_code' => '1', 'active' => true]);

        $agent = User::factory()->create(['role' => 'agent']);
        $conversation = Conversation::create([
            'whatsapp_number' => '5511666666666',
            'status' => 'in_progress',
            'current_agent_id' => $agent->id,
            'last_message_at' => now(),
        ]);

        $remote = '5511666666666@s.whatsapp.net';
        $this->postJson('/webhook/whatsapp', $this->payload($remote, 'oi'))->assertOk();

        $botMessages = Message::where('conversation_id', $conversation->id)->where('direction', 'bot')->count();
        $this->assertEquals(0, $botMessages);
    }
}


