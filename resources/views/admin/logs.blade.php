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
            
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            
            @if($fileExists)
                <hr>
                <h2>相関調査ログ（簡易表示）</h2>

                <h3>サーバーエラー</h3>
                @if(($errorLogs ?? collect())->count() > 0)
                    <div class="admin-logs-log-container">
                        @foreach($errorLogs as $err)
                            <div class="log-line log-error">
                                {{ $err->created_at }} | status={{ $err->status_code ?? '-' }} | request_id={{ $err->request_id ?? '-' }} | event_id={{ $err->event_id ?? '-' }} | source={{ $err->source }} | {{ $err->message }}
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="admin-logs-empty-state">一致するサーバーエラーはありません</div>
                @endif

                <h3>イベントログ</h3>
                @if(($eventLogs ?? collect())->count() > 0)
                    <div class="admin-logs-log-container">
                        @foreach($eventLogs as $event)
                            <div class="log-line log-info">
                                {{ $event->created_at }} | event_type={{ $event->event_type }} | request_id={{ $event->request_id ?? '-' }} | event_id={{ $event->event_id }} | path={{ $event->path ?? '-' }}
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="admin-logs-empty-state">一致するイベントログはありません</div>
                @endif

                <h3>アクセスログ</h3>
                @if(($accessLogs ?? collect())->count() > 0)
                    <div class="admin-logs-log-container">
                        @foreach($accessLogs as $access)
                            <div class="log-line log-debug">
                                {{ $access->created_at }} | type={{ $access->type }} | status={{ $access->status_code ?? '-' }} | request_id={{ $access->request_id ?? '-' }} | event_id={{ $access->event_id ?? '-' }} | {{ $access->method }} {{ $access->path }}
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="admin-logs-empty-state">一致するアクセスログはありません</div>
                @endif

                <div class="admin-collapsible-card">
                    <button type="button" class="admin-collapsible-toggle" data-target-id="adminWalLogsPanel" aria-expanded="false">
                        <span>WAL復元ログ</span>
                        <span class="admin-collapsible-arrow" aria-hidden="true">▼</span>
                    </button>
                    <div id="adminWalLogsPanel" class="admin-collapsible-panel">
                        @if(($walLogs ?? collect())->count() > 0)
                            <div class="admin-logs-log-container">
                                @foreach($walLogs as $wal)
                                    <div class="log-line log-info">
                                        {{ $wal->created_at }} | db={{ $wal->database_driver }} | wal_lsn={{ $wal->wal_lsn ?? '-' }} | txid={{ $wal->transaction_id ?? '-' }} | reason={{ $wal->snapshot_reason }} | event_id={{ $wal->event_id }}
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="admin-logs-empty-state">WAL復元ログはまだありません</div>
                        @endif
                    </div>
                </div>

                <div class="admin-collapsible-card">
                    <button type="button" class="admin-collapsible-toggle" data-target-id="adminLogFilePanel" aria-expanded="false">
                        <span>{{ \App\Services\LanguageService::trans('admin_logs_file_display', $lang) }}</span>
                        <span class="admin-collapsible-arrow" aria-hidden="true">▼</span>
                    </button>
                    <div id="adminLogFilePanel" class="admin-collapsible-panel">
                        <div class="admin-logs-info-bar">
                            <div class="admin-logs-info-item">
                                <span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_file', $lang) }}:</span>
                                <span class="admin-logs-info-value">{{ basename($logPath ?? '') }}</span>
                            </div>
                            @if($logPath)
                                <div class="admin-logs-info-item">
                                    <span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_last_updated', $lang) }}:</span>
                                    <span class="admin-logs-info-value">{{ date('Y-m-d H:i:s', filemtime($logPath)) }}</span>
                                </div>
                            @endif
                            <div class="admin-logs-info-item">
                                <span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_file_size', $lang) }}:</span>
                                <span class="admin-logs-info-value">{{ str_replace(':size', number_format($fileSize / 1024, 2), \App\Services\LanguageService::trans('admin_logs_file_size_kb', $lang)) }}</span>
                            </div>
                            <div class="admin-logs-info-item">
                                <span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_total_lines', $lang) }}:</span>
                                <span class="admin-logs-info-value">{{ $totalLines > 0 ? number_format($totalLines) : \App\Services\LanguageService::trans('admin_logs_calculating', $lang) }}</span>
                            </div>
                            <div class="admin-logs-info-item">
                                <span class="admin-logs-info-label">{{ \App\Services\LanguageService::trans('admin_logs_display_lines', $lang) }}:</span>
                                <span class="admin-logs-info-value">{{ count($lines) }}</span>
                            </div>
                        </div>

                        @if($fileSize > 10 * 1024 * 1024)
                            <div class="alert alert-warning">
                                {{ str_replace(':size', number_format($fileSize / 1024 / 1024, 2), \App\Services\LanguageService::trans('admin_logs_large_file_warning', $lang)) }}
                            </div>
                        @endif

                        <div class="admin-logs-actions">
                            <form method="GET" action="{{ route('admin.logs') }}" class="admin-form-inline">
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
                            
                            <a href="{{ route('admin.logs.download') }}" class="btn btn-primary">{{ \App\Services\LanguageService::trans('admin_logs_download', $lang) }}</a>
                            
                            <form method="POST" action="{{ route('admin.logs.clear') }}" class="admin-form-inline" data-confirm-message="{{ \App\Services\LanguageService::trans('admin_logs_clear_confirm', $lang) }}">
                                @csrf
                                <button type="submit" class="btn btn-danger">{{ \App\Services\LanguageService::trans('admin_logs_clear', $lang) }}</button>
                            </form>
                        </div>
                        
                        @if(count($lines) > 0)
                            <div class="admin-logs-log-container">
                                @foreach($lines as $line)
                                    <div class="log-line 
                                        @if(strpos($line, 'ERROR') !== false || strpos($line, 'CRITICAL') !== false) log-error
                                        @elseif(strpos($line, 'WARNING') !== false || strpos($line, 'ALERT') !== false) log-warning
                                        @elseif(strpos($line, 'INFO') !== false) log-info
                                        @elseif(strpos($line, 'DEBUG') !== false) log-debug
                                        @endif
                                    ">{{ $line }}</div>
                                @endforeach
                            </div>
                        @else
                            <div class="admin-logs-empty-state">
                                {{ \App\Services\LanguageService::trans('admin_logs_empty', $lang) }}
                            </div>
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

