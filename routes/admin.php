<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;

$prefix = trim((string) config('admin.prefix'), '/');

Route::middleware(['web', 'admin.basic', 'admin.visit'])
    ->prefix($prefix)
    ->as('admin.')
    ->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

        Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
        Route::get('/reports/thread/{threadId}', [AdminController::class, 'reportThreadDetail'])->name('reports.thread');
        Route::get('/reports/response/{responseId}', [AdminController::class, 'reportResponseDetail'])->name('reports.response');

        Route::post('/reports/thread/{threadId}/approve', [AdminController::class, 'approveThreadReports'])->name('reports.thread.approve');
        Route::post('/reports/thread/{threadId}/reject', [AdminController::class, 'rejectThreadReports'])->name('reports.thread.reject');
        Route::post('/reports/thread/{threadId}/toggle-flag', [AdminController::class, 'toggleThreadFlag'])->name('reports.thread.toggle-flag');

        Route::post('/reports/response/{responseId}/approve', [AdminController::class, 'approveResponseReports'])->name('reports.response.approve');
        Route::post('/reports/response/{responseId}/reject', [AdminController::class, 'rejectResponseReports'])->name('reports.response.reject');
        Route::post('/reports/response/{responseId}/toggle-flag', [AdminController::class, 'toggleResponseFlag'])->name('reports.response.toggle-flag');

        Route::post('/reports/profile/{reportedUserId}/approve', [AdminController::class, 'approveProfileReports'])->name('reports.profile.approve');
        Route::post('/reports/profile/{reportedUserId}/reject', [AdminController::class, 'rejectProfileReports'])->name('reports.profile.reject');
        
        Route::post('/reports/{reportId}/set-out-count', [AdminController::class, 'setReportOutCount'])->name('reports.set-out-count');
        Route::get('/suggestions', [AdminController::class, 'suggestions'])->name('suggestions');

        // お知らせ配信
        Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
        Route::post('/messages', [AdminController::class, 'messagesStore'])->name('messages.store');
        Route::post('/messages/{messageId}/cancel', [AdminController::class, 'messagesCancel'])->name('messages.cancel');

        Route::post('/suggestions/{suggestion}/approve', [AdminController::class, 'approveSuggestion'])->name('suggestions.approve');
        Route::post('/suggestions/{suggestion}/reject', [AdminController::class, 'rejectSuggestion'])->name('suggestions.reject');
        Route::post('/suggestions/{suggestion}/toggle-star', [AdminController::class, 'toggleSuggestionStar'])->name('suggestions.toggle-star');

        // ログ管理
        Route::get('/logs', [AdminController::class, 'logs'])->name('logs');
        Route::get('/logs/download', [AdminController::class, 'downloadLogs'])->name('logs.download');
        Route::post('/logs/clear', [AdminController::class, 'clearLogs'])->name('logs.clear');
    });


