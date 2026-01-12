<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Post;

class UpdatePostsCommentsCountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 全ての投稿のコメント数を更新
        $posts = Post::all();
        
        foreach ($posts as $post) {
            $post->updateCommentsCount();
        }
        
        $this->command->info('投稿のコメント数が更新されました。');
    }
}
