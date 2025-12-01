<?php

use App\Http\Controllers\Api\Chat\MentionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Mentions
|--------------------------------------------------------------------------
|
| These routes handle encrypted mention functionality with quantum E2EE
| support for the chat system.
|
*/

Route::middleware(['auth:sanctum', 'throttle:120,1'])->prefix('v1/mentions')->group(function () {
    // Get mentionable entities
    Route::get('/users', [MentionController::class, 'getUsers']);
    Route::get('/channels', [MentionController::class, 'getChannels']);
    Route::get('/groups', [MentionController::class, 'getGroups']);
    
    // Search mentions
    Route::get('/search', [MentionController::class, 'search']);
    
    // Recent mentions
    Route::get('/recent', [MentionController::class, 'getRecentMentions']);
    
    // Create encrypted mention
    Route::post('/create', [MentionController::class, 'createMention']);
    
    // Mention management
    Route::post('/{mentionId}/read', [MentionController::class, 'markAsRead']);
    
    // Statistics
    Route::get('/stats', [MentionController::class, 'getStats']);
});