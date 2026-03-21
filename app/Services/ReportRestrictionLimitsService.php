<?php

namespace App\Services;

use App\Models\Response;
use App\Models\Thread;

class ReportRestrictionLimitsService
{
    public function isRestrictedUser(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }
        $restrictionService = app(ReportRestrictionService::class);
        return $restrictionService->isUserRestrictedNow($userId);
    }

    public function threadCreateLimitPerDay(int $userId): int
    {
        if ($this->isRestrictedUser($userId)) {
            return (int) (config('report_restrictions.limits_while_restricted.threads_per_day', 1));
        }
        return 2; // 既存仕様
    }

    public function fileUploadLimitPerDay(int $userId): int
    {
        if ($this->isRestrictedUser($userId)) {
            return (int) (config('report_restrictions.limits_while_restricted.files_per_day', 5));
        }
        return PHP_INT_MAX;
    }

    public function urlPostLimitPerDay(int $userId): int
    {
        if ($this->isRestrictedUser($userId)) {
            return (int) (config('report_restrictions.limits_while_restricted.urls_per_day', 5));
        }
        return PHP_INT_MAX;
    }

    public function todayThreadCreateCount(int $userId): int
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        return (int) Thread::where('user_id', $userId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();
    }

    public function todayFileUploadCount(int $userId): int
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $threadImageCount = (int) Thread::where('user_id', $userId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->count();

        $responseMediaCount = (int) Response::where('user_id', $userId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->whereNotNull('media_file')
            ->where('media_file', '!=', '')
            ->count();

        return $threadImageCount + $responseMediaCount;
    }

    public function todayUrlPostCount(int $userId): int
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        // Thread の本文は Response に保存されるので Response のみで十分
        return (int) Response::where('user_id', $userId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->where(function ($q) {
                $q->where('body', 'like', '%http://%')
                  ->orWhere('body', 'like', '%https://%');
            })
            ->count();
    }
}

