<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\GreetingController;

/*
|--------------------------------------------------------------------------
| WhatsApp API Routes
|--------------------------------------------------------------------------
|
| Rotas para gerenciamento do WhatsApp, chatbot e mensagens de saudação
|
*/

// Rotas protegidas por autenticação
Route::middleware(['auth:sanctum'])->group(function () {
    
    // WhatsApp Instances
    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        Route::get('/', [WhatsAppController::class, 'index'])->name('index');
        Route::post('/', [WhatsAppController::class, 'store'])->name('store');
        Route::get('/{instance}', [WhatsAppController::class, 'show'])->name('show');
        Route::put('/{instance}', [WhatsAppController::class, 'update'])->name('update');
        Route::delete('/{instance}', [WhatsAppController::class, 'destroy'])->name('destroy');
        
        // Ações de conexão
        Route::post('/{instance}/connect', [WhatsAppController::class, 'connect'])->name('connect');
        Route::post('/{instance}/disconnect', [WhatsAppController::class, 'disconnect'])->name('disconnect');
        Route::get('/{instance}/status', [WhatsAppController::class, 'getStatus'])->name('status');
        Route::get('/{instance}/qr-code', [WhatsAppController::class, 'getQrCode'])->name('qr-code');
        
        // Mensagens
        Route::post('/{instance}/send-message', [WhatsAppController::class, 'sendMessage'])->name('send-message');
        Route::get('/{instance}/messages', [WhatsAppController::class, 'getMessages'])->name('messages');
        Route::get('/{instance}/conversations', [WhatsAppController::class, 'getConversations'])->name('conversations');
        Route::get('/{instance}/conversations/{phoneNumber}', [WhatsAppController::class, 'getConversation'])->name('conversation');
        
        // Estatísticas
        Route::get('/{instance}/stats', [WhatsAppController::class, 'getStats'])->name('stats');
        Route::get('/{instance}/stats/messages', [WhatsAppController::class, 'getMessageStats'])->name('message-stats');
    });
    
    // Chatbot Responses
    Route::prefix('chatbot')->name('chatbot.')->group(function () {
        Route::get('/', [ChatbotController::class, 'index'])->name('index');
        Route::post('/', [ChatbotController::class, 'store'])->name('store');
        Route::get('/{response}', [ChatbotController::class, 'show'])->name('show');
        Route::put('/{response}', [ChatbotController::class, 'update'])->name('update');
        Route::delete('/{response}', [ChatbotController::class, 'destroy'])->name('destroy');
        
        // Ações específicas
        Route::post('/{response}/activate', [ChatbotController::class, 'activate'])->name('activate');
        Route::post('/{response}/deactivate', [ChatbotController::class, 'deactivate'])->name('deactivate');
        Route::post('/{response}/duplicate', [ChatbotController::class, 'duplicate'])->name('duplicate');
        Route::post('/{response}/test', [ChatbotController::class, 'test'])->name('test');
        
        // Busca e estatísticas
        Route::post('/find-response', [ChatbotController::class, 'findBestResponse'])->name('find-response');
        Route::get('/stats/usage', [ChatbotController::class, 'getUsageStats'])->name('usage-stats');
        Route::get('/stats/performance', [ChatbotController::class, 'getPerformanceStats'])->name('performance-stats');
        
        // Import/Export
        Route::post('/import', [ChatbotController::class, 'import'])->name('import');
        Route::get('/export', [ChatbotController::class, 'export'])->name('export');
        Route::get('/export/{instance}', [ChatbotController::class, 'exportByInstance'])->name('export-instance');
    });
    
    // Greeting Messages
    Route::prefix('greetings')->name('greetings.')->group(function () {
        Route::get('/', [GreetingController::class, 'index'])->name('index');
        Route::post('/', [GreetingController::class, 'store'])->name('store');
        Route::get('/{greeting}', [GreetingController::class, 'show'])->name('show');
        Route::put('/{greeting}', [GreetingController::class, 'update'])->name('update');
        Route::delete('/{greeting}', [GreetingController::class, 'destroy'])->name('destroy');
        
        // Ações específicas
        Route::post('/{greeting}/activate', [GreetingController::class, 'activate'])->name('activate');
        Route::post('/{greeting}/deactivate', [GreetingController::class, 'deactivate'])->name('deactivate');
        Route::post('/{greeting}/duplicate', [GreetingController::class, 'duplicate'])->name('duplicate');
        Route::post('/{greeting}/test', [GreetingController::class, 'test'])->name('test');
        
        // Estatísticas
        Route::get('/stats/usage', [GreetingController::class, 'getUsageStats'])->name('usage-stats');
        Route::get('/stats/performance', [GreetingController::class, 'getPerformanceStats'])->name('performance-stats');
        
        // Import/Export
        Route::post('/import', [GreetingController::class, 'import'])->name('import');
        Route::get('/export', [GreetingController::class, 'export'])->name('export');
        Route::get('/export/{instance}', [GreetingController::class, 'exportByInstance'])->name('export-instance');
    });
});

// Rotas públicas para webhooks (sem autenticação)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/whatsapp/{instance}', [WhatsAppController::class, 'webhook'])->name('whatsapp');
    Route::get('/whatsapp/{instance}/verify', [WhatsAppController::class, 'verifyWebhook'])->name('whatsapp.verify');
});