@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_logs_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    <div class="admin-logs-container-wrapper">
        <a href="{{ route('admin.dashboard') }}" class="admin-link admin-logs-back-link">← {{ \App\Services\LanguageService::trans('admin_logs_back', $lang) }}</a>

        <div class="admin-logs-card">
            <h1 class="admin-logs-title">{{ \App\Services\LanguageService::trans('admin_logs_file_display', $lang) }}</h1>
            <div class="admin-ops-guide">
                <h2 class="admin-ops-guide-title">操作ガイド（異常検知→原因特定）</h2>
                <div class="admin-muted">
                    <strong>単発重大:</strong> 5xx / 未処理例外 / 重要導線4xx / 重要導線timeout は1件でも対応対象です。<br>
                    <strong>連発判定:</strong> 4xx連発・timeout連発は、判定条件を超えたら対応対象です。
                </div>
                <ol class="admin-ops-guide-list">
                    <li><strong>サーバーエラー(5xx):</strong> status か request_id をクリック → 「サーバーエラー」→「イベントログ」→「アクセスログ」の順で同ID確認。</li>
                    <li><strong>未処理例外:</strong> request_id / event_id をクリック → 「サーバーエラー」の例外メッセージ確認 → 同IDのイベント・アクセスで発生経路確認。</li>
                    <li><strong>重要導線4xx:</strong> status=401/403/404/422 か request_id をクリック → 「アクセスログ」の path/method を確認 → 同IDイベントで直前処理確認。</li>
                    <li><strong>タイムアウト/接続失敗:</strong> request_id をクリック → 「サーバーエラー」の message を確認 → 同IDイベントで外部依存・処理段階を確認。</li>
                    <li><strong>WAL確認（必要時）:</strong> 上記IDで「WAL復元ログ」を開き、reason / wal_lsn / txid を確認。</li>
                </ol>
                <div class="admin-muted">
                    重要: <code>request_id / event_id / status</code> が3つ全部一致する必要はありません。まず <code>request_id</code> で相関追跡してください。
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if($fileExists)
                @php
                    $unconfirmedTriggers = collect($unconfirmedSingleCriticalTriggers ?? [])->filter(fn($t) => ($t['count'] ?? 0) > 0)->values();
                    $singleCriticalTriggers = collect($operationalTriggers ?? [])->filter(fn($t) => ($t['trigger_mode'] ?? '') === 'single_critical')->values();
                    $recurrentTriggers = collect($operationalTriggers ?? [])->filter(fn($t) => ($t['trigger_mode'] ?? '') === 'recurrent')->values();
                @endphp
                <h2>0. 未確認の単発重大（前回確認以降）</h2>
                <div class="admin-logs-inline-help">前回ログ確認（{{ optional($unconfirmedSince ?? null)->format('Y-m-d H:i:s') ?? '-' }}）以降の単発重大です。見落とし防止のため最優先で確認してください。</div>
                @if($unconfirmedTriggers->count() > 0)
                    @foreach($unconfirmedTriggers as $trigger)
                        <div class="admin-ops-trigger-card">
                            <div class="admin-ops-trigger-title">
                                <strong>{{ $trigger['label'] ?? '-' }}</strong>
                                <span class="admin-ops-trigger-count">{{ $trigger['count'] ?? 0 }}件</span>
                                <span class="admin-new-inline">未確認</span>
                                <span class="admin-new-inline">優先調査</span>
                            </div>
                            @php $examples = array_slice($trigger['examples'] ?? [], 0, 3); @endphp
                            @if(!empty($examples))
                                <div class="admin-logs-log-container">
                                    @foreach($examples as $example)
                                        <div class="log-line">
                                            {{ $example['created_at'] ?? '-' }} |
                                            @if(!empty($example['status_code']))
                                                status=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="status_code" data-filter-value="{{ $example['status_code'] }}">{{ $example['status_code'] }}</button> |
                                            @endif
                                            request_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="request_id" data-filter-value="{{ $example['request_id'] ?? '' }}">{{ $example['request_id'] ?? '-' }}</button> |
                                            event_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="event_id" data-filter-value="{{ $example['event_id'] ?? '' }}">{{ $example['event_id'] ?? '-' }}</button>
                                            @if(!empty($example['message']))
                                                | {{ $example['message'] }}
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <div class="admin-logs-empty-state">未確認の単発重大はありません。</div>
                @endif

                <h2>1. 単発重大トリガー（直近5分）</h2>
                <div class="admin-logs-inline-help">1件でも対応対象です。サンプルのIDクリックで絞り込みできます。</div>
                @foreach($singleCriticalTriggers as $trigger)
                    <div class="admin-ops-trigger-card">
                        <div class="admin-ops-trigger-title">
                            <strong>{{ $trigger['label'] ?? '-' }}</strong>
                            <span class="admin-ops-trigger-count">{{ $trigger['count'] ?? 0 }}件</span>
                            @if(($trigger['is_triggered'] ?? false))
                                <span class="admin-new-inline">単発重大</span>
                                <span class="admin-new-inline">優先調査</span>
                            @endif
                        </div>
                        <div class="admin-muted">判定条件: {{ $trigger['rule_text'] ?? '-' }}</div>
                        <div class="admin-muted">{{ $trigger['description'] ?? '' }}</div>
                        @php $examples = $trigger['examples'] ?? []; @endphp
                        @if(!empty($examples))
                            <div class="admin-logs-log-container">
                                @foreach($examples as $example)
                                    <div class="log-line">
                                        {{ $example['created_at'] ?? '-' }} |
                                        @if(!empty($example['status_code']))
                                            status=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="status_code" data-filter-value="{{ $example['status_code'] }}">{{ $example['status_code'] }}</button> |
                                        @endif
                                        request_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="request_id" data-filter-value="{{ $example['request_id'] ?? '' }}">{{ $example['request_id'] ?? '-' }}</button> |
                                        event_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="event_id" data-filter-value="{{ $example['event_id'] ?? '' }}">{{ $example['event_id'] ?? '-' }}</button>
                                        @if(!empty($example['message']))
                                            | {{ $example['message'] }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                <h2>2. 連発判定トリガー</h2>
                <div class="admin-logs-inline-help">閾値を超えたときに対応対象です（404はここで判定）。</div>
                @foreach($recurrentTriggers as $trigger)
                    <div class="admin-ops-trigger-card">
                        <div class="admin-ops-trigger-title">
                            <strong>{{ $trigger['label'] ?? '-' }}</strong>
                            <span class="admin-ops-trigger-count">{{ $trigger['count'] ?? 0 }}件</span>
                            @if(($trigger['is_triggered'] ?? false))
                                <span class="admin-new-inline">連発判定</span>
                                <span class="admin-new-inline">要確認</span>
                            @endif
                        </div>
                        <div class="admin-muted">判定条件: {{ $trigger['rule_text'] ?? '-' }}</div>
                        <div class="admin-muted">{{ $trigger['description'] ?? '' }}</div>
                        @php $examples = $trigger['examples'] ?? []; @endphp
                        @if(!empty($examples))
                            <div class="admin-logs-log-container">
                                @foreach($examples as $example)
                                    <div class="log-line">
                                        {{ $example['created_at'] ?? '-' }} |
                                        @if(!empty($example['status_code']))
                                            status=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="status_code" data-filter-value="{{ $example['status_code'] }}">{{ $example['status_code'] }}</button> |
                                        @endif
                                        request_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="request_id" data-filter-value="{{ $example['request_id'] ?? '' }}">{{ $example['request_id'] ?? '-' }}</button> |
                                        event_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="event_id" data-filter-value="{{ $example['event_id'] ?? '' }}">{{ $example['event_id'] ?? '-' }}</button>
                                        @if(!empty($example['message']))
                                            | {{ $example['message'] }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="admin-collapsible-card">
                    <button type="button" class="admin-collapsible-toggle" data-target-id="adminFilterPanel" aria-expanded="true">
                        <span>3. 調査フィルタ（相関ID / ステータス）</span>
                        <span class="admin-collapsible-arrow" aria-hidden="true">▼</span>
                    </button>
                    <div id="adminFilterPanel" class="admin-collapsible-panel is-open">
                        <div class="admin-logs-inline-help">ここで絞り込むと、下の相関ログ詳細（サーバーエラー/イベント/アクセス/WAL）を同条件で確認できます。</div>
                        <div class="admin-muted">補足: <code>admin_visit</code> 系はイベントログには出ますが、管理ページのアクセスはアクセスログ対象外のため、アクセスログに出ない場合があります。</div>
                        <div class="admin-logs-actions">
                            <form method="GET" action="{{ route('admin.logs') }}" class="admin-form-inline" id="adminLogsFilterForm">
                                <div class="admin-logs-form-group">
                                    <label for="lines">{{ \App\Services\LanguageService::trans('admin_logs_lines_label', $lang) }}:</label>
                                    <input type="number" name="lines" id="lines" value="{{ request('lines', 1000) }}" min="100" max="10000" step="100">
                                    <label for="search" class="admin-logs-search-label">{{ \App\Services\LanguageService::trans('admin_logs_search', $lang) }}:</label>
                                    <input type="text" name="search" id="search" value="{{ request('search', '') }}" placeholder="{{ \App\Services\LanguageService::trans('admin_logs_search_placeholder', $lang) }}" class="admin-logs-search-input">
                                    <label for="request_id" class="admin-logs-search-label">Request ID:</label>
                                    <input type="text" name="request_id" id="request_id" value="{{ $requestId ?? '' }}" class="admin-logs-search-input" placeholder="uuid">
                                    <label for="event_id" class="admin-logs-search-label">Event ID:</label>
                                    <input type="text" name="event_id" id="event_id" value="{{ $eventId ?? '' }}" class="admin-logs-search-input" placeholder="uuid">
                                    <label for="status_code" class="admin-logs-search-label">Status:</label>
                                    <input type="number" name="status_code" id="status_code" value="{{ $statusCode > 0 ? $statusCode : '' }}" min="100" max="599" style="width: 90px;">
                                    <button type="submit" class="btn btn-secondary">{{ \App\Services\LanguageService::trans('admin_logs_update_button', $lang) }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="admin-collapsible-card">
                    <button type="button" class="admin-collapsible-toggle" data-target-id="adminCorrelationPanel" aria-expanded="true">
                        <span>4. 相関ログ詳細（絞り込み結果）</span>
                        <span class="admin-collapsible-arrow" aria-hidden="true">▼</span>
                    </button>
                    <div id="adminCorrelationPanel" class="admin-collapsible-panel is-open">
                        <div class="admin-collapsible-card">
                            <button type="button" class="admin-collapsible-toggle" data-target-id="adminErrorLogsPanel" aria-expanded="true">
                                <span>サーバーエラー</span><span class="admin-collapsible-arrow" aria-hidden="true">▼</span>
                            </button>
                            <div id="adminErrorLogsPanel" class="admin-collapsible-panel is-open">
                                @if(($errorLogs ?? collect())->count() > 0)
                                    <div class="admin-logs-log-container">
                                        @foreach($errorLogs as $err)
                                            <div class="log-line log-error">{{ $err->created_at }} | status=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="status_code" data-filter-value="{{ $err->status_code ?? '' }}">{{ $err->status_code ?? '-' }}</button> | request_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="request_id" data-filter-value="{{ $err->request_id ?? '' }}">{{ $err->request_id ?? '-' }}</button> | event_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="event_id" data-filter-value="{{ $err->event_id ?? '' }}">{{ $err->event_id ?? '-' }}</button> | source={{ $err->source }} | {{ $err->message }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="admin-logs-empty-state">一致するサーバーエラーはありません</div>
                                @endif
                            </div>
                        </div>
                        <div class="admin-collapsible-card">
                            <button type="button" class="admin-collapsible-toggle" data-target-id="adminEventLogsPanel" aria-expanded="false">
                                <span>イベントログ</span><span class="admin-collapsible-arrow" aria-hidden="true">▼</span>
                            </button>
                            <div id="adminEventLogsPanel" class="admin-collapsible-panel">
                                @if(($eventLogs ?? collect())->count() > 0)
                                    <div class="admin-logs-log-container">
                                        @foreach($eventLogs as $event)
                                            <div class="log-line log-info">{{ $event->created_at }} | event_type={{ $event->event_type }} | request_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="request_id" data-filter-value="{{ $event->request_id ?? '' }}">{{ $event->request_id ?? '-' }}</button> | event_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="event_id" data-filter-value="{{ $event->event_id ?? '' }}">{{ $event->event_id ?? '-' }}</button> | path={{ $event->path ?? '-' }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="admin-logs-empty-state">一致するイベントログはありません</div>
                                @endif
                            </div>
                        </div>
                        <div class="admin-collapsible-card">
                            <button type="button" class="admin-collapsible-toggle" data-target-id="adminAccessLogsPanel" aria-expanded="false">
                                <span>アクセスログ</span><span class="admin-collapsible-arrow" aria-hidden="true">▼</span>
                            </button>
                            <div id="adminAccessLogsPanel" class="admin-collapsible-panel">
                                @if(($accessLogs ?? collect())->count() > 0)
                                    <div class="admin-logs-log-container">
                                        @foreach($accessLogs as $access)
                                            <div class="log-line log-debug">{{ $access->created_at }} | type={{ $access->type }} | status=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="status_code" data-filter-value="{{ $access->status_code ?? '' }}">{{ $access->status_code ?? '-' }}</button> | request_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="request_id" data-filter-value="{{ $access->request_id ?? '' }}">{{ $access->request_id ?? '-' }}</button> | event_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="event_id" data-filter-value="{{ $access->event_id ?? '' }}">{{ $access->event_id ?? '-' }}</button> | {{ $access->method }} {{ $access->path }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="admin-logs-empty-state">一致するアクセスログはありません</div>
                                @endif
                            </div>
                        </div>
                        <div class="admin-collapsible-card">
                            <button type="button" class="admin-collapsible-toggle" data-target-id="adminWalLogsPanel" aria-expanded="false">
                                <span>WAL復元ログ</span><span class="admin-collapsible-arrow" aria-hidden="true">▼</span>
                            </button>
                            <div id="adminWalLogsPanel" class="admin-collapsible-panel">
                                @if(($walLogs ?? collect())->count() > 0)
                                    <div class="admin-logs-log-container">
                                        @foreach($walLogs as $wal)
                                            <div class="log-line log-info">{{ $wal->created_at }} | db={{ $wal->database_driver }} | wal_lsn={{ $wal->wal_lsn ?? '-' }} | txid={{ $wal->transaction_id ?? '-' }} | reason={{ $wal->snapshot_reason }} | request_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="request_id" data-filter-value="{{ $wal->request_id ?? '' }}">{{ $wal->request_id ?? '-' }}</button> | event_id=<button type="button" class="admin-logs-filter-chip js-log-filter" data-filter-type="event_id" data-filter-value="{{ $wal->event_id ?? '' }}">{{ $wal->event_id ?? '-' }}</button></div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="admin-logs-empty-state">WAL復元ログはまだありません</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-collapsible-card">
                    <button type="button" class="admin-collapsible-toggle" data-target-id="adminLogFilePanel" aria-expanded="false">
                        <span>5. {{ \App\Services\LanguageService::trans('admin_logs_file_display', $lang) }}</span>
                        <span class="admin-collapsible-arrow" aria-hidden="true">▼</span>
                    </button>
                    <div id="adminLogFilePanel" class="admin-collapsible-panel">
                        <div class="admin-logs-inline-help">相関ログで特定したIDを使って、必要に応じて生ログまで掘り下げて確認します。</div>
                        <div class="admin-logs-info-bar">
                            <div class="admin-logs-info-item"><span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_file', $lang) }}:</span><span class="admin-logs-info-value">{{ basename($logPath ?? '') }}</span></div>
                            @if($logPath)<div class="admin-logs-info-item"><span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_last_updated', $lang) }}:</span><span class="admin-logs-info-value">{{ date('Y-m-d H:i:s', filemtime($logPath)) }}</span></div>@endif
                            <div class="admin-logs-info-item"><span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_file_size', $lang) }}:</span><span class="admin-logs-info-value">{{ str_replace(':size', number_format($fileSize / 1024, 2), \App\Services\LanguageService::trans('admin_logs_file_size_kb', $lang)) }}</span></div>
                            <div class="admin-logs-info-item"><span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_total_lines', $lang) }}:</span><span class="admin-logs-info-value">{{ $totalLines > 0 ? number_format($totalLines) : \App\Services\LanguageService::trans('admin_logs_calculating', $lang) }}</span></div>
                            <div class="admin-logs-info-item"><span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_display_lines', $lang) }}:</span><span class="admin-logs-info-value">{{ count($lines) }}</span></div>
                        </div>
                        @if($fileSize > 10 * 1024 * 1024)
                            <div class="alert alert-warning">{{ str_replace(':size', number_format($fileSize / 1024 / 1024, 2), \App\Services\LanguageService::trans('admin_logs_large_file_warning', $lang)) }}</div>
                        @endif
                        <div class="admin-logs-actions">
                            <a href="{{ route('admin.logs.download') }}" class="btn btn-primary">{{ \App\Services\LanguageService::trans('admin_logs_download', $lang) }}</a>
                            <form method="POST" action="{{ route('admin.logs.clear') }}" class="admin-form-inline" data-confirm-message="{{ \App\Services\LanguageService::trans('admin_logs_clear_confirm', $lang) }}">
                                @csrf
                                <button type="submit" class="btn btn-danger">{{ \App\Services\LanguageService::trans('admin_logs_clear', $lang) }}</button>
                            </form>
                        </div>
                        @if(count($lines) > 0)
                            <div class="admin-logs-log-container">
                                @foreach($lines as $line)
                                    <div class="log-line @if(strpos($line, 'ERROR') !== false || strpos($line, 'CRITICAL') !== false) log-error @elseif(strpos($line, 'WARNING') !== false || strpos($line, 'ALERT') !== false) log-warning @elseif(strpos($line, 'INFO') !== false) log-info @elseif(strpos($line, 'DEBUG') !== false) log-debug @endif">{{ $line }}</div>
                                @endforeach
                            </div>
                        @else
                            <div class="admin-logs-empty-state">{{ \App\Services\LanguageService::trans('admin_logs_empty', $lang) }}</div>
                        @endif
                    </div>
                </div>
            @else
                <div class="admin-logs-empty-state">
                    <p>{{ \App\Services\LanguageService::trans('admin_logs_not_found', $lang) }}</p>
                    @if(isset($logPath))
                        <p>{{ \App\Services\LanguageService::trans('admin_logs_search_path', $lang) }}: {{ $logPath }}</p>
                    @else
                        <p>{{ \App\Services\LanguageService::trans('admin_logs_search_path', $lang) }}: {{ \App\Services\LanguageService::trans('admin_logs_search_path_default', $lang) }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('js/admin-logs.js') }}?v={{ @filemtime(public_path('js/admin-logs.js')) ?: time() }}" nonce="{{ $csp_nonce ?? '' }}"></script>

