<?php

return [
    // 通報制限が発生したとき、ユーザーが「了承」した場合の処分軽減係数（アウト数に乗算）
    'self_ack_out_multiplier' => (float) env('REPORT_SELF_ACK_OUT_MULTIPLIER', 0.7),

    // プロフィール通報のアウト数係数（承認/了承の両方に適用、後から調整可能）
    'profile_out_multiplier' => (float) env('REPORT_PROFILE_OUT_MULTIPLIER', 1.3),

    // 通報制限同時件数で一時凍結する閾値
    'freeze_threshold' => (int) env('REPORT_RESTRICTION_FREEZE_THRESHOLD', 5),

    // 上記閾値に達したときの一時凍結時間（時間）
    'freeze_duration_hours' => (int) env('REPORT_RESTRICTION_FREEZE_DURATION_HOURS', 24),

    // 通報制限中の追加制限
    'limits_while_restricted' => [
        'threads_per_day' => 1,
        'files_per_day' => 5,
        'urls_per_day' => 5,
    ],

    // プロフィール通報を了承した場合、自己紹介文を無記載に戻して変更不可にする期間（日）
    'profile_lock_days' => (int) env('REPORT_PROFILE_LOCK_DAYS', 30),

    // 1アウト警告お知らせを再送しない期間（直近◯ヶ月以内に「利用に関する警告」があれば送らない）
    'out_warning_suppress_months' => (int) env('REPORT_OUT_WARNING_SUPPRESS_MONTHS', 1),
];

