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
