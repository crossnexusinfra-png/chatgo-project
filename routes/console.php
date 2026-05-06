<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\ObservabilityLogService;

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

Artisan::command('user:admin-create', function () {
    $username = $this->ask('利用者ページ用 管理者ユーザー名を入力してください');
    $userIdentifier = $this->ask('ユーザーID（@以降）を入力してください（15文字以内）');
    $email = $this->ask('メールアドレスを入力してください');
    $password = $this->secret('パスワードを入力してください');
    $passwordConfirm = $this->secret('パスワードを再入力してください');

    $validator = Validator::make([
        'username' => $username,
        'user_identifier' => $userIdentifier,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $passwordConfirm,
    ], [
        'username' => ['required', 'string', 'max:255'],
        'user_identifier' => ['required', 'string', 'max:15', 'regex:/^[a-z0-9_]+$/', 'unique:users,user_identifier'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }
        return 1;
    }

    try {
        $user = User::create([
            'username' => $username,
            'user_identifier' => strtolower($userIdentifier),
            'email' => $email,
            'password' => Hash::make($password),
            'nationality' => 'JP',
            'residence' => 'JP',
            'language' => 'JA',
            'is_admin' => true,
            'email_verified_at' => now(),
            'is_verified' => true,
        ]);

        $this->info('利用者ページ用の管理者ユーザーを作成しました。');
        $this->line('user_id: ' . $user->user_id);
        $this->line('表示名: ' . $user->username . '@' . $user->user_identifier);
        $this->line('管理者マーク: ON');

        return 0;
    } catch (\Throwable $e) {
        $this->error('管理者ユーザーの作成に失敗しました: ' . $e->getMessage());
        return 1;
    }
})->purpose('利用者ページ用の管理者ユーザー（is_admin=true）を作成');

Artisan::command('admin:bootstrap-from-env {--rotate-passwords : 既存レコードのパスワードも更新する}', function () {
    $enabled = (bool) data_get(config('admin.bootstrap'), 'enabled', false);
    if (!$enabled) {
        $this->warn('ADMIN_BOOTSTRAP_ENABLED=true ではないため、処理を中断しました。');
        return 1;
    }

    $panel = (array) data_get(config('admin.bootstrap'), 'panel', []);
    $userAdmin = (array) data_get(config('admin.bootstrap'), 'user', []);
    $rotate = (bool) $this->option('rotate-passwords');

    $validator = Validator::make([
        'panel_username' => (string) ($panel['username'] ?? ''),
        'panel_email' => (string) ($panel['email'] ?? ''),
        'panel_password' => (string) ($panel['password'] ?? ''),
        'user_username' => (string) ($userAdmin['username'] ?? ''),
        'user_identifier' => (string) ($userAdmin['identifier'] ?? ''),
        'user_email' => (string) ($userAdmin['email'] ?? ''),
        'user_password' => (string) ($userAdmin['password'] ?? ''),
    ], [
        'panel_username' => ['required', 'string', 'max:255'],
        'panel_email' => ['required', 'string', 'email', 'max:255'],
        'panel_password' => ['required', 'string', 'min:8'],
        'user_username' => ['required', 'string', 'max:255'],
        'user_identifier' => ['required', 'string', 'max:15', 'regex:/^[a-z0-9_]+$/'],
        'user_email' => ['required', 'string', 'email', 'max:255'],
        'user_password' => ['required', 'string', 'min:8'],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }
        return 1;
    }

    try {
        DB::transaction(function () use ($panel, $userAdmin, $rotate) {
            $panelAdmin = Admin::where('email', (string) $panel['email'])->first();
            if (!$panelAdmin) {
                Admin::create([
                    'username' => (string) $panel['username'],
                    'email' => (string) $panel['email'],
                    'password' => Hash::make((string) $panel['password']),
                ]);
            } elseif ($rotate) {
                $panelAdmin->password = Hash::make((string) $panel['password']);
                $panelAdmin->save();
            }

            $user = User::where('email', (string) $userAdmin['email'])->first();
            if (!$user) {
                User::create([
                    'username' => (string) $userAdmin['username'],
                    'user_identifier' => strtolower((string) $userAdmin['identifier']),
                    'email' => (string) $userAdmin['email'],
                    'password' => Hash::make((string) $userAdmin['password']),
                    'nationality' => 'JP',
                    'residence' => 'JP',
                    'language' => 'JA',
                    'is_verified' => true,
                    'is_admin' => true,
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->is_admin = true;
                $user->is_verified = true;
                if ($user->email_verified_at === null) {
                    $user->email_verified_at = now();
                }
                if ($rotate) {
                    $user->password = Hash::make((string) $userAdmin['password']);
                }
                $user->save();
            }
        });
    } catch (\Throwable $e) {
        $this->error('初期投入に失敗しました: ' . $e->getMessage());
        return 1;
    }

    $this->info('管理者アカウント初期投入が完了しました。');
    $this->line('- admins: ' . (string) $panel['email']);
    $this->line('- users(is_admin=true): ' . (string) $userAdmin['email']);
    $this->line('次の手順: ADMIN_BOOTSTRAP_ENABLED=false に戻し、php artisan config:clear を実行してください。');

    return 0;
})->purpose('.env の初期値から admins/users の管理者アカウントをDBへ投入');

Artisan::command('admin:url', function () {
    $baseUrl = config('app.url');
    $prefix = trim((string) config('admin.prefix'), '/') ?: 'admin';
    $url = rtrim($baseUrl, '/') . '/' . $prefix;
    $this->info('管理者画面のURL: ' . $url);
    $this->line('（算出式: APP_URL + "/" + ADMIN_PREFIX）');
    $this->line('現在の APP_URL: ' . $baseUrl . '  |  ADMIN_PREFIX: ' . $prefix);
    $this->line('.env を変更した場合は php artisan config:clear を実行してください。');
})->purpose('管理者画面のURLを表示します（APP_URL + ADMIN_PREFIX）');

Artisan::command('db:wal:snapshot {reason=scheduled}', function (string $reason) {
    $driver = (string) config('database.default');
    $dbName = (string) config("database.connections.{$driver}.database");
    $eventId = (string) Str::uuid();

    $walLsn = null;
    $txid = null;
    if ($driver === 'pgsql') {
        $row = DB::selectOne('SELECT pg_current_wal_lsn() AS wal_lsn, txid_current() AS txid');
        $walLsn = $row?->wal_lsn;
        $txid = isset($row?->txid) ? (string) $row->txid : null;
    }

    ObservabilityLogService::recordWalSnapshot([
        'event_id' => $eventId,
        'request_id' => null,
        'database_driver' => $driver,
        'database_name' => $dbName,
        'wal_lsn' => $walLsn,
        'transaction_id' => $txid,
        'snapshot_reason' => $reason,
        'metadata' => [
            'captured_at' => now()->toDateTimeString(),
            'app_env' => config('app.env'),
        ],
    ]);

    $this->info("WAL snapshot saved. event_id={$eventId}");
})->purpose('DB復元用のWALスナップショットログを保存');

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

Schedule::command('db:wal:snapshot scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Artisan::command('db:backup:s3 {--label=}', function () {
    $driver = (string) config('database.default');
    $conn = (array) config("database.connections.{$driver}");
    $timestamp = now()->format('Ymd_His');
    $label = trim((string) $this->option('label'));
    $suffix = $label !== '' ? ('_' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $label)) : '';

    $tmpDir = storage_path('app/private/backups/tmp');
    if (! File::exists($tmpDir)) {
        File::makeDirectory($tmpDir, 0755, true);
    }

    $baseName = "db_{$driver}_{$timestamp}{$suffix}";
    $localPath = match ($driver) {
        'pgsql' => "{$tmpDir}/{$baseName}.dump",
        'mysql', 'mariadb' => "{$tmpDir}/{$baseName}.sql",
        default => "{$tmpDir}/{$baseName}.sqlite",
    };

    if ($driver === 'sqlite') {
        $dbPath = (string) ($conn['database'] ?? '');
        if ($dbPath === '' || ! File::exists($dbPath)) {
            $this->error('SQLite DBファイルが見つかりません。');
            return 1;
        }
        File::copy($dbPath, $localPath);
    } elseif ($driver === 'pgsql') {
        $host = (string) ($conn['host'] ?? '127.0.0.1');
        $port = (string) ($conn['port'] ?? '5432');
        $database = (string) ($conn['database'] ?? '');
        $username = (string) ($conn['username'] ?? '');
        $password = (string) ($conn['password'] ?? '');

        $result = Process::env(['PGPASSWORD' => $password])->run([
            'pg_dump',
            '--host=' . $host,
            '--port=' . $port,
            '--username=' . $username,
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--file=' . $localPath,
            $database,
        ]);

        if (! $result->successful()) {
            $this->error("pg_dump 失敗: {$result->errorOutput()}");
            return 1;
        }
    } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
        $host = (string) ($conn['host'] ?? '127.0.0.1');
        $port = (string) ($conn['port'] ?? '3306');
        $database = (string) ($conn['database'] ?? '');
        $username = (string) ($conn['username'] ?? '');
        $password = (string) ($conn['password'] ?? '');

        $result = Process::run([
            'mysqldump',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--password=' . $password,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--result-file=' . $localPath,
            $database,
        ]);

        if (! $result->successful()) {
            $this->error("mysqldump 失敗: {$result->errorOutput()}");
            return 1;
        }
    } else {
        $this->error("未対応のDBドライバです: {$driver}");
        return 1;
    }

    $prefix = trim((string) env('DB_BACKUP_S3_PREFIX', 'db-backups'), '/');
    $remotePath = "{$prefix}/{$driver}/" . basename($localPath);

    $stream = fopen($localPath, 'r');
    if ($stream === false) {
        $this->error('バックアップファイルを開けませんでした。');
        return 1;
    }

    $uploaded = Storage::disk('s3')->put($remotePath, $stream);
    fclose($stream);

    if (! $uploaded) {
        $this->error('S3へのアップロードに失敗しました。');
        return 1;
    }

    if (! (bool) env('DB_BACKUP_KEEP_LOCAL', false)) {
        File::delete($localPath);
    }

    $this->info("S3バックアップ完了: {$remotePath}");
    return 0;
})->purpose('DBダンプをS3へ保存');

Artisan::command('db:backup:pull {s3_key}', function () {
    $s3Key = (string) $this->argument('s3_key');
    $disk = Storage::disk('s3');

    if (! $disk->exists($s3Key)) {
        $this->error("S3にファイルが存在しません: {$s3Key}");
        return 1;
    }

    $restoreDir = storage_path('app/private/backups/restore');
    if (! File::exists($restoreDir)) {
        File::makeDirectory($restoreDir, 0755, true);
    }

    $localPath = "{$restoreDir}/" . basename($s3Key);
    $readStream = $disk->readStream($s3Key);
    if ($readStream === false) {
        $this->error("S3からファイルを読み取れませんでした: {$s3Key}");
        return 1;
    }

    $writeStream = fopen($localPath, 'w');
    if ($writeStream === false) {
        if (is_resource($readStream)) {
            fclose($readStream);
        }
        $this->error("ローカルファイルを作成できませんでした: {$localPath}");
        return 1;
    }

    stream_copy_to_stream($readStream, $writeStream);
    fclose($readStream);
    fclose($writeStream);

    $this->info("復元用ダンプを取得しました: {$localPath}");
    $this->line('必要に応じて以下を実行してください:');
    $this->line('- PostgreSQL: pg_restore --clean --if-exists --no-owner --no-privileges --dbname=<DB名> <ダンプファイル>');
    $this->line('- MySQL: mysql -h <host> -P <port> -u <user> -p <DB名> < <ダンプファイル>');
    $this->line('- SQLite: 既存DBを退避後、ダンプファイルで置換');
    return 0;
})->purpose('S3上のDBバックアップをローカルへ取得');

Schedule::command('db:backup:s3')
    ->dailyAt(env('DB_BACKUP_SCHEDULE_AT', '03:30'))
    ->withoutOverlapping();
