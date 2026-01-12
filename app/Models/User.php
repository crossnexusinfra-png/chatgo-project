<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use App\Models\ResidenceHistory;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'user_id';

    /**
     * テーブル名を指定
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'user_identifier',
        'email',
        'phone',
        'nationality',
        'residence',
        'birthdate',
        'password',
        'language',
        'sms_verified_at',
        'email_verified_at',
        'is_verified',
        'profile_image',
        'bio',
        'frozen_until',
        'freeze_count',
        'is_permanently_banned',
    ];
    
    /**
     * 変更不可の属性
     */
    protected $guarded = [];
    
    /**
     * モデルのブートメソッド
     */
    protected static function boot()
    {
        parent::boot();
        
        // 既存レコードのusernameとuser_identifierの変更を禁止
        static::updating(function ($user) {
            if ($user->exists) {
                $original = $user->getOriginal();
                if (isset($original['username']) && $user->username !== $original['username']) {
                    $user->username = $original['username'];
                }
                if (isset($original['user_identifier']) && $user->user_identifier !== $original['user_identifier']) {
                    $user->user_identifier = $original['user_identifier'];
                }
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'birthdate' => 'date',
            'sms_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'is_verified' => 'boolean',
            'frozen_until' => 'datetime',
            'is_permanently_banned' => 'boolean',
        ];
    }

    /**
     * このユーザーが作成したスレッドを取得します。
     */
    public function threads()
    {
        return $this->hasMany(Thread::class, 'user_id', 'user_id');
    }

    /**
     * このユーザーが作成したレスポンスを取得します。
     */
    public function responses()
    {
        return $this->hasMany(Response::class, 'user_id', 'user_id');
    }

    /**
     * このユーザーのアクセス記録を取得します。
     * 注意: user_idベースのリレーションを使用
     */
    public function accesses()
    {
        return $this->hasMany(ThreadAccess::class, 'user_id', 'user_id');
    }

    /**
     * このユーザーのお気に入りレコード
     */
    public function threadFavorites()
    {
        return $this->hasMany(ThreadFavorite::class, 'user_id', 'user_id');
    }

    /**
     * このユーザーがお気に入りしたスレッド
     */
    public function favoriteThreads()
    {
        return $this->belongsToMany(Thread::class, 'thread_favorites', 'user_id', 'thread_id', 'user_id', 'thread_id')
            ->withTimestamps();
    }

    /**
     * このユーザーの居住地変更履歴を取得します。
     */
    public function residenceHistories()
    {
        return $this->hasMany(ResidenceHistory::class, 'user_id', 'user_id')->orderBy('changed_at', 'desc');
    }

    /**
     * このユーザーのフレンド関係
     */
    public function friendships()
    {
        return $this->hasMany(Friendship::class, 'user_id', 'user_id');
    }

    /**
     * このユーザーのフレンド
     */
    public function friends()
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id', 'user_id', 'user_id');
    }

    /**
     * このユーザーが送信したフレンド申請
     */
    public function sentFriendRequests()
    {
        return $this->hasMany(FriendRequest::class, 'from_user_id', 'user_id');
    }

    /**
     * このユーザーが受信したフレンド申請
     */
    public function receivedFriendRequests()
    {
        return $this->hasMany(FriendRequest::class, 'to_user_id', 'user_id');
    }

    /**
     * このユーザーが送信したコイン
     */
    public function sentCoins()
    {
        return $this->hasMany(CoinSend::class, 'from_user_id', 'user_id');
    }

    /**
     * このユーザーが受信したコイン
     */
    public function receivedCoins()
    {
        return $this->hasMany(CoinSend::class, 'to_user_id', 'user_id');
    }

    /**
     * このユーザーの招待記録
     */
    public function invites()
    {
        return $this->hasMany(UserInvite::class, 'inviter_id', 'user_id');
    }

    /**
     * このユーザーが招待された記録
     */
    public function invitedBy()
    {
        return $this->hasOne(UserInvite::class, 'invitee_id', 'user_id');
    }

    /**
     * このユーザーが18歳以上かどうかを判定します。
     * 生年月日がnullの場合はfalseを返します。
     *
     * @return bool
     */
    public function isAdult(): bool
    {
        if (!$this->birthdate) {
            return false;
        }

        // 18歳以上かどうかを判定（今日から18年前の日付と比較）
        $eighteenYearsAgo = now()->subYears(18);
        return $this->birthdate->lte($eighteenYearsAgo);
    }

    /**
     * このユーザーのプロフィールが非表示にすべきかどうかを判定
     * スレッド・レスポンスと同じ非表示条件を使用
     * 
     * @return bool
     */
    public function shouldBeHidden(): bool
    {
        // 特定理由によるスコア合計が1以上
        $restrictedScore = \App\Models\Report::calculateUserProfileRestrictedReasonScore($this->user_id);
        if ($restrictedScore >= 1.0) {
            return true;
        }
        
        return false;
    }

    /**
     * 管理側で通報が了承され、公開側で削除扱いかどうか
     */
    public function isDeletedByReport(): bool
    {
        return \App\Models\Report::where('reported_user_id', $this->user_id)
            ->where('is_approved', true)
            ->exists();
    }

    /**
     * このユーザーに対する通報を取得
     */
    public function reports()
    {
        return $this->hasMany(Report::class, 'reported_user_id', 'user_id');
    }

    /**
     * 現在のアウト数を計算（1年以内の承認済み通報のアウト数の合計）
     * 
     * @return float
     */
    public function calculateOutCount(): float
    {
        $oneYearAgo = now()->subYear();
        
        // スレッド通報のアウト数合計
        $threadOutCount = Report::whereNotNull('thread_id')
            ->where('is_approved', true)
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', $oneYearAgo)
            ->whereHas('thread', function($query) {
                $query->where('user_id', $this->user_id);
            })
            ->sum('out_count');
        
        // レスポンス通報のアウト数合計
        $responseOutCount = Report::whereNotNull('response_id')
            ->where('is_approved', true)
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', $oneYearAgo)
            ->whereHas('response', function($query) {
                $query->where('user_id', $this->user_id);
            })
            ->sum('out_count');
        
        // プロフィール通報のアウト数合計
        $profileOutCount = Report::whereNotNull('reported_user_id')
            ->where('reported_user_id', $this->user_id)
            ->where('is_approved', true)
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', $oneYearAgo)
            ->sum('out_count');
        
        $totalOutCount = (float)($threadOutCount ?? 0) + (float)($responseOutCount ?? 0) + (float)($profileOutCount ?? 0);
        
        return $totalOutCount;
    }

    /**
     * ユーザーが凍結されているかどうかを判定
     * 
     * @return bool
     */
    public function isFrozen(): bool
    {
        // 永久凍結の場合
        if ($this->is_permanently_banned) {
            return true;
        }
        
        // 一時凍結の場合
        if ($this->frozen_until && $this->frozen_until->isFuture()) {
            return true;
        }
        
        return false;
    }

    /**
     * ユーザーが警告状態かどうかを判定（1アウト以上）
     * 
     * @return bool
     */
    public function isWarned(): bool
    {
        $outCount = $this->calculateOutCount();
        return $outCount >= 1.0;
    }

    /**
     * ユーザーが一時凍結対象かどうかを判定（2アウト以上、4アウト未満）
     * 
     * @return bool
     */
    public function shouldBeTemporarilyFrozen(): bool
    {
        $outCount = $this->calculateOutCount();
        return $outCount >= 2.0 && $outCount < 4.0;
    }

    /**
     * ユーザーが永久凍結対象かどうかを判定（4アウト以上）
     * 
     * @return bool
     */
    public function shouldBePermanentlyBanned(): bool
    {
        $outCount = $this->calculateOutCount();
        return $outCount >= 4.0;
    }

    /**
     * 凍結期間を計算（アウト数が0になったら凍結回数もリセット）
     * 
     * @return \Carbon\Carbon|null
     */
    public function calculateFreezeDuration(): ?\Carbon\Carbon
    {
        $outCount = $this->calculateOutCount();
        
        // アウト数が0になったら凍結回数もリセット
        if ($outCount < 1.0) {
            $this->freeze_count = 0;
            $this->save();
            return null;
        }
        
        // 2アウト以上の場合のみ凍結
        if ($outCount < 2.0) {
            return null;
        }
        
        $freezeCount = $this->freeze_count;
        
        // 凍結期間を決定
        if ($freezeCount === 0) {
            // 1回目：24時間
            return now()->addHours(24);
        } elseif ($freezeCount === 1) {
            // 2回目：72時間
            return now()->addHours(72);
        } elseif ($freezeCount === 2) {
            // 3回目：1週間
            return now()->addWeek();
        } else {
            // 4回目以降：1カ月
            return now()->addMonth();
        }
    }

    /**
     * 国籍を英語コードで取得（常に英語コードを返す）
     *
     * @return string
     */
    public function getNationalityDisplayAttribute(): string
    {
        // 既に英語コードの場合はそのまま返す
        $validCodes = ['JP', 'US', 'GB', 'CA', 'AU', 'OTHER'];
        if (in_array($this->nationality, $validCodes)) {
            return $this->nationality;
        }
        
        // 日本語の国名が保存されている場合、英語コードに変換
        $countryMap = [
            '日本' => 'JP',
            'アメリカ' => 'US',
            'イギリス' => 'GB',
            'カナダ' => 'CA',
            'オーストラリア' => 'AU',
            'その他' => 'OTHER',
        ];
        
        return $countryMap[$this->nationality] ?? $this->nationality ?? 'OTHER';
    }

    /**
     * 居住地を英語コードで取得（常に英語コードを返す）
     *
     * @return string
     */
    public function getResidenceDisplayAttribute(): string
    {
        // 既に英語コードの場合はそのまま返す
        $validCodes = ['JP', 'US', 'GB', 'CA', 'AU', 'OTHER'];
        if (in_array($this->residence, $validCodes)) {
            return $this->residence;
        }
        
        // 日本語の国名が保存されている場合、英語コードに変換
        $countryMap = [
            '日本' => 'JP',
            'アメリカ' => 'US',
            'イギリス' => 'GB',
            'カナダ' => 'CA',
            'オーストラリア' => 'AU',
            'その他' => 'OTHER',
        ];
        
        return $countryMap[$this->residence] ?? $this->residence ?? 'OTHER';
    }
}
