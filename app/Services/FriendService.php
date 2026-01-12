<?php

namespace App\Services;

use App\Models\User;
use App\Models\Friendship;
use App\Models\FriendRequest;
use App\Models\CoinSend;
use App\Models\ThreadInteraction;
use App\Models\Response;
use Illuminate\Support\Facades\DB;

class FriendService
{
    /**
     * フレンド機能が有効かチェック
     */
    public function isFriendFeatureEnabled(User $user): bool
    {
        // 総ログイン数5日以上
        $loginCount = \App\Models\AccessLog::where('user_id', $user->user_id)
            ->where('type', 'login')
            ->distinct('created_at')
            ->count();
        
        if ($loginCount < 5) {
            return false;
        }
        
        // スレッド作成1個以上
        $threadCount = \App\Models\Thread::where('user_id', $user->user_id)->count();
        if ($threadCount < 1) {
            return false;
        }
        
        // レスポンス送信15回以上
        $responseCount = Response::where('user_id', $user->user_id)->count();
        if ($responseCount < 15) {
            return false;
        }
        
        return true;
    }

    /**
     * フレンド機能の条件状態を取得
     */
    public function getFriendFeatureConditions(User $user): array
    {
        // 総ログイン数
        $loginCount = \App\Models\AccessLog::where('user_id', $user->user_id)
            ->where('type', 'login')
            ->distinct('created_at')
            ->count();
        
        // スレッド作成数
        $threadCount = \App\Models\Thread::where('user_id', $user->user_id)->count();
        
        // レスポンス送信数
        $responseCount = Response::where('user_id', $user->user_id)->count();
        
        return [
            'login_count' => $loginCount,
            'login_required' => 5,
            'login_met' => $loginCount >= 5,
            'thread_count' => $threadCount,
            'thread_required' => 1,
            'thread_met' => $threadCount >= 1,
            'response_count' => $responseCount,
            'response_required' => 15,
            'response_met' => $responseCount >= 15,
            'all_met' => $loginCount >= 5 && $threadCount >= 1 && $responseCount >= 15,
        ];
    }

    /**
     * フレンド申請可能かチェック
     */
    public function canSendFriendRequest(User $fromUser, User $toUser): array
    {
        // 自分自身には申請できない
        if ($fromUser->user_id === $toUser->user_id) {
            return [
                'can_send' => false,
                'reason' => '自分自身には申請できません',
            ];
        }
        
        // 既にフレンドかチェック
        $friendship = Friendship::where(function($query) use ($fromUser, $toUser) {
            $query->where('user_id', $fromUser->user_id)
                  ->where('friend_id', $toUser->user_id);
        })->orWhere(function($query) use ($fromUser, $toUser) {
            $query->where('user_id', $toUser->user_id)
                  ->where('friend_id', $fromUser->user_id);
        })->first();
        
        if ($friendship) {
            return [
                'can_send' => false,
                'reason' => '既にフレンドです',
            ];
        }
        
        // 既に申請中かチェック
        $existingRequest = FriendRequest::where(function($query) use ($fromUser, $toUser) {
            $query->where('from_user_id', $fromUser->user_id)
                  ->where('to_user_id', $toUser->user_id)
                  ->where('status', 'pending');
        })->orWhere(function($query) use ($fromUser, $toUser) {
            $query->where('from_user_id', $toUser->user_id)
                  ->where('to_user_id', $fromUser->user_id)
                  ->where('status', 'pending');
        })->first();
        
        if ($existingRequest) {
            return [
                'can_send' => false,
                'reason' => '既に申請中です',
            ];
        }
        
        // スレッド内でお互いに10通以上（双方それぞれ1000文字以上）送信し合ったかチェック
        $interaction = $this->checkThreadInteraction($fromUser, $toUser);
        
        if (!$interaction['can_request']) {
            return [
                'can_send' => false,
                'reason' => $interaction['reason'],
            ];
        }
        
        return [
            'can_send' => true,
        ];
    }

    /**
     * スレッド内での相互送信をチェック
     */
    private function checkThreadInteraction(User $user1, User $user2): array
    {
        // ThreadInteractionテーブルから直接チェック
        $interactions = ThreadInteraction::where(function($query) use ($user1, $user2) {
            $query->where('user_id', $user1->user_id)
                  ->where('other_user_id', $user2->user_id);
        })->orWhere(function($query) use ($user1, $user2) {
            $query->where('user_id', $user2->user_id)
                  ->where('other_user_id', $user1->user_id);
        })->get();
        
        foreach ($interactions as $interaction) {
            // お互いに10通以上送信したかチェック
            $user1Interaction = ThreadInteraction::where('thread_id', $interaction->thread_id)
                ->where('user_id', $user1->user_id)
                ->where('other_user_id', $user2->user_id)
                ->first();
            
            $user2Interaction = ThreadInteraction::where('thread_id', $interaction->thread_id)
                ->where('user_id', $user2->user_id)
                ->where('other_user_id', $user1->user_id)
                ->first();
            
            if ($user1Interaction && $user2Interaction) {
                if ($user1Interaction->message_count >= 10 && $user2Interaction->message_count >= 10) {
                    // 双方それぞれ1000文字以上かチェック
                    if ($user1Interaction->total_characters >= 1000 && $user2Interaction->total_characters >= 1000) {
                        // 12時間以内に相互に送信したかチェック
                        if ($user1Interaction->last_interaction_at && $user2Interaction->last_interaction_at) {
                            $timeDiff = abs($user1Interaction->last_interaction_at->diffInSeconds($user2Interaction->last_interaction_at));
                            if ($timeDiff <= 43200) { // 12時間 = 43200秒
                                return [
                                    'can_request' => true,
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        return [
            'can_request' => false,
            'reason' => 'スレッド内でお互いに10通以上（双方それぞれ1000文字以上）送信し合う必要があります',
        ];
    }

    /**
     * フレンド機能の有効条件を満たした時に自動的にフレンド申請可能にする
     * 注意: 招待によるフレンドも申請ボタンを押す方式に変更したため、このメソッドは使用されません
     */
    public function checkAndAutoCreateFriendRequests(User $user): void
    {
        // 招待によるフレンドも申請ボタンを押す方式に変更したため、自動的にフレンド申請を作成しない
        // このメソッドは互換性のために残していますが、実際には何も行いません
        return;
    }

    /**
     * フレンド申請を送信
     */
    public function sendFriendRequest(User $fromUser, User $toUser): bool
    {
        // 最大フレンド数チェック
        if ($this->isMaxFriendsReached($fromUser)) {
            return false;
        }
        
        $check = $this->canSendFriendRequest($fromUser, $toUser);
        if (!$check['can_send']) {
            return false;
        }
        
        FriendRequest::create([
            'from_user_id' => $fromUser->user_id,
            'to_user_id' => $toUser->user_id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        
        return true;
    }

    /**
     * フレンド申請を承認
     */
    public function acceptFriendRequest(FriendRequest $request): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }
        
        DB::transaction(function() use ($request) {
            // 逆方向の申請もチェック（双方が申請している場合）
            $reverseRequest = FriendRequest::where('from_user_id', $request->to_user_id)
                ->where('to_user_id', $request->from_user_id)
                ->where('status', 'pending')
                ->first();
            
            // フレンド関係を作成（双方向）
            Friendship::create([
                'user_id' => $request->from_user_id,
                'friend_id' => $request->to_user_id,
                'friendship_date' => now(),
            ]);
            
            Friendship::create([
                'user_id' => $request->to_user_id,
                'friend_id' => $request->from_user_id,
                'friendship_date' => now(),
            ]);
            
            // 申請を承認済みに更新
            $request->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
            
            // 逆方向の申請も承認済みに更新
            if ($reverseRequest) {
                $reverseRequest->update([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);
            }
        });
        
        return true;
    }

    /**
     * フレンド申請を拒否
     */
    public function rejectFriendRequest(FriendRequest $request): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }
        
        DB::transaction(function() use ($request) {
            // 逆方向の申請も削除
            $reverseRequest = FriendRequest::where('from_user_id', $request->to_user_id)
                ->where('to_user_id', $request->from_user_id)
                ->where('status', 'pending')
                ->first();
            
            if ($reverseRequest) {
                $reverseRequest->delete();
            }
            
            // 申請を拒否済みに更新
            $request->update([
                'status' => 'rejected',
                'responded_at' => now(),
            ]);
            
            // フレンド申請条件をリセット（ThreadInteractionと招待関係を削除）
            $fromUser = User::find($request->from_user_id);
            $toUser = User::find($request->to_user_id);
            
            if ($fromUser && $toUser) {
                $this->resetFriendRequestConditions($fromUser, $toUser);
            }
        });
        
        return true;
    }

    /**
     * フレンドにコインを送信
     */
    public function sendCoinsToFriend(User $fromUser, User $toUser): array
    {
        // フレンドかチェック
        $friendship = Friendship::where(function($query) use ($fromUser, $toUser) {
            $query->where('user_id', $fromUser->user_id)
                  ->where('friend_id', $toUser->user_id);
        })->orWhere(function($query) use ($fromUser, $toUser) {
            $query->where('user_id', $toUser->user_id)
                  ->where('friend_id', $fromUser->user_id);
        })->first();
        
        if (!$friendship) {
            return [
                'success' => false,
                'message' => 'フレンドではありません',
            ];
        }
        
        // 最後の送信から12時間経過しているかチェック
        $lastSend = CoinSend::where('from_user_id', $fromUser->user_id)
            ->where('to_user_id', $toUser->user_id)
            ->latest('sent_at')
            ->first();
        
        if ($lastSend && $lastSend->next_available_at && now() < $lastSend->next_available_at) {
            return [
                'success' => false,
                'message' => '12時間経過していません',
            ];
        }
        
        // コインを送信（新規配布）
        $coinService = new \App\Services\CoinService();
        $coinService->addCoins($toUser, 1);
        
        // 送信記録を保存
        CoinSend::create([
            'from_user_id' => $fromUser->user_id,
            'to_user_id' => $toUser->user_id,
            'coins' => 1,
            'sent_at' => now(),
            'next_available_at' => now()->addHours(12),
        ]);
        
        return [
            'success' => true,
            'message' => 'コインを送信しました',
        ];
    }

    /**
     * 招待コードを生成
     */
    public function generateInviteCode(User $user): string
    {
        // 既に招待コードがある場合はそれを返す
        if ($user->invite_code) {
            return $user->invite_code;
        }
        
        // 新しい招待コードを生成
        do {
            $code = strtoupper(substr(md5($user->user_id . time() . uniqid()), 0, 8));
        } while (\App\Models\User::where('invite_code', $code)->exists());
        
        // データベースに保存
        $user->invite_code = $code;
        $user->save();
        
        // モデルをリフレッシュして確実に取得
        $user->refresh();
        
        // 念のため、保存されたコードを返す（nullの場合は生成したコードを返す）
        return $user->invite_code ?? $code;
    }

    /**
     * フレンド申請可能なユーザー一覧を取得
     */
    public function getAvailableFriendRequests(User $user): array
    {
        $availableUsers = [];
        
        // すべてのユーザーを取得（自分以外）
        $allUsers = User::where('user_id', '!=', $user->user_id)->get();
        
        foreach ($allUsers as $otherUser) {
            // 招待関係があるかチェック
            $invite = \App\Models\UserInvite::where(function($query) use ($user, $otherUser) {
                $query->where('inviter_id', $user->user_id)
                      ->where('invitee_id', $otherUser->user_id);
            })->orWhere(function($query) use ($user, $otherUser) {
                $query->where('inviter_id', $otherUser->user_id)
                      ->where('invitee_id', $user->user_id);
            })->first();
            
            // 招待関係がある場合、またはフレンド申請可能な場合
            $canSend = false;
            if ($invite) {
                // 招待関係がある場合は、既にフレンドでない限り申請可能
                $friendship = Friendship::where(function($query) use ($user, $otherUser) {
                    $query->where('user_id', $user->user_id)
                          ->where('friend_id', $otherUser->user_id);
                })->orWhere(function($query) use ($user, $otherUser) {
                    $query->where('user_id', $otherUser->user_id)
                          ->where('friend_id', $user->user_id);
                })->first();
                
                if (!$friendship) {
                    $canSend = true;
                }
            } else {
                // 招待関係がない場合は、通常の条件チェック
                $check = $this->canSendFriendRequest($user, $otherUser);
                $canSend = $check['can_send'];
            }
            
            if ($canSend) {
                // 既に申請を送信しているかチェック
                $sentRequest = FriendRequest::where('from_user_id', $user->user_id)
                    ->where('to_user_id', $otherUser->user_id)
                    ->where('status', 'pending')
                    ->first();
                
                // 既に申請を受け取っているかチェック
                $receivedRequest = FriendRequest::where('from_user_id', $otherUser->user_id)
                    ->where('to_user_id', $user->user_id)
                    ->where('status', 'pending')
                    ->first();
                
                $availableUsers[] = [
                    'user' => $otherUser,
                    'sent_request' => $sentRequest,
                    'received_request' => $receivedRequest,
                    'is_invite' => $invite !== null,
                ];
            }
        }
        
        return $availableUsers;
    }

    /**
     * フレンド申請条件をリセット（拒否時に呼ばれる）
     */
    public function resetFriendRequestConditions(User $user1, User $user2): void
    {
        // ThreadInteractionを削除
        ThreadInteraction::where(function($query) use ($user1, $user2) {
            $query->where('user_id', $user1->user_id)
                  ->where('other_user_id', $user2->user_id);
        })->orWhere(function($query) use ($user1, $user2) {
            $query->where('user_id', $user2->user_id)
                  ->where('other_user_id', $user1->user_id);
        })->delete();
        
        // 招待関係を削除（双方向）
        \App\Models\UserInvite::where(function($query) use ($user1, $user2) {
            $query->where('inviter_id', $user1->user_id)
                  ->where('invitee_id', $user2->user_id);
        })->orWhere(function($query) use ($user1, $user2) {
            $query->where('inviter_id', $user2->user_id)
                  ->where('invitee_id', $user1->user_id);
        })->delete();
    }

    /**
     * フレンドを削除
     */
    public function deleteFriend(User $user, User $friend): bool
    {
        // フレンド関係を削除（双方向）
        Friendship::where(function($query) use ($user, $friend) {
            $query->where('user_id', $user->user_id)
                  ->where('friend_id', $friend->user_id);
        })->orWhere(function($query) use ($user, $friend) {
            $query->where('user_id', $friend->user_id)
                  ->where('friend_id', $user->user_id);
        })->delete();
        
        return true;
    }

    /**
     * 現在のフレンド数を取得
     */
    public function getFriendCount(User $user): int
    {
        return Friendship::where('user_id', $user->user_id)->count();
    }

    /**
     * 最大フレンド数に達しているかチェック
     */
    public function isMaxFriendsReached(User $user): bool
    {
        return $this->getFriendCount($user) >= 10;
    }
}

