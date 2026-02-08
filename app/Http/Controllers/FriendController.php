<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Services\FriendService;
use App\Services\LanguageService;
use App\Models\User;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\CoinSend;
use App\Policies\FriendRequestPolicy;

class FriendController extends Controller
{
    protected $friendService;

    public function __construct(FriendService $friendService)
    {
        $this->friendService = $friendService;
    }

    /**
     * フレンド一覧を表示
     */
    public function index()
    {
        $user = Auth::user();
        $lang = LanguageService::getCurrentLanguage();
        
        // フレンド機能の条件状態を取得
        $conditions = $this->friendService->getFriendFeatureConditions($user);
        $isEnabled = $this->friendService->isFriendFeatureEnabled($user);
        
        // 条件を満たしていない場合でも画面を表示
        $friendships = collect();
        $inviteCode = '';
        $receivedRequests = collect();
        $sentRequests = collect();
        $availableUsers = [];
        $friendCount = 0;
        $maxFriends = 10;
        $isMaxFriendsReached = false;
        
        if ($isEnabled) {
            // フレンド一覧を取得
            $friendships = Friendship::where('user_id', $user->user_id)
                ->with('friend')
                ->get();
            
            // 各フレンドのコイン送信可能状態を取得
            $coinSendStatuses = [];
            foreach ($friendships as $friendship) {
                $friendId = $friendship->friend->user_id;
                $lastSend = CoinSend::where('from_user_id', $user->user_id)
                    ->where('to_user_id', $friendId)
                    ->latest('sent_at')
                    ->first();
                
                $canSend = true;
                $remainingSeconds = 0;
                
                if ($lastSend && $lastSend->next_available_at && now() < $lastSend->next_available_at) {
                    $canSend = false;
                    $remainingSeconds = max(0, now()->diffInSeconds($lastSend->next_available_at, false));
                }
                
                $coinSendStatuses[$friendId] = [
                    'can_send' => $canSend,
                    'remaining_seconds' => $remainingSeconds,
                    'next_available_at' => $lastSend ? $lastSend->next_available_at : null,
                ];
            }
            
            // 招待コードを取得
            $inviteCode = $this->friendService->generateInviteCode($user);
            
            // 受信した申請
            $receivedRequests = FriendRequest::where('to_user_id', $user->user_id)
                ->where('status', 'pending')
                ->with('fromUser')
                ->get();
            
            // 送信した申請
            $sentRequests = FriendRequest::where('from_user_id', $user->user_id)
                ->where('status', 'pending')
                ->with('toUser')
                ->get();
            
            // フレンド申請可能なユーザー一覧を取得
            $availableUsers = $this->friendService->getAvailableFriendRequests($user);
            
            // 現在のフレンド数と最大フレンド数
            $friendCount = $this->friendService->getFriendCount($user);
            $isMaxFriendsReached = $this->friendService->isMaxFriendsReached($user);
        } else {
            $coinSendStatuses = [];
        }
        
        return view('friends.index', compact(
            'friendships', 
            'inviteCode', 
            'receivedRequests', 
            'sentRequests', 
            'availableUsers',
            'friendCount',
            'maxFriends',
            'isMaxFriendsReached',
            'conditions',
            'isEnabled',
            'lang',
            'coinSendStatuses'
        ));
    }

    /**
     * フレンド申請を送信
     */
    public function sendRequest(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'user_id' => 'required|exists:users,user_id',
        ]);
        
        $targetUser = User::findOrFail($request->user_id);
        
        // IDOR防止: フレンド申請の送信権限をチェック
        $policy = new FriendRequestPolicy();
        if (!$policy->send($user, $targetUser)) {
            abort(403, 'この操作を実行する権限がありません');
        }
        
        $result = $this->friendService->sendFriendRequest($user, $targetUser);
        
        $lang = LanguageService::getCurrentLanguage();
        
        if ($result) {
            return back()->with('success', LanguageService::trans('friend_request_sent', $lang));
        } else {
            return back()->with('error', LanguageService::trans('friend_request_failed', $lang));
        }
    }

    /**
     * フレンド申請を承認
     */
    public function acceptRequest(Request $request, FriendRequest $friendRequest)
    {
        $user = Auth::user();
        
        // IDOR防止: 申請の受信者のみ承認可能
        Gate::authorize('accept', $friendRequest);
        
        $result = $this->friendService->acceptFriendRequest($friendRequest);
        
        $lang = LanguageService::getCurrentLanguage();
        
        if ($result) {
            return back()->with('success', LanguageService::trans('friend_request_accepted', $lang));
        } else {
            return back()->with('error', LanguageService::trans('friend_request_accept_failed', $lang));
        }
    }

    /**
     * フレンド申請を拒否
     */
    public function rejectRequest(Request $request, FriendRequest $friendRequest)
    {
        $user = Auth::user();
        
        // IDOR防止: 申請の受信者のみ拒否可能
        Gate::authorize('reject', $friendRequest);
        
        $result = $this->friendService->rejectFriendRequest($friendRequest);
        
        $lang = LanguageService::getCurrentLanguage();
        
        if ($result) {
            return back()->with('success', LanguageService::trans('friend_request_rejected', $lang));
        } else {
            return back()->with('error', LanguageService::trans('friend_request_reject_failed', $lang));
        }
    }

    /**
     * フレンドにコインを送信
     */
    public function sendCoins(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'friend_id' => 'required|exists:users,user_id',
        ]);
        
        $friend = User::findOrFail($request->friend_id);
        
        // IDOR防止: フレンドにコインを送信する権限をチェック
        $policy = new FriendRequestPolicy();
        if (!$policy->sendCoins($user, $friend)) {
            abort(403, 'この操作を実行する権限がありません');
        }
        
        $result = $this->friendService->sendCoinsToFriend($user, $friend);
        
        $lang = LanguageService::getCurrentLanguage();
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }
    }

    /**
     * フレンドを削除
     */
    public function deleteFriend(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'friend_id' => 'required|exists:users,user_id',
        ]);
        
        $friend = User::findOrFail($request->friend_id);
        
        // IDOR防止: フレンドを削除する権限をチェック
        $policy = new FriendRequestPolicy();
        if (!$policy->deleteFriend($user, $friend)) {
            abort(403, 'この操作を実行する権限がありません');
        }
        
        $result = $this->friendService->deleteFriend($user, $friend);
        
        $lang = LanguageService::getCurrentLanguage();
        
        if ($result) {
            return back()->with('success', LanguageService::trans('friend_deleted', $lang));
        } else {
            return back()->with('error', LanguageService::trans('friend_delete_failed', $lang));
        }
    }

    /**
     * フレンド申請可能なユーザーからの拒否（条件リセット）
     */
    public function rejectAvailable(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'user_id' => 'required|exists:users,user_id',
        ]);
        
        $targetUser = User::findOrFail($request->user_id);
        
        // IDOR防止: フレンド申請の送信権限をチェック（拒否も同様の権限が必要）
        $policy = new FriendRequestPolicy();
        if (!$policy->send($user, $targetUser)) {
            abort(403, 'この操作を実行する権限がありません');
        }
        
        // フレンド申請条件をリセット
        $this->friendService->resetFriendRequestConditions($user, $targetUser);
        
        $lang = LanguageService::getCurrentLanguage();
        
        return back()->with('success', LanguageService::trans('friend_request_rejected', $lang));
    }
}

