<?php

namespace App\Http\Controllers;

use App\Models\FreezeAppeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FreezeAppealController extends Controller
{
    public function store(Request $request)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        $validated = $request->validate([
            'message' => 'required|string|min:10|max:2000',
        ]);

        $user = Auth::user();
        $user->refresh();

        if (!$user->isFrozen()) {
            return redirect()
                ->route('threads.index')
                ->withErrors(['freeze_appeal' => \App\Services\LanguageService::trans('freeze_appeal_not_frozen', $lang)]);
        }

        if (!$user->freeze_period_started_at) {
            $user->freeze_period_started_at = now();
            $user->save();
        }

        try {
            DB::transaction(function () use ($user, $validated) {
                $locked = \App\Models\User::where('user_id', $user->user_id)->lockForUpdate()->first();
                if (!$locked || !$locked->isFrozen()) {
                    throw new \RuntimeException('not_frozen');
                }
                if (!$locked->freeze_period_started_at) {
                    $locked->freeze_period_started_at = now();
                    $locked->save();
                }
                $p = $locked->freeze_period_started_at;

                $exists = FreezeAppeal::where('user_id', $locked->user_id)
                    ->where('freeze_period_started_at', $p)
                    ->lockForUpdate()
                    ->exists();
                if ($exists) {
                    throw new \RuntimeException('duplicate');
                }

                FreezeAppeal::create([
                    'user_id' => $locked->user_id,
                    'message' => $validated['message'],
                    'out_count_snapshot' => $locked->calculateOutCount(),
                    'frozen_until_snapshot' => $locked->frozen_until,
                    'is_permanent_snapshot' => (bool) $locked->is_permanently_banned,
                    'freeze_period_started_at' => $p,
                    'status' => 'pending',
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'duplicate') {
                return back()->withErrors([
                    'freeze_appeal' => \App\Services\LanguageService::trans('freeze_appeal_already_submitted', $lang),
                ]);
            }
            if ($e->getMessage() === 'not_frozen') {
                return redirect()
                    ->route('threads.index')
                    ->withErrors(['freeze_appeal' => \App\Services\LanguageService::trans('freeze_appeal_not_frozen', $lang)]);
            }

            throw $e;
        }

        return redirect()
            ->route('threads.index')
            ->with('success', \App\Services\LanguageService::trans('freeze_appeal_submitted', $lang));
    }
}
