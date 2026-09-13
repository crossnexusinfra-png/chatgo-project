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
use App\Http\Controllers\FriendController;
use App\Http\Controllers\FreezeAppealController;
use App\Http\Controllers\ArticleController;

/*
|--------------------------------------------------------------------------
| 人間が直接見る UI（/{locale}/... 配下で読み込まれる）
|--------------------------------------------------------------------------
| 言語ごとのルート複製はしない。同じ Controller / Blade を共有する。
*/

Route::get('/', [ThreadController::class, 'index'])->name('threads.index');

Route::post('/suggestions', [SuggestionController::class, 'store'])->middleware(['throttle:suggestions', 'request.user'])->name('suggestions.store');
Route::post('/freeze-appeals', [FreezeAppealController::class, 'store'])
    ->middleware(['auth', 'throttle:freeze_appeals', 'request.user'])
    ->name('freeze-appeals.store');
Route::get('/suggestions', function () {
    return redirect()->route('threads.index');
});

Route::get('/search', [ThreadController::class, 'search'])->middleware('throttle:search')->name('threads.search');
Route::get('/tag/{tag}', [ThreadController::class, 'searchByTag'])->name('threads.tag');
Route::get('/category/{category}', [ThreadController::class, 'category'])->name('threads.category');

Route::get('/threads/create', function () {
    return redirect()->route('threads.index');
})->name('threads.create');

Route::post('/threads', [ThreadController::class, 'store'])->middleware(['throttle:post', 'request.user'])->name('threads.store');
Route::get('/threads', function () {
    return redirect()->route('threads.index');
});

Route::get('/threads/{thread}', [ThreadController::class, 'show'])->name('threads.show');
Route::post('/threads/{thread}/favorite', [ThreadController::class, 'toggleFavorite'])->middleware('auth')->name('threads.favorite.toggle');
Route::post('/threads/{thread}/responses', [ResponseController::class, 'store'])->middleware(['throttle:post', 'request.user'])->name('responses.store');
Route::post('/threads/{thread}/responses/{response}/reply', [ResponseController::class, 'reply'])->middleware(['throttle:post', 'request.user'])->name('responses.reply');

Route::get('/threads/{thread}/edit', function (string $locale, $thread) {
    return redirect()->route('threads.show', $thread);
});
Route::put('/threads/{thread}', function (string $locale, $thread) {
    return redirect()->route('threads.show', $thread);
});
Route::delete('/threads/{thread}', [ThreadController::class, 'destroy'])->name('threads.destroy');

Route::get('/auth', [AuthController::class, 'showAuthChoice'])->name('auth.choice');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::get('/login/password-reset', [AuthController::class, 'showPasswordResetForm'])->name('login.password-reset');
Route::post('/login/password-reset', [AuthController::class, 'requestPasswordResetEmail'])->middleware('throttle:password_reset_email')->name('login.password-reset.request');
Route::get('/login/password-reset/phone', [AuthController::class, 'showPasswordResetPhoneForm'])->name('login.password-reset.phone');
Route::post('/login/password-reset/phone', [AuthController::class, 'requestPasswordResetPhone'])->middleware('throttle:password_reset_phone')->name('login.password-reset.phone.submit');
Route::get('/login/password-reset/sent', [AuthController::class, 'showPasswordResetSent'])->name('login.password-reset.sent');
Route::get('/login/password-reset/complete/{token}', [AuthController::class, 'showPasswordResetComplete'])->name('login.password-reset.complete');
Route::post('/login/password-reset/complete', [AuthController::class, 'submitPasswordResetFromToken'])->name('login.password-reset.complete.submit');
Route::get('/logout', function () {
    return redirect()->route('login');
});

Route::get('/auth/terms', [AuthController::class, 'showTermsForm'])->name('auth.terms');
Route::post('/auth/terms', [AuthController::class, 'acceptTerms'])->name('register.terms');
Route::view('/privacy', 'legal.privacy')->name('legal.privacy');
Route::view('/terms', 'legal.terms')->name('legal.terms');
Route::view('/contact', 'legal.contact')->name('legal.contact');
Route::view('/company', 'legal.company')->name('legal.company');
Route::view('/guide', 'legal.guide')->name('legal.guide');
Route::view('/faq', 'legal.faq')->name('legal.faq');
Route::get('/articles', [ArticleController::class, 'index'])->name('legal.articles');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('legal.articles.show');
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:veriphone');
Route::get('/register/sms-verification', [AuthController::class, 'showSmsVerification'])->name('register.sms-verification');
Route::post('/register/sms-verification', [AuthController::class, 'verifySms'])->name('register.sms-verify');
Route::get('/register/sms-resend', function () {
    return redirect()->route('register');
});
Route::post('/register/sms-resend', [AuthController::class, 'resendSms'])->middleware('throttle:verification_initial_sms')->middleware('throttle:veriphone')->name('register.sms-resend');
Route::get('/register/email-verification', [AuthController::class, 'showEmailVerification'])->name('register.email-verification');
Route::post('/register/email-verification', [AuthController::class, 'verifyEmail'])->name('register.email-verify');
Route::get('/register/email-resend', function () {
    return redirect()->route('register');
});
Route::post('/register/email-resend', [AuthController::class, 'resendEmail'])->middleware('throttle:verification_initial_email')->name('register.email-resend');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->middleware('request.user')->name('profile.update');
    Route::post('/profile/cancel-pending-contact', [ProfileController::class, 'cancelPendingContactVerification'])->name('profile.cancel-pending-contact');
    Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');

    Route::get('/profile/sms-verification', [AuthController::class, 'showProfileSmsVerification'])->name('profile.sms-verification');
    Route::post('/profile/sms-verification', [AuthController::class, 'verifyProfileSms'])->name('profile.sms-verify');
    Route::get('/profile/sms-resend', function () {
        return redirect()->route('profile.sms-verification');
    });
    Route::post('/profile/sms-resend', [AuthController::class, 'resendProfileSms'])->middleware('throttle:verification_profile')->middleware('throttle:veriphone')->name('profile.sms-resend');
    Route::get('/profile/email-verification', [AuthController::class, 'showProfileEmailVerification'])->name('profile.email-verification');
    Route::post('/profile/email-verification', [AuthController::class, 'verifyProfileEmail'])->name('profile.email-verify');
    Route::get('/profile/email-resend', function () {
        return redirect()->route('profile.email-verification');
    });
    Route::post('/profile/email-resend', [AuthController::class, 'resendProfileEmail'])->middleware('throttle:verification_profile')->name('profile.email-resend');
});

Route::get('/user/{user}', [ProfileController::class, 'show'])->name('profile.show');

Route::middleware('auth')->group(function () {
    Route::post('/reports', [ReportController::class, 'store'])->middleware(['throttle:reports', 'request.user'])->name('reports.store');
    Route::get('/reports', function () {
        return redirect()->route('threads.index');
    });
});

Route::post('/threads/{thread}/acknowledge', [AcknowledgmentController::class, 'acknowledgeThread'])->middleware('throttle:notice_reply')->name('threads.acknowledge');
Route::post('/threads/{thread}/responses/{response}/acknowledge', [AcknowledgmentController::class, 'acknowledgeResponse'])->middleware('throttle:notice_reply')->name('responses.acknowledge');
Route::get('/threads/{thread}/acknowledge', function (string $locale, $thread) {
    return redirect()->route('threads.show', $thread);
});
Route::get('/threads/{thread}/responses/{response}/acknowledge', function (string $locale, $thread) {
    return redirect()->route('threads.show', $thread);
});

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
});
Route::get('/notifications/{message}/read', function () {
    return auth()->check() ? redirect()->route('notifications.index') : redirect()->route('login');
})->where('message', '[0-9]+');
Route::get('/notifications/{message}/reply', function () {
    return auth()->check() ? redirect()->route('notifications.index') : redirect()->route('login');
})->where('message', '[0-9]+');
Route::get('/notifications/{message}/receive-coin', function () {
    return auth()->check() ? redirect()->route('notifications.index') : redirect()->route('login');
})->where('message', '[0-9]+');
Route::get('/notifications/{message}/r18-approve', function () {
    return auth()->check() ? redirect()->route('notifications.index') : redirect()->route('login');
})->where('message', '[0-9]+');
Route::get('/notifications/{message}/r18-reject', function () {
    return auth()->check() ? redirect()->route('notifications.index') : redirect()->route('login');
})->where('message', '[0-9]+');

Route::middleware('auth')->group(function () {
    Route::get('/coins/watch-ad', function () {
        return redirect()->route('profile.index');
    });
    Route::get('/coins/claim-login-reward', function () {
        return redirect()->route('profile.index');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('/friends/request', [FriendController::class, 'sendRequest'])->name('friends.send-request');
    Route::get('/friends/request', function () {
        return redirect()->route('friends.index');
    });
    Route::post('/friends/{friendRequest}/accept', [FriendController::class, 'acceptRequest'])->name('friends.accept-request');
    Route::get('/friends/{friendRequest}/accept', function () {
        return redirect()->route('friends.index');
    });
    Route::post('/friends/{friendRequest}/reject', [FriendController::class, 'rejectRequest'])->name('friends.reject-request');
    Route::get('/friends/{friendRequest}/reject', function () {
        return redirect()->route('friends.index');
    });
    Route::get('/friends/reject-available', function () {
        return redirect()->route('friends.index');
    });
    Route::get('/friends/delete', function () {
        return redirect()->route('friends.index');
    });
    Route::get('/friends/send-coins', function () {
        return redirect()->route('friends.index');
    });
});
