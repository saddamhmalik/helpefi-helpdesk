<?php

use App\Domains\Ai\Controllers\Api\AiDeflectionController as ApiAiDeflectionController;
use App\Domains\Chat\Controllers\Api\ChatWidgetController as ApiChatWidgetController;
use App\Domains\Channels\Controllers\Api\ChannelController as ApiChannelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/channels/inbound/email', [ApiChannelController::class, 'inboundEmail'])
        ->middleware('tenancy.public-api:inbound');

    Route::middleware(['tenancy.public-api:widget', 'chat.widget.cors', 'throttle:120,1'])->prefix('chat')->group(function () {
        Route::get('/config', [ApiChatWidgetController::class, 'config']);
        Route::post('/sessions', [ApiChatWidgetController::class, 'start']);
        Route::post('/sessions/{session}/messages', [ApiChatWidgetController::class, 'send']);
        Route::get('/sessions/{session}/poll', [ApiChatWidgetController::class, 'poll']);
    });

    Route::middleware(['tenancy.public-api:widget', 'chat.widget.cors', 'throttle:120,1'])->prefix('deflection')->group(function () {
        Route::get('/config', [ApiAiDeflectionController::class, 'config']);
        Route::post('/ask', [ApiAiDeflectionController::class, 'ask']);
        Route::post('/feedback', [ApiAiDeflectionController::class, 'feedback']);
        Route::post('/escalate', [ApiAiDeflectionController::class, 'escalate']);
    });
});
