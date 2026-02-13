<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AcknowledgmentController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\CoinController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\ThreadContinuationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| 管理者ルートは先に登録し、ADMIN_PREFIX のURLが他ルートに奪われないようにする。
|
*/

// 管理者専用ルート（必ず最初に登録）
require __DIR__.'/admin.php';

// トップページにアクセスされたら、ThreadControllerのindexメソッドを呼び出す
Route::get('/', [ThreadController::class, 'index'])->name('threads.index');

// サイト改善要望の投稿
Route::post('/suggestions', [SuggestionController::class, 'store'])->name('suggestions.store');
// GETリクエストの場合はトップページにリダイレクト
Route::get('/suggestions', function() {
    return redirect()->route('threads.index');
});

// 検索機能
Route::get('/search', [ThreadController::class, 'search'])->name('threads.search');
Route::get('/search/more', [ThreadController::class, 'getMoreSearchThreads'])->name('threads.search.more');

// タグ検索機能
Route::get('/tag/{tag}', [ThreadController::class, 'searchByTag'])->name('threads.tag');
Route::get('/tag/{tag}/more', [ThreadController::class, 'getMoreTagThreads'])->name('threads.tag.more');

// カテゴリ詳細ページ
Route::get('/category/{category}', [ThreadController::class, 'category'])->name('threads.category');
Route::get('/category/{category}/more', [ThreadController::class, 'getMoreCategoryThreads'])->name('threads.category.more');

// スレッド作成ページは削除（モーダルで表示するため）
// 直接メインページにリダイレクト
Route::get('/threads/create', function() {
    return redirect()->route('threads.index');
})->name('threads.create');

// /threads というURLでPOSTリクエストがあった場合、ThreadControllerのstoreメソッドを呼び出す
Route::post('/threads', [ThreadController::class, 'store'])->middleware('throttle:post')->name('threads.store');
// GETリクエストの場合はトップページにリダイレクト
Route::get('/threads', function() {
    return redirect()->route('threads.index');
});

// スレッドの個別表示
Route::get('/threads/{thread}', [ThreadController::class, 'show'])->name('threads.show');

// スレッドのレスポンスを取得するAPIエンドポイント
Route::get('/threads/{thread}/responses', [ThreadController::class, 'getResponses'])->name('threads.responses');
Route::get('/threads/{thread}/responses/new', [ThreadController::class, 'getNewResponses'])->name('threads.responses.new');
Route::get('/threads/{thread}/responses/search', [ThreadController::class, 'searchResponses'])->name('threads.responses.search');

// お気に入り（認証が必要）
Route::post('/threads/{thread}/favorite', [ThreadController::class, 'toggleFavorite'])->middleware('auth')->name('threads.favorite.toggle');

// 続きスレッド要望（認証が必要）
Route::post('/threads/{thread}/continuation-request', [ThreadContinuationController::class, 'toggleRequest'])->middleware('auth')->name('threads.continuation-request');

// レスポンス投稿（認証が必要）
Route::post('/threads/{thread}/responses', [ResponseController::class, 'store'])->middleware('throttle:post')->name('responses.store');

// レスポンス返信（認証が必要）
Route::post('/threads/{thread}/responses/{response}/reply', [ResponseController::class, 'reply'])->middleware('throttle:post')->name('responses.reply');

// スレッド編集機能は削除（ユーザーはスレッドを編集できない）
// 直接アクセスされた場合はスレッド詳細ページにリダイレクト
Route::get('/threads/{thread}/edit', function($thread) {
    return redirect()->route('threads.show', $thread);
});
Route::put('/threads/{thread}', function($thread) {
    return redirect()->route('threads.show', $thread);
});

// スレッド削除
Route::delete('/threads/{thread}', [ThreadController::class, 'destroy'])->name('threads.destroy');

// 認証関連のルート
Route::get('/auth', [AuthController::class, 'showAuthChoice'])->name('auth.choice');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
// GETリクエストの場合はログインページにリダイレクト
Route::get('/logout', function() {
    return redirect()->route('login');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 新規登録フロー
Route::get('/auth/terms', [AuthController::class, 'showTermsForm'])->name('auth.terms');
Route::post('/auth/terms', [AuthController::class, 'acceptTerms'])->name('register.terms');
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/register/sms-verification', [AuthController::class, 'showSmsVerification'])->name('register.sms-verification');
Route::post('/register/sms-verification', [AuthController::class, 'verifySms'])->name('register.sms-verify');
// GETリクエストの場合は登録ページにリダイレクト
Route::get('/register/sms-resend', function() {
    return redirect()->route('register');
});
Route::post('/register/sms-resend', [AuthController::class, 'resendSms'])->middleware('throttle:verification')->name('register.sms-resend');
Route::get('/register/email-verification', [AuthController::class, 'showEmailVerification'])->name('register.email-verification');
Route::post('/register/email-verification', [AuthController::class, 'verifyEmail'])->name('register.email-verify');
// GETリクエストの場合は登録ページにリダイレクト
Route::get('/register/email-resend', function() {
    return redirect()->route('register');
});
Route::post('/register/email-resend', [AuthController::class, 'resendEmail'])->middleware('throttle:verification')->name('register.email-resend');

// マイページ関連のルート（認証が必要）
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');
    
    // 既存ユーザー向け認証ルート
    Route::get('/profile/sms-verification', [AuthController::class, 'showProfileSmsVerification'])->name('profile.sms-verification');
    Route::post('/profile/sms-verification', [AuthController::class, 'verifyProfileSms'])->name('profile.sms-verify');
    // GETリクエストの場合はSMS認証ページにリダイレクト
    Route::get('/profile/sms-resend', function() {
        return redirect()->route('profile.sms-verification');
    });
    Route::post('/profile/sms-resend', [AuthController::class, 'resendProfileSms'])->middleware('throttle:verification')->name('profile.sms-resend');
    Route::get('/profile/email-verification', [AuthController::class, 'showProfileEmailVerification'])->name('profile.email-verification');
    Route::post('/profile/email-verification', [AuthController::class, 'verifyProfileEmail'])->name('profile.email-verify');
    // GETリクエストの場合はメール認証ページにリダイレクト
    Route::get('/profile/email-resend', function() {
        return redirect()->route('profile.email-verification');
    });
    Route::post('/profile/email-resend', [AuthController::class, 'resendProfileEmail'])->middleware('throttle:verification')->name('profile.email-resend');
});

// ユーザープロフィール表示（認証不要）
Route::get('/user/{user}', [ProfileController::class, 'show'])->name('profile.show');

// 居住地変更履歴取得（認証不要）
Route::get('/user/{user}/residence-history', [ProfileController::class, 'getResidenceHistory'])->name('profile.residence-history');

// ユーザーが作成したスレッドをさらに取得（AJAX用）
Route::middleware('auth')->group(function () {
    Route::get('/profile/threads/more', [ProfileController::class, 'getMoreThreads'])->name('profile.threads.more');
});
Route::get('/user/{user}/threads/more', [ProfileController::class, 'getMoreThreads'])->name('profile.user.threads.more');

// 通報機能（認証が必要）
Route::middleware('auth')->group(function () {
    Route::get('/reports/existing', [ReportController::class, 'getExisting'])->name('reports.existing');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    // GETリクエストの場合はトップページにリダイレクト
    Route::get('/reports', function() {
        return redirect()->route('threads.index');
    });
    
    // レスポンス制限了承機能（認証が必要）
    Route::post('/threads/{thread}/responses/{response}/acknowledge', [AcknowledgmentController::class, 'acknowledgeResponse'])->name('responses.acknowledge');
    // GETリクエストの場合はスレッド詳細ページにリダイレクト
    Route::get('/threads/{thread}/responses/{response}/acknowledge', function($thread) {
        return redirect()->route('threads.show', $thread);
    });
});

// スレッド制限了承機能（非ログイン時でも可能）
Route::post('/threads/{thread}/acknowledge', [AcknowledgmentController::class, 'acknowledgeThread'])->name('threads.acknowledge');
// GETリクエストの場合はスレッド詳細ページにリダイレクト
Route::get('/threads/{thread}/acknowledge', function($thread) {
    return redirect()->route('threads.show', $thread);
});

// お知らせ（通知）
Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{message}/read', [NotificationsController::class, 'markAsRead'])->name('notifications.mark-as-read');
// GETリクエストの場合はお知らせページにリダイレクト
Route::get('/notifications/{message}/read', function() {
    return redirect()->route('notifications.index');
});
Route::post('/notifications/{message}/reply', [NotificationsController::class, 'reply'])->name('notifications.reply')->middleware('auth');
// GETリクエストの場合はお知らせページにリダイレクト
Route::get('/notifications/{message}/reply', function() {
    return redirect()->route('notifications.index');
})->middleware('auth');
Route::post('/notifications/{message}/receive-coin', [NotificationsController::class, 'receiveCoin'])->name('notifications.receive-coin')->middleware('auth');
// GETリクエストの場合はお知らせページにリダイレクト
Route::get('/notifications/{message}/receive-coin', function() {
    return redirect()->route('notifications.index');
})->middleware('auth');
Route::post('/notifications/{message}/r18-approve', [NotificationsController::class, 'approveR18Change'])->name('notifications.r18-approve')->middleware('auth');
// GETリクエストの場合はお知らせページにリダイレクト
Route::get('/notifications/{message}/r18-approve', function() {
    return redirect()->route('notifications.index');
})->middleware('auth');
Route::post('/notifications/{message}/r18-reject', [NotificationsController::class, 'rejectR18Change'])->name('notifications.r18-reject')->middleware('auth');
// GETリクエストの場合はお知らせページにリダイレクト
Route::get('/notifications/{message}/r18-reject', function() {
    return redirect()->route('notifications.index');
})->middleware('auth');

// コイン機能（認証が必要）
Route::middleware('auth')->group(function () {
    Route::post('/coins/watch-ad', [CoinController::class, 'watchAd'])->name('coins.watch-ad');
    // GETリクエストの場合はマイページにリダイレクト
    Route::get('/coins/watch-ad', function() {
        return redirect()->route('profile.index');
    });
    Route::post('/coins/claim-login-reward', [CoinController::class, 'claimLoginReward'])->name('coins.claim-login-reward');
    // GETリクエストの場合はマイページにリダイレクト
    Route::get('/coins/claim-login-reward', function() {
        return redirect()->route('profile.index');
    });
    Route::get('/coins/balance', [CoinController::class, 'getBalance'])->name('coins.balance');
});

// フレンド機能（認証が必要）
Route::middleware('auth')->group(function () {
    Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('/friends/request', [FriendController::class, 'sendRequest'])->name('friends.send-request');
    // GETリクエストの場合はフレンドページにリダイレクト
    Route::get('/friends/request', function() {
        return redirect()->route('friends.index');
    });
    Route::post('/friends/{friendRequest}/accept', [FriendController::class, 'acceptRequest'])->name('friends.accept-request');
    // GETリクエストの場合はフレンドページにリダイレクト
    Route::get('/friends/{friendRequest}/accept', function() {
        return redirect()->route('friends.index');
    });
    Route::post('/friends/{friendRequest}/reject', [FriendController::class, 'rejectRequest'])->name('friends.reject-request');
    // GETリクエストの場合はフレンドページにリダイレクト
    Route::get('/friends/{friendRequest}/reject', function() {
        return redirect()->route('friends.index');
    });
    Route::post('/friends/reject-available', [FriendController::class, 'rejectAvailable'])->name('friends.reject-available');
    // GETリクエストの場合はフレンドページにリダイレクト
    Route::get('/friends/reject-available', function() {
        return redirect()->route('friends.index');
    });
    Route::post('/friends/delete', [FriendController::class, 'deleteFriend'])->name('friends.delete');
    // GETリクエストの場合はフレンドページにリダイレクト
    Route::get('/friends/delete', function() {
        return redirect()->route('friends.index');
    });
    Route::post('/friends/send-coins', [FriendController::class, 'sendCoins'])->name('friends.send-coins');
    // GETリクエストの場合はフレンドページにリダイレクト
    Route::get('/friends/send-coins', function() {
        return redirect()->route('friends.index');
    });
});
