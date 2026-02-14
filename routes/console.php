<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\File;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:create', function () {
    $username = $this->ask('管理者ユーザー名を入力してください');
    $email = $this->ask('メールアドレスを入力してください');
    $password = $this->secret('パスワードを入力してください');
    $passwordConfirm = $this->secret('パスワードを再入力してください');

    // バリデーション
    $validator = Validator::make([
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $passwordConfirm,
    ], [
        'username' => ['required', 'string', 'max:255', 'unique:admins,username'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }
        return 1;
    }

    // 管理者アカウントを作成
    try {
        $admin = Admin::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("管理者アカウントが正常に作成されました。");
        $this->info("ID: {$admin->admin_id}");
        $this->info("ユーザー名: {$admin->username}");
        $this->info("メールアドレス: {$admin->email}");
        
        return 0;
    } catch (\Exception $e) {
        $this->error("管理者アカウントの作成に失敗しました: " . $e->getMessage());
        return 1;
    }
})->purpose('管理者アカウントを作成します');

Artisan::command('admin:url', function () {
    $baseUrl = config('app.url');
    $prefix = trim((string) config('admin.prefix'), '/') ?: 'admin';
    $url = rtrim($baseUrl, '/') . '/' . $prefix;
    $this->info('管理者画面のURL: ' . $url);
    $this->line('（算出式: APP_URL + "/" + ADMIN_PREFIX）');
    $this->line('現在の APP_URL: ' . $baseUrl . '  |  ADMIN_PREFIX: ' . $prefix);
    $this->line('.env を変更した場合は php artisan config:clear を実行してください。');
})->purpose('管理者画面のURLを表示します（APP_URL + ADMIN_PREFIX）');

// ログファイルの自動削除（毎日実行）
Schedule::call(function () {
    $logsDir = storage_path('logs');
    $now = now();
    
    // エラーログの削除（30日以上古いファイル）
    $errorLogDays = env('LOG_ERROR_DAYS', 30);
    $errorLogPattern = $logsDir . '/error-*.log';
    $errorLogFiles = glob($errorLogPattern);
    
    foreach ($errorLogFiles as $file) {
        $fileTime = File::lastModified($file);
        $fileDate = \Carbon\Carbon::createFromTimestamp($fileTime);
        
        if ($fileDate->lt($now->copy()->subDays($errorLogDays))) {
            File::delete($file);
        }
    }
    
    // 警告ログの削除（14日以上古いファイル）
    $warningLogDays = env('LOG_WARNING_DAYS', 14);
    $warningLogPattern = $logsDir . '/warning-*.log';
    $warningLogFiles = glob($warningLogPattern);
    
    foreach ($warningLogFiles as $file) {
        $fileTime = File::lastModified($file);
        $fileDate = \Carbon\Carbon::createFromTimestamp($fileTime);
        
        if ($fileDate->lt($now->copy()->subDays($warningLogDays))) {
            File::delete($file);
        }
    }
    
    // Cloudflareアクセスログの削除（30日以上古いファイル）
    $cloudflareLogDays = env('CLOUDFLARE_LOG_RETENTION_DAYS', 30);
    $cloudflareLogPath = $logsDir . '/cloudflare-access.log';
    
    if (File::exists($cloudflareLogPath)) {
        $fileTime = File::lastModified($cloudflareLogPath);
        $fileDate = \Carbon\Carbon::createFromTimestamp($fileTime);
        
        if ($fileDate->lt($now->copy()->subDays($cloudflareLogDays))) {
            File::delete($cloudflareLogPath);
        }
    }
})->daily();
