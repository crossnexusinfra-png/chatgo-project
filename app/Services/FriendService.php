<?php

namespace App\Services;

use App\Models\User;
use App\Models\Friendship;
use App\Models\FriendRequest;
use App\Models\CoinSend;
use App\Models\ThreadInteraction;
use App\Models\Response;
use App\Models\UserInvite;
use Illuminate\Support\Facades\DB;

class FriendService
{
    /**
     * フレンド機能が有効かチェック
     */
    public function isFriendFeatureEnabled(User $user): bool
    {
        // 利用者ページ用管理者はフレンド機能を利用しない
        if (!empty($user->is_admin)) {
            return false;
        }

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
     * フレンド申請可能かチェック（送信枠の上限は別）
     */
    public function canSendFriendRequest(User $fromUser, User $toUser): array
    {
        if (!$this->hasFriendSlotCapacityIncludingPendingOutgoing($fromUser)) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();

            return [
                'can_send' => false,
                'reason' => \App\Services\LanguageService::trans('friend_request_capacity_full', $lang),
            ];
        }

        return $this->evaluateFriendRequestEligibilityWithoutCapacity($fromUser, $toUser);
    }

    /**
     * 送信枠を除くフレンド申請要件を満たすか（一覧表示で枠オーバー時も判定するために利用）
     */
    public function evaluateFriendRequestEligibilityWithoutCapacity(User $fromUser, User $toUser): array
    {
        // 自分自身には申請できない
        if ($fromUser->user_id === $toUser->user_id) {
            return [
                'can_send' => false,
                'reason' => '自分自身には申請できません',
            ];
        }

        // 管理者アカウントを含むペアではフレンド申請不可（相互に成立させない）
        if (!empty($fromUser->is_admin) || !empty($toUser->is_admin)) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();

            return [
                'can_send' => false,
                'reason' => \App\Services\LanguageService::trans('friend_request_blocked_involves_admin', $lang),
            ];
        }

        // 既にフレンドかチェック
        $friendship = Friendship::where(function ($query) use ($fromUser, $toUser) {
            $query->where('user_id', $fromUser->user_id)
                ->where('friend_id', $toUser->user_id);
        })->orWhere(function ($query) use ($fromUser, $toUser) {
            $query->where('user_id', $toUser->user_id)
                ->where('friend_id', $fromUser->user_id);
        })->first();

        if ($friendship) {
            return [
                'can_send' => false,
                'reason' => '既にフレンドです',
            ];
        }

        // 既に申請中かチェック（pending のみ。拒否済みは再度条件達成後に申請可能）
        $existingRequest = FriendRequest::query()
            ->where(function ($query) use ($fromUser, $toUser) {
                $query->where(function ($q) use ($fromUser, $toUser) {
                    $q->where('from_user_id', $fromUser->user_id)
                        ->where('to_user_id', $toUser->user_id);
                })->orWhere(function ($q) use ($fromUser, $toUser) {
                    $q->where('from_user_id', $toUser->user_id)
                        ->where('to_user_id', $fromUser->user_id);
                });
            })
            ->pending()
            ->first();

        if ($existingRequest) {
            return [
                'can_send' => false,
                'reason' => '既に申請中です',
            ];
        }

        // 招待関係がある場合は会話条件を免除
        if ($this->hasInviteRelationship($fromUser, $toUser)) {
            return [
                'can_send' => true,
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
     * 招待関係（双方向）があるかチェック
     */
    private function hasInviteRelationship(User $user1, User $user2): bool
    {
        return UserInvite::where(function ($query) use ($user1, $user2) {
            $query->where('inviter_id', $user1->user_id)
                ->where('invitee_id', $user2->user_id);
        })->orWhere(function ($query) use ($user1, $user2) {
            $query->where('inviter_id', $user2->user_id)
                ->where('invitee_id', $user1->user_id);
        })->exists();
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
                    if ($user1Interaction->total_characters >= 1000 && $user2Interaction->total_characters >= 1000) {
                        return [
                            'can_request' => true,
                        ];
                    }
                }
            }
        }
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return [
            'can_request' => false,
            'reason' => \App\Services\LanguageService::trans('friend_request_interaction_not_met', $lang),
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
        if (!empty($fromUser->is_admin) || !empty($toUser->is_admin)) {
            return false;
        }

        if (!$this->hasFriendSlotCapacityIncludingPendingOutgoing($fromUser)) {
            return false;
        }

        $check = $this->canSendFriendRequest($fromUser, $toUser);
        if (!$check['can_send']) {
            return false;
        }

        return DB::transaction(function () use ($fromUser, $toUser): bool {
            $pair = FriendRequest::query()
                ->where('from_user_id', $fromUser->user_id)
                ->where('to_user_id', $toUser->user_id)
                ->lockForUpdate()
                ->first();

            if ($pair && $pair->status === 'pending') {
                return false;
            }
            if ($pair && $pair->status === 'accepted') {
                return false;
            }

            if (!$this->hasFriendSlotCapacityIncludingPendingOutgoing($fromUser)) {
                return false;
            }

            FriendRequest::query()->updateOrCreate(
                [
                    'from_user_id' => $fromUser->user_id,
                    'to_user_id' => $toUser->user_id,
                ],
                [
                    'status' => 'pending',
                    'requested_at' => now(),
                    'responded_at' => null,
                ]
            );

            return true;
        });
    }

    /**
     * 承認者側の枠のみで判定する（フレンド数＋自分が送った未処理申請）。申請者側の枠は承認可否に使わない。
     */
    public function accepterHasCapacityToAcceptFriend(User $accepter): bool
    {
        $pendingSentByAccepter = FriendRequest::query()
            ->where('from_user_id', $accepter->user_id)
            ->pending()
            ->count();

        return $this->getFriendCount($accepter) + $pendingSentByAccepter < $accepter->maxFriendsAllowed();
    }

    /**
     * 指定のフレンド申請を承認できるか（サーバー側 accept と同一条件）
     */
    public function canAcceptFriendRequest(FriendRequest $request): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        $fromUser = User::query()->find($request->from_user_id);
        $toUser = User::query()->find($request->to_user_id);
        if (($fromUser && !empty($fromUser->is_admin)) || ($toUser && !empty($toUser->is_admin))) {
            return false;
        }

        if (!$toUser) {
            return false;
        }

        return $this->accepterHasCapacityToAcceptFriend($toUser);
    }

    /**
     * フレンド申請を承認
     */
    public function acceptFriendRequest(FriendRequest $request): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        return DB::transaction(function () use ($request): bool {
            $locked = FriendRequest::query()
                ->lockForUpdate()
                ->find($request->getKey());
            if (!$locked || $locked->status !== 'pending') {
                return false;
            }

            $fromUser = User::query()->lockForUpdate()->find($locked->from_user_id);
            $toUser = User::query()->lockForUpdate()->find($locked->to_user_id);
            if (!$fromUser || !$toUser) {
                return false;
            }

            if (!empty($fromUser->is_admin) || !empty($toUser->is_admin)) {
                return false;
            }

            if (!$this->accepterHasCapacityToAcceptFriend($toUser)) {
                return false;
            }

            $reverseRequest = FriendRequest::query()
                ->where('from_user_id', $locked->to_user_id)
                ->where('to_user_id', $locked->from_user_id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            Friendship::create([
                'user_id' => $locked->from_user_id,
                'friend_id' => $locked->to_user_id,
                'friendship_date' => now(),
            ]);

            Friendship::create([
                'user_id' => $locked->to_user_id,
                'friend_id' => $locked->from_user_id,
                'friendship_date' => now(),
            ]);

            $locked->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            if ($reverseRequest) {
                $reverseRequest->update([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);
            }

            return true;
        });
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
        if (!empty($fromUser->is_admin) || !empty($toUser->is_admin)) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();

            return [
                'success' => false,
                'message' => \App\Services\LanguageService::trans('friend_request_blocked_involves_admin', $lang),
            ];
        }

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

        if ($toUser->is_permanently_banned) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();

            return [
                'success' => false,
                'message' => \App\Services\LanguageService::trans('friend_send_coins_peer_permanently_banned', $lang),
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

        if (!empty($user->is_admin)) {
            return [];
        }
        
        // すべてのユーザーを取得（自分以外）
        $allUsers = User::where('user_id', '!=', $user->user_id)->get();
        
        foreach ($allUsers as $otherUser) {
            if (!empty($otherUser->is_admin)) {
                continue;
            }

            $friendship = Friendship::where(function ($query) use ($user, $otherUser) {
                $query->where('user_id', $user->user_id)
                    ->where('friend_id', $otherUser->user_id);
            })->orWhere(function ($query) use ($user, $otherUser) {
                $query->where('user_id', $otherUser->user_id)
                    ->where('friend_id', $user->user_id);
            })->first();

            if ($friendship) {
                continue;
            }

            // 既に申請を送信しているかチェック
            $sentRequest = FriendRequest::query()
                ->where('from_user_id', $user->user_id)
                ->where('to_user_id', $otherUser->user_id)
                ->pending()
                ->first();

            // 既に申請を受け取っているかチェック
            $receivedRequest = FriendRequest::query()
                ->where('from_user_id', $otherUser->user_id)
                ->where('to_user_id', $user->user_id)
                ->pending()
                ->first();

            // 招待関係があるかチェック
            $invite = \App\Models\UserInvite::where(function ($query) use ($user, $otherUser) {
                $query->where('inviter_id', $user->user_id)
                    ->where('invitee_id', $otherUser->user_id);
            })->orWhere(function ($query) use ($user, $otherUser) {
                $query->where('inviter_id', $otherUser->user_id)
                    ->where('invitee_id', $user->user_id);
            })->first();

            $eligibleIgnoringCapacity = false;
            if ($invite) {
                $eligibleIgnoringCapacity = true;
            } else {
                $check = $this->evaluateFriendRequestEligibilityWithoutCapacity($user, $otherUser);
                $eligibleIgnoringCapacity = $check['can_send'];
            }

            $hasSlot = $this->hasFriendSlotCapacityIncludingPendingOutgoing($user);
            $canSend = $eligibleIgnoringCapacity && $hasSlot;

            $listedDespiteNoSendSlot = $eligibleIgnoringCapacity && !$hasSlot && !$sentRequest && !$receivedRequest;

            // 申請中（送信/受信）は常に表示。枠オーバーで除外されていた「申請要件は満たすが送信枠なし」も表示
            if ($canSend || $sentRequest || $receivedRequest || $listedDespiteNoSendSlot) {
                $availableUsers[] = [
                    'user' => $otherUser,
                    'sent_request' => $sentRequest,
                    'received_request' => $receivedRequest,
                    'is_invite' => $invite !== null,
                    'can_accept' => $receivedRequest ? $this->canAcceptFriendRequest($receivedRequest) : false,
                ];
            }
        }
        
        return $availableUsers;
    }

    /**
     * 「フレンド申請可能」一覧の拒否—送信済み pending があれば取り下げ（枠を解放）、なければ会話・招待条件のみリセット
     */
    public function rejectAvailableFriendConnection(User $user, User $targetUser): bool
    {
        if (!empty($user->is_admin)) {
            return false;
        }

        return DB::transaction(function () use ($user, $targetUser): bool {
            $outgoing = FriendRequest::query()
                ->where('from_user_id', $user->user_id)
                ->where('to_user_id', $targetUser->user_id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($outgoing) {
                $reverseRequest = FriendRequest::query()
                    ->where('from_user_id', $targetUser->user_id)
                    ->where('to_user_id', $user->user_id)
                    ->where('status', 'pending')
                    ->first();

                if ($reverseRequest) {
                    $reverseRequest->delete();
                }

                $outgoing->update([
                    'status' => 'rejected',
                    'responded_at' => now(),
                ]);
            }

            $this->resetFriendRequestConditions($user, $targetUser);

            return true;
        });
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
        if (!empty($user->is_admin)) {
            return false;
        }

        // フレンド関係を削除（双方向）
        Friendship::where(function($query) use ($user, $friend) {
            $query->where('user_id', $user->user_id)
                  ->where('friend_id', $friend->user_id);
        })->orWhere(function($query) use ($user, $friend) {
            $query->where('user_id', $friend->user_id)
                  ->where('friend_id', $user->user_id);
        })->delete();

        // 会話条件の集計をリセットし、再フレンド時は同一条件を満たす必要がある
        ThreadInteraction::where(function ($query) use ($user, $friend) {
            $query->where('user_id', $user->user_id)
                ->where('other_user_id', $friend->user_id);
        })->orWhere(function ($query) use ($user, $friend) {
            $query->where('user_id', $friend->user_id)
                ->where('other_user_id', $user->user_id);
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
     * 送信した未処理（pending）のフレンド申請数
     */
    public function countPendingSentFriendRequests(User $user): int
    {
        return FriendRequest::query()
            ->where('from_user_id', $user->user_id)
            ->pending()
            ->count();
    }

    /**
     * フレンド数＋自分が送った未処理申請の合計が上限未満か
     */
    public function hasFriendSlotCapacityIncludingPendingOutgoing(User $user): bool
    {
        return $this->getFriendCount($user) + $this->countPendingSentFriendRequests($user) < $user->maxFriendsAllowed();
    }

    /**
     * 新規申請を送れない（フレンド＋未処理送信申請で枠を使い切り）
     */
    public function isMaxFriendsReached(User $user): bool
    {
        return !$this->hasFriendSlotCapacityIncludingPendingOutgoing($user);
    }
}

