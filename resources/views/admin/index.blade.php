@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_index_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    <div class="admin-index-card">
        <h1 class="admin-index-title">{{ \App\Services\LanguageService::trans('admin_index_title', $lang) }}</h1>
        <p class="admin-index-description">{{ \App\Services\LanguageService::trans('admin_index_description', $lang) }}</p>
        <div class="admin-index-access-record">
            <div class="admin-index-access-record-title">{{ \App\Services\LanguageService::trans('admin_index_last_access', $lang) }}</div>
            <div>{{ \App\Services\LanguageService::trans('admin_index_last_guest', $lang) }}: {{ optional($lastGuest?->created_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
            <div>{{ \App\Services\LanguageService::trans('admin_index_last_login', $lang) }}: {{ optional($lastLogin?->created_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
            <div class="admin-index-ops-status">
                <div>
                    直近5分のサーバーエラー(5xx): <strong>{{ $recentServerErrorCount ?? 0 }}</strong>
                    @if(($recentServerErrorCount ?? 0) > 0)
                        <span class="admin-new-inline">要確認</span>
                    @endif
                </div>
                <div>
                    異常通知設定:
                    @if($cloudflareAlertsEnabled ?? false)
                        <strong>有効</strong>
                        （Webhook: {{ ($cloudflareWebhookConfigured ?? false) ? '設定済み' : '未設定' }} / Email: {{ ($cloudflareEmailConfigured ?? false) ? '設定済み' : '未設定' }}）
                    @else
                        <strong>無効</strong>
                    @endif
                </div>
            </div>
        </div>
        <div class="admin-index-access-record">
            <div class="admin-index-access-record-title">運用トリガー（直近5分）</div>
            @foreach(($operationalTriggers ?? []) as $trigger)
                <div class="admin-ops-trigger-row">
                    <strong>{{ $trigger['label'] ?? '-' }}</strong>:
                    <span>{{ $trigger['count'] ?? 0 }}件</span>
                    @if(($trigger['is_triggered'] ?? false) && ($trigger['trigger_mode'] ?? '') === 'single_critical')
                        <span class="admin-new-inline">単発重大</span>
                    @elseif(($trigger['is_triggered'] ?? false) && ($trigger['trigger_mode'] ?? '') === 'recurrent')
                        <span class="admin-new-inline">連発判定</span>
                    @endif
                    @if(($trigger['severity'] ?? 'medium') === 'high' && ($trigger['is_triggered'] ?? false))
                        <span class="admin-new-inline">優先調査</span>
                    @endif
                    <div class="admin-muted">判定条件: {{ $trigger['rule_text'] ?? '-' }}</div>
                    <div class="admin-muted">{{ $trigger['description'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
        <ul class="admin-index-menu">
            <li>
                <a href="{{ route('admin.reports') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_reports', $lang) }}</a>
                @if(!empty($newReports))
                    <span class="admin-new-badge">NEW {{ $newReports }}</span>
                @endif
            </li>
            <li>
                <a href="{{ route('admin.suggestions') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_suggestions', $lang) }}</a>
                @if(!empty($newSuggestions))
                    <span class="admin-new-badge">NEW {{ $newSuggestions }}</span>
                @endif
            </li>
            <li>
                <a href="{{ route('admin.freeze-appeals') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_freeze_appeals', $lang) }}</a>
                @if(!empty($newFreezeAppeals))
                    <span class="admin-new-badge">NEW {{ $newFreezeAppeals }}</span>
                @endif
            </li>
            <li><a href="{{ route('admin.user-enforcements') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_user_enforcements', $lang) }}</a></li>
            <li><a href="{{ route('admin.messages') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_messages', $lang) }}</a></li>
            <li><a href="{{ route('admin.logs') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_logs', $lang) }}</a></li>
        </ul>
    </div>
</div>
@endsection


