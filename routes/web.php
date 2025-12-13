<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    
    // Conversas
    Route::resource('conversations', \App\Http\Controllers\ConversationController::class)->only(['index', 'show']);
    Route::post('conversations/{conversation}/assume', [\App\Http\Controllers\ConversationController::class, 'assume'])->name('conversations.assume');
    Route::post('conversations/{conversation}/message', [\App\Http\Controllers\ConversationController::class, 'sendMessage'])->name('conversations.sendMessage');
    Route::post('conversations/{conversation}/transfer', [\App\Http\Controllers\ConversationController::class, 'transfer'])->name('conversations.transfer');
    Route::post('conversations/{conversation}/close', [\App\Http\Controllers\ConversationController::class, 'close'])->name('conversations.close');
    
    // Setores (Admin)
    Route::resource('sectors', \App\Http\Controllers\SectorController::class)->middleware('can:viewAny,App\Models\Sector');
    
    // Usuários (Admin)
    Route::resource('users', \App\Http\Controllers\UserController::class)->middleware('can:viewAny,App\Models\User');
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Webhook público (sem autenticação)
Route::post('/webhook/whatsapp', [\App\Http\Controllers\Webhook\WhatsAppWebhookController::class, 'handle'])
    ->name('webhook.whatsapp');

require __DIR__.'/auth.php';

// Rotas de desenvolvimento (apenas em ambiente local)
if (app()->environment('local')) {
    Route::get('/dev/simulate', function () {
        return view('dev.simulate');
    })->name('dev.simulate');

    Route::post('/dev/simulate-whatsapp', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'number' => 'required|string',
            'message' => 'required|string',
        ]);

        $number = $request->input('number');
        $remoteJid = str_contains($number, '@') ? $number : $number . '@s.whatsapp.net';

        // Mock HTTP para evitar chamadas reais à Evolution API
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $payload = [
            'event' => 'messages.upsert',
            'instance' => config('revolution.api.instance_id', 'test-instance'),
            'data' => [
                'key' => [
                    'remoteJid' => $remoteJid,
                    'fromMe' => false,
                    'id' => 'SIM_' . time() . '_' . uniqid(),
                ],
                'message' => [
                    'conversation' => $request->input('message'),
                ],
                'messageType' => 'conversation',
            ],
            'sender' => config('revolution.api.instance_id', 'test-instance') . '@s.whatsapp.net',
        ];

        // Cria Request manualmente a partir de JSON (como o webhook real funciona)
        // Isso garante que os dados sejam parseados corretamente
        $webhookRequest = \Illuminate\Http\Request::create(
            '/webhook/whatsapp', 
            'POST', 
            [], 
            [], 
            [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        // Decodifica o JSON no request body para que $request->all() funcione
        $webhookRequest->merge(json_decode($webhookRequest->getContent(), true) ?? []);
        
        // Chama o controller diretamente
        $controller = app(\App\Http\Controllers\Webhook\WhatsAppWebhookController::class);
        
        try {
            $response = $controller->handle($webhookRequest);
            
            // Se a resposta for um Response do Laravel, extrai o status
            $statusCode = $response instanceof \Illuminate\Http\JsonResponse 
                ? $response->getStatusCode() 
                : 200;
            
            return response()->json([
                'success' => true,
                'status' => $statusCode,
                'message' => 'Mensagem simulada com sucesso!',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação: ' . $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao simular WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    })->name('dev.simulate-whatsapp');
}
