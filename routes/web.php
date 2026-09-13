<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\CoinController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\ThreadContinuationController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\LocaleRedirectController;
use App\Services\LanguageService;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| 管理者ルートは先に登録し、ADMIN_PREFIX のURLが他ルートに奪われないようにする。
|
| 人間が直接見る UI → /{locale}/... （routes/ui.php、実装は言語で複製しない）
| 機械が利用するエンドポイント → locale なし
|
*/

require __DIR__.'/admin.php';

Route::get('/favicon.ico', function () {
    $path = public_path('images/favicon-16.png');
    if (! is_file($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=604800',
    ]);
});

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');

Route::prefix('api')->middleware(['web', 'throttle:api'])->group(function () {
    if (config('app.debug')) {
        Route::get('/upload-limits', function () {
            $uploadMax = ini_get('upload_max_filesize');
            $postMax = ini_get('post_max_size');
            return response()->json([
                'php' => [
                    'upload_max_filesize' => $uploadMax,
                    'post_max_size' => $postMax,
                    'note' => '音声は5MBまで許可。post_max_size と upload_max_filesize は 5M 以上推奨。',
                ],
                'app_allowed' => [
                    'image_mb' => 1.5,
                    'video_mb' => 10,
                    'audio_mb' => 5,
                ],
            ], 200, ['Content-Type' => 'application/json; charset=UTF-8'], JSON_UNESCAPED_UNICODE);
        })->name('api.upload-limits');
    }

    Route::get('/search/more', [ThreadController::class, 'getMoreSearchThreads'])->middleware('throttle:search')->name('api.threads.search.more');
    Route::get('/tag/{tag}/more', [ThreadController::class, 'getMoreTagThreads'])->name('api.threads.tag.more');
    Route::get('/category/{category}/more', [ThreadController::class, 'getMoreCategoryThreads'])->name('api.threads.category.more');

    Route::middleware('auth')->group(function () {
        Route::get('/profile/threads/more', [ProfileController::class, 'getMoreThreads'])->name('api.profile.threads.more');
        Route::get('/reports/existing', [ReportController::class, 'getExisting'])->name('api.reports.existing');
        Route::get('/coins/balance', [CoinController::class, 'getBalance'])->name('api.coins.balance');
    });
    Route::get('/user/{user}/threads/more', [ProfileController::class, 'getMoreThreads'])->name('api.user.threads.more');
    Route::get('/user/{user}/residence-history', [ProfileController::class, 'getResidenceHistory'])->name('api.user.residence-history');
});

Route::get('/threads/{thread}/responses/search', [ThreadController::class, 'searchResponses'])
    ->middleware('throttle:api')
    ->name('api.threads.responses.search');
Route::get('/threads/{thread}/responses/new', [ThreadController::class, 'getNewResponses'])
    ->middleware('throttle:api')
    ->name('api.threads.responses.new');
Route::get('/threads/{thread}/responses', [ThreadController::class, 'getResponses'])
    ->middleware('throttle:api')
    ->name('api.threads.responses');
Route::post('/threads/{thread}/responses/{response}/translate', [ThreadController::class, 'translateResponse'])
    ->middleware('throttle:api')
    ->name('threads.responses.translate');
Route::post('/threads/{thread}/translate-title', [ThreadController::class, 'translateThreadTitle'])
    ->middleware('throttle:api')
    ->name('threads.translate-title');
Route::post('/threads/{thread}/continuation-request', [ThreadContinuationController::class, 'toggleRequest'])
    ->middleware('auth')
    ->name('threads.continuation-request');

Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider'])->where('provider', 'google')->name('auth.provider.redirect');
Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])->where('provider', 'google')->name('auth.provider.callback');

Route::middleware('auth')->group(function () {
    Route::post('/notifications/{message}/read', [NotificationsController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::post('/notifications/{message}/mandatory-consent', [NotificationsController::class, 'consentMandatory'])->name('notifications.mandatory-consent');
    Route::post('/notifications/{message}/reply', [NotificationsController::class, 'reply'])->middleware('throttle:notice_reply')->name('notifications.reply');
    Route::post('/notifications/{message}/receive-coin', [NotificationsController::class, 'receiveCoin'])->name('notifications.receive-coin');
    Route::post('/notifications/{message}/r18-approve', [NotificationsController::class, 'approveR18Change'])->name('notifications.r18-approve');
    Route::post('/notifications/{message}/r18-reject', [NotificationsController::class, 'rejectR18Change'])->name('notifications.r18-reject');
    Route::post('/notifications/{message}/report-acknowledge', [NotificationsController::class, 'acknowledgeReportRestriction'])->name('notifications.report-acknowledge');

    Route::post('/coins/watch-ad', [CoinController::class, 'watchAd'])->middleware('throttle:ad_api')->name('coins.watch-ad');
    Route::post('/coins/claim-login-reward', [CoinController::class, 'claimLoginReward'])->name('coins.claim-login-reward');

    Route::post('/friends/reject-available', [FriendController::class, 'rejectAvailable'])->name('friends.reject-available');
    Route::post('/friends/delete', [FriendController::class, 'deleteFriend'])->name('friends.delete');
    Route::post('/friends/send-coins', [FriendController::class, 'sendCoins'])->middleware(['throttle:coins_send', 'request.user'])->name('friends.send-coins');
});

$supportedLocales = LanguageService::supportedLocales();

Route::prefix('{locale}')
    ->whereIn('locale', $supportedLocales)
    ->group(base_path('routes/ui.php'));

Route::get('/', [LocaleRedirectController::class, 'home']);

Route::any('{unprefixed}', [LocaleRedirectController::class, 'legacy'])
    ->where('unprefixed', LanguageService::unprefixedUiPattern());
