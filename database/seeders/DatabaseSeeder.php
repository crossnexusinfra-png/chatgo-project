<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 初期動作確認用ユーザー（本番では必要に応じて削除/変更）
        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'username' => 'testuser',
                'user_identifier' => 'test_user',
                'nationality' => 'JP',
                'residence' => 'JP',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
