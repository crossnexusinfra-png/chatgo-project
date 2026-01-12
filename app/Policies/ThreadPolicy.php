<?php

namespace App\Policies;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ThreadPolicy
{
    /**
     * ユーザーがスレッドを作成できるかどうか
     * 
     * @param User $user 作成しようとしているユーザー
     * @param string|null $tag スレッドのタグ（R18判定用）
     * @param bool|null $isR18 R18フラグ（R18判定用）
     * @return bool
     */
    public function create(User $user, ?string $tag = null, ?bool $isR18 = null): bool
    {
        // R18タグ（3種類）を定義
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        
        // R18スレッドかどうかを判定
        $isR18Thread = $isR18 === true || ($tag && in_array($tag, $r18Tags));
        
        // R18スレッドを作成する場合、18歳以上かどうかをチェック
        if ($isR18Thread && !$user->isAdult()) {
            return false;
        }
        
        // 通常のスレッドまたは18歳以上のユーザーは作成可能
        return true;
    }

    /**
     * ユーザーがスレッドを更新できるかどうか
     */
    public function update(User $user, Thread $thread): bool
    {
        // スレッド主のみ更新可能（現在は編集機能なし）
        return $user->user_id === $thread->user_id;
    }

    /**
     * ユーザーがスレッドを削除できるかどうか
     */
    public function delete(User $user, Thread $thread): bool
    {
        // スレッド主のみ削除可能
        return $user->user_id === $thread->user_id;
    }

    /**
     * ユーザーがスレッドをお気に入りに追加できるかどうか
     */
    public function favorite(User $user, Thread $thread): bool
    {
        // ログインユーザーはお気に入りに追加可能
        return true;
    }

    /**
     * ユーザーがスレッドを閲覧できるかどうか
     */
    public function view(?User $user, Thread $thread): bool
    {
        // R18タグ（3種類）を定義
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        
        // R18スレッドかどうかを判定（is_r18=true または R18タグ）
        $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
        
        // R18スレッドでない場合は誰でも閲覧可能
        if (!$isR18Thread) {
            return true;
        }
        
        // R18スレッドの場合、18歳未満のユーザーは閲覧不可
        if ($user && !$user->isAdult()) {
            return false;
        }
        
        // 18歳以上または非ログインユーザーは閲覧可能（了承画面は別途表示）
        return true;
    }
}
