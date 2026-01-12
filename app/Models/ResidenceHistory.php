<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResidenceHistory extends Model
{
    use HasFactory;

    protected $table = 'residence_histories';

    protected $fillable = [
        'user_id',
        'old_residence',
        'new_residence',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * この履歴を所有するユーザーを取得
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * 国コードから国名を取得
     */
    public static function getCountryName($code)
    {
        $countries = [
            'JP' => '日本',
            'US' => 'アメリカ',
            'KR' => '韓国',
            'CN' => '中国',
            'GB' => 'イギリス',
            'DE' => 'ドイツ',
            'FR' => 'フランス',
            'CA' => 'カナダ',
            'AU' => 'オーストラリア',
            'OTHER' => 'その他',
        ];

        return $countries[$code] ?? $code;
    }
}

