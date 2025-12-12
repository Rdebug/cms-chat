<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Sector;
use App\Models\User;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\TransferConversationRequest;
use App\Services\ConversationService;
use App\Services\WhatsApp\RevolutionClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversationService,
        private RevolutionClient $whatsAppClient
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Conversation::query()
            ->with(['currentSector', 'currentAgent'])
            ->orderBy('last_message_at', 'desc');

        // Filtros
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('sector_id') && $request->sector_id) {
            $query->where('current_sector_id', $request->sector_id);
        }

        if ($request->has('agent_id') && $request->agent_id) {
            $query->where('current_agent_id', $request->agent_id);
        }

        // Para agentes, por padrão filtrar por seu setor e status aberto
        if ($user->isAgent() && $user->sector_id) {
            if (!$request->has('sector_id')) {
                $query->where('current_sector_id', $user->sector_id);
            }
            if (!$request->has('status')) {
                $query->whereIn('status', ['new', 'queued', 'in_progress']);
            }
        }

        $conversations = $query->paginate(20)->through(fn($conv) => [
            'id' => $conv->id,
            'whatsapp_number' => $conv->whatsapp_number,
            'client_name' => $conv->client_name,
            'status' => $conv->status,
            'sector' => $conv->currentSector?->name,
            'agent' => $conv->currentAgent?->name,
            'last_message_at' => $conv->last_message_at?->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Conversations/Index', [
            'conversations' => $conversations,
            'filters' => $request->only(['status', 'sector_id', 'agent_id']),
        ]);
    }

    public function show(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $conversation->load(['currentSector', 'currentAgent', 'messages', 'transferLogs.fromSector', 'transferLogs.toSector', 'transferLogs.fromAgent', 'transferLogs.toAgent']);

        $messages = $conversation->messages()
            ->orderBy('sent_at', 'asc')
            ->get()
            ->map(fn($msg) => [
                'id' => $msg->id,
                'direction' => $msg->direction,
                'type' => $msg->type,
                'body' => $msg->body,
                'media_url' => $msg->media_url,
                'sent_at' => $msg->sent_at->format('d/m/Y H:i:s'),
            ]);

        $transferLogs = $conversation->transferLogs()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'from_sector' => $log->fromSector?->name,
                'to_sector' => $log->toSector->name,
                'from_agent' => $log->fromAgent?->name,
                'to_agent' => $log->toAgent?->name,
                'note' => $log->note,
                'created_at' => $log->created_at->format('d/m/Y H:i'),
            ]);

        $sectors = Sector::where('active', true)->orderBy('name')->get()->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ]);

        return Inertia::render('Conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'whatsapp_number' => $conversation->whatsapp_number,
                'client_name' => $conversation->client_name,
                'status' => $conversation->status,
                'sector' => $conversation->currentSector?->name,
                'sector_id' => $conversation->current_sector_id,
                'agent' => $conversation->currentAgent?->name,
                'agent_id' => $conversation->current_agent_id,
                'last_message_at' => $conversation->last_message_at?->format('d/m/Y H:i:s'),
            ],
            'messages' => $messages,
            'transferLogs' => $transferLogs,
            'sectors' => $sectors,
        ]);
    }

    public function assume(Conversation $conversation)
    {
        $this->authorize('assume', $conversation);

        $this->conversationService->assignAgent($conversation, Auth::user());

        return redirect()->back()->with('success', 'Conversa assumida com sucesso!');
    }

    public function sendMessage(StoreMessageRequest $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $messageText = $request->validated()['message'];

        // Envia via WhatsApp
        $this->whatsAppClient->sendTextMessage($conversation->whatsapp_number, $messageText);

        // Salva mensagem
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'agent',
            'type' => 'text',
            'body' => $messageText,
            'sent_at' => now(),
        ]);

        // Atualiza última mensagem da conversa
        $conversation->update(['last_message_at' => now()]);

        return redirect()->back()->with('success', 'Mensagem enviada com sucesso!');
    }

    public function transfer(TransferConversationRequest $request, Conversation $conversation)
    {
        $this->authorize('transfer', $conversation);

        $toSector = Sector::findOrFail($request->validated()['sector_id']);
        $toAgent = $request->has('agent_id') ? User::find($request->validated()['agent_id']) : null;

        $this->conversationService->transferConversation(
            $conversation,
            $toSector,
            $toAgent,
            $request->validated()['note'] ?? null
        );

        return redirect()->back()->with('success', 'Conversa transferida com sucesso!');
    }

    public function close(Conversation $conversation)
    {
        $this->authorize('close', $conversation);

        $this->conversationService->closeConversation($conversation);

        return redirect()->route('conversations.index')->with('success', 'Conversa encerrada com sucesso!');
    }
}
