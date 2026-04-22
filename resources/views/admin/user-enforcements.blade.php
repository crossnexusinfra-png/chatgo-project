@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_user_enforcement_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    <p class="admin-reports-back-link"><a href="{{ route('admin.dashboard') }}" class="admin-link">← {{ \App\Services\LanguageService::trans('admin_dashboard_back', $lang) }}</a></p>
    <h1 class="admin-title">{{ \App\Services\LanguageService::trans('admin_user_enforcement_title', $lang) }}</h1>

    @if (session('success'))
        <div class="admin-messages-success">{{ session('success') }}</div>
    @endif

    <div class="card admin-card">
        <form method="post" action="{{ route('admin.user-enforcements.store') }}">
            @csrf
            <label>{{ \App\Services\LanguageService::trans('admin_user_enforcement_target', $lang) }}</label>
            <input type="text" name="target_user" value="{{ old('target_user') }}" placeholder="{{ \App\Services\LanguageService::trans('admin_user_enforcement_target_placeholder', $lang) }}" required>

            <label>{{ \App\Services\LanguageService::trans('admin_user_enforcement_type', $lang) }}</label>
            <select name="enforcement_type" id="enforcement_type_select" required>
                <option value="restriction" {{ old('enforcement_type') === 'restriction' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_user_enforcement_type_restriction', $lang) }}</option>
                <option value="temporary_freeze" {{ old('enforcement_type') === 'temporary_freeze' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_user_enforcement_type_temp', $lang) }}</option>
                <option value="permanent_freeze" {{ old('enforcement_type') === 'permanent_freeze' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_user_enforcement_type_permanent', $lang) }}</option>
            </select>

            <div id="duration_hours_wrap">
                <label>{{ \App\Services\LanguageService::trans('admin_user_enforcement_duration_hours', $lang) }}</label>
                <input type="number" name="duration_hours" min="1" max="8760" value="{{ old('duration_hours', 24) }}">
            </div>

            <label>{{ \App\Services\LanguageService::trans('admin_user_enforcement_reason', $lang) }}</label>
            <textarea name="reason" rows="4" maxlength="1000" placeholder="{{ \App\Services\LanguageService::trans('admin_user_enforcement_reason_placeholder', $lang) }}">{{ old('reason') }}</textarea>

            <label>{{ \App\Services\LanguageService::trans('admin_user_enforcement_notice_title_ja', $lang) }}</label>
            <input type="text" name="notice_title_ja" id="notice_title_ja" maxlength="255" value="{{ old('notice_title_ja') }}" placeholder="{{ \App\Services\LanguageService::trans('admin_user_enforcement_notice_title_ja_placeholder', $lang) }}">

            <label>{{ \App\Services\LanguageService::trans('admin_user_enforcement_notice_title_en', $lang) }}</label>
            <input type="text" name="notice_title_en" id="notice_title_en" maxlength="255" value="{{ old('notice_title_en') }}" placeholder="{{ \App\Services\LanguageService::trans('admin_user_enforcement_notice_title_en_placeholder', $lang) }}">

            <label>{{ \App\Services\LanguageService::trans('admin_user_enforcement_notice_body_ja', $lang) }}</label>
            <textarea name="notice_body_ja" id="notice_body_ja" rows="6" maxlength="10000" required placeholder="{{ \App\Services\LanguageService::trans('admin_user_enforcement_notice_body_ja_placeholder', $lang) }}">{{ old('notice_body_ja') }}</textarea>

            <label>{{ \App\Services\LanguageService::trans('admin_user_enforcement_notice_body_en', $lang) }}</label>
            <textarea name="notice_body_en" id="notice_body_en" rows="6" maxlength="10000" placeholder="{{ \App\Services\LanguageService::trans('admin_user_enforcement_notice_body_en_placeholder', $lang) }}">{{ old('notice_body_en') }}</textarea>
            <p class="admin-form-note">{{ \App\Services\LanguageService::trans('admin_user_enforcement_notice_placeholder_hint', $lang) }}</p>

            <div class="admin-messages-submit-container">
                <button type="submit">{{ \App\Services\LanguageService::trans('admin_user_enforcement_apply', $lang) }}</button>
            </div>
        </form>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>{{ \App\Services\LanguageService::trans('admin_user_enforcement_target', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_user_enforcement_type', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_user_enforcement_started_at', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_user_enforcement_expires_at', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_user_enforcement_reason', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_user_enforcement_status', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_actions', $lang) }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($enforcements as $e)
            <tr>
                <td>
                    {{ $e->user?->username ?? 'Unknown' }}
                    <div>
                        <code class="admin-copy-token">{{ $e->user?->user_identifier ?? $e->user_id }}</code>
                    </div>
                </td>
                <td>
                    @if($e->enforcement_type === 'restriction')
                        {{ \App\Services\LanguageService::trans('admin_user_enforcement_type_restriction', $lang) }}
                    @elseif($e->enforcement_type === 'temporary_freeze')
                        {{ \App\Services\LanguageService::trans('admin_user_enforcement_type_temp', $lang) }}
                    @else
                        {{ \App\Services\LanguageService::trans('admin_user_enforcement_type_permanent', $lang) }}
                    @endif
                </td>
                <td>{{ optional($e->started_at)->format('Y-m-d H:i') }}</td>
                <td>{{ optional($e->expires_at)->format('Y-m-d H:i') ?? '—' }}</td>
                <td>{{ $e->reason ?: '—' }}</td>
                <td>
                    @if($e->released_at)
                        {{ \App\Services\LanguageService::trans('admin_user_enforcement_status_released', $lang) }}
                    @elseif($e->enforcement_type === 'temporary_freeze' && $e->expires_at && $e->expires_at->isPast())
                        {{ \App\Services\LanguageService::trans('admin_user_enforcement_status_expired', $lang) }}
                    @else
                        {{ \App\Services\LanguageService::trans('admin_user_enforcement_status_active', $lang) }}
                    @endif
                </td>
                <td>
                    @if(!$e->released_at && in_array($e->enforcement_type, ['restriction', 'temporary_freeze', 'permanent_freeze'], true))
                        <form method="post" action="{{ route('admin.user-enforcements.release', $e->id) }}" class="admin-form-inline">
                            @csrf
                            <button type="submit">{{ \App\Services\LanguageService::trans('admin_user_enforcement_release', $lang) }}</button>
                        </form>
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7">{{ \App\Services\LanguageService::trans('admin_user_enforcement_no_records', $lang) }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @if(method_exists($enforcements, 'hasPages') && $enforcements->hasPages())
        <div class="admin-messages-submit-container">
            @if($enforcements->onFirstPage())
                <span class="admin-link">←</span>
            @else
                <a class="admin-link" href="{{ $enforcements->previousPageUrl() }}">←</a>
            @endif
            <span> {{ $enforcements->currentPage() }} / {{ $enforcements->lastPage() }} </span>
            @if($enforcements->hasMorePages())
                <a class="admin-link" href="{{ $enforcements->nextPageUrl() }}">→</a>
            @else
                <span class="admin-link">→</span>
            @endif
        </div>
    @endif
</div>

<script nonce="{{ $csp_nonce ?? '' }}">
    (function () {
        const templates = @json($noticeTemplates ?? []);
        const hasOldNotice = @json(
            old('notice_title_ja') !== null
            || old('notice_title_en') !== null
            || old('notice_body_ja') !== null
            || old('notice_body_en') !== null
        );
        const typeEl = document.getElementById('enforcement_type_select');
        const durationWrap = document.getElementById('duration_hours_wrap');
        const titleJa = document.getElementById('notice_title_ja');
        const titleEn = document.getElementById('notice_title_en');
        const bodyJa = document.getElementById('notice_body_ja');
        const bodyEn = document.getElementById('notice_body_en');

        function applyTemplate(type) {
            const t = templates[type];
            if (!t) return;
            if (titleJa) titleJa.value = t.title_ja || '';
            if (titleEn) titleEn.value = t.title_en || '';
            if (bodyJa) bodyJa.value = t.body_ja || '';
            if (bodyEn) bodyEn.value = t.body_en || '';
        }

        function sync() {
            if (!typeEl || !durationWrap) return;
            durationWrap.style.display = (typeEl.value === 'restriction' || typeEl.value === 'temporary_freeze') ? 'block' : 'none';
        }
        if (typeEl) {
            typeEl.addEventListener('change', function () {
                sync();
                applyTemplate(typeEl.value);
            });
            sync();
            if (!hasOldNotice) {
                applyTemplate(typeEl.value);
            }
        }
    })();
</script>
@endsection

