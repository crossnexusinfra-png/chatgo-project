<?php

namespace Tests\Feature;

use App\Models\Thread;
use App\Models\ThreadAccess;
use App\Models\ThreadFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HomeIndexHidesR18Test extends TestCase
{
    use DatabaseTransactions;

    public function test_home_lists_hide_r18_rooms_for_guests_and_adults(): void
    {
        $adult = User::factory()->create([
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $token = uniqid('home', false);
        $sfwTitle = 'SFWLIST'.$token;
        $flagTitle = 'R18FLAG'.$token;
        $tagTitle = 'R18TAG'.$token;

        $sfw = $this->makeThread($adult, $sfwTitle, '雑談', false);
        $flagged = $this->makeThread($adult, $flagTitle, '雑談', true);
        $tagged = $this->makeThread($adult, $tagTitle, Thread::R18_TAGS[0], false);

        ThreadFavorite::create([
            'user_id' => $adult->user_id,
            'thread_id' => $flagged->thread_id,
        ]);
        ThreadAccess::create([
            'user_name' => $adult->username,
            'user_id' => $adult->user_id,
            'thread_id' => $tagged->thread_id,
            'accessed_at' => now(),
        ]);

        $this->get(route('threads.index'))
            ->assertOk()
            ->assertSee($sfwTitle, false)
            ->assertDontSee($flagTitle, false)
            ->assertDontSee($tagTitle, false);

        $this->actingAs($adult)
            ->get(route('threads.index'))
            ->assertOk()
            ->assertSee($sfwTitle, false)
            ->assertDontSee($flagTitle, false)
            ->assertDontSee($tagTitle, false);

        $this->assertNotNull($sfw->thread_id);
    }

    private function makeThread(User $user, string $title, string $tag, bool $isR18): Thread
    {
        return Thread::create([
            'title' => $title,
            'source_lang' => 'JA',
            'tag' => $tag,
            'user_id' => $user->user_id,
            'responses_count' => 0,
            'access_count' => 2000000000,
            'is_r18' => $isR18,
            'allows_user_replies' => true,
        ]);
    }
}
