<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Suggestion extends Model
{
    use HasFactory;

    protected $table = 'suggestions';

    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'message',
        'completed',
        'starred',
        'coin_amount',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'starred' => 'boolean',
        ];
    }

    /**
     * 状態を取得（null=未処理、true=採用、false=非採用）
     */
    public function getStatusAttribute(): string
    {
        if ($this->completed === null) {
            return '未処理';
        }
        return $this->completed ? '採用' : '非採用';
    }
}


