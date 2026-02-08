@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ str_replace(':id', $threadId, \App\Services\LanguageService::trans('admin_reports_detail_thread', $lang)) }}
@endsection

@section('content')
<div class="admin-page">
    <p class="admin-reports-back-link"><a href="{{ route('admin.reports', ['show_approved' => request('show_approved'), 'only_flagged' => request('only_flagged')]) }}" class="admin-link">← {{ \App\Services\LanguageService::trans('admin_reports_back', $lang) }}</a></p>
    <h1 class="admin-title">{{ str_replace(':id', $threadId, \App\Services\LanguageService::trans('admin_reports_detail_thread', $lang)) }}</h1>

    <form method="get" action="{{ route('admin.reports.thread', $threadId) }}" class="admin-form-margin">
        <label class="admin-label-margin">
            <input type="checkbox" name="show_approved" value="1" {{ !empty($showApproved) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_reports_show_approved', $lang) }}
        </label>
        <label class="admin-label-margin">
            <input type="checkbox" name="only_flagged" value="1" {{ !empty($onlyFlagged) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_only_starred', $lang) }}
        </label>
        <button type="submit">{{ \App\Services\LanguageService::trans('admin_apply', $lang) }}</button>
    </form>

    <div class="admin-reports-actions-container">
        <form method="post" action="{{ route('admin.reports.thread.approve', $threadId) }}" class="admin-form-inline">
            @csrf
            <button type="submit">{{ \App\Services\LanguageService::trans('admin_reports_approve', $lang) }}</button>
        </form>
        <form method="post" action="{{ route('admin.reports.thread.reject', $threadId) }}" class="admin-form-inline admin-button-margin">
            @csrf
            <button type="submit">{{ \App\Services\LanguageService::trans('admin_reports_reject', $lang) }}</button>
        </form>
        <form method="post" action="{{ route('admin.reports.thread.toggle-flag', $threadId) }}" class="admin-form-inline admin-button-margin">
            @csrf
            <button type="submit">{{ !empty($groupFlagged) ? \App\Services\LanguageService::trans('admin_remove_star', $lang) : \App\Services\LanguageService::trans('admin_toggle_star', $lang) }}</button>
        </form>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_user_id', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_reason', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_detail', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_created_at_label', $lang) }}</th>
                <th>アウト数</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($reports as $r)
            <tr>
                <td>{{ $r->user_id }}</td>
                <td>{{ $r->reason }}</td>
                <td class="admin-message">{{ $r->description }}</td>
                <td>{{ optional($r->created_at)->format('Y-m-d H:i') }}</td>
                <td>
                    @if($r->is_approved && $r->approved_at)
                        <form method="post" action="{{ route('admin.reports.set-out-count', $r->report_id) }}" class="admin-form-inline">
                            @csrf
                            <input type="number" name="out_count" value="{{ $r->out_count ?? \App\Models\Report::getDefaultOutCount($r->reason) }}" step="0.5" min="0.5" max="3.0" style="width: 80px;">
                            <button type="submit" style="padding: 2px 8px; font-size: 12px;">設定</button>
                        </form>
                    @else
                        <span style="color: #999;">未承認</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4">{{ \App\Services\LanguageService::trans('admin_reports_no_matching', $lang) }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @if($thread)
    <h2 class="admin-reports-section-title">{{ \App\Services\LanguageService::trans('admin_reports_thread_content', $lang) }}</h2>
    <div class="admin-reports-content-card">
        <div class="admin-reports-content-title">{{ $thread->title }}</div>
        <div class="admin-muted admin-reports-content-meta">{{ \App\Services\LanguageService::trans('admin_reports_created_at', $lang) }}: {{ optional($thread->created_at)->format('Y-m-d H:i') }} / {{ \App\Services\LanguageService::trans('admin_reports_author', $lang) }}: {{ $thread->user ? $thread->user->username : '削除されたユーザー' }}</div>
        @php
            $threadImage = $thread->image_path ?: asset('images/default-16x9.svg');
            // Storage::disk('public')->url()を使用してURLを取得（image_pathがstorageパスの場合、S3対応）
            if ($thread->image_path && strpos($thread->image_path, 'thread_images/') === 0) {
                $threadImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thread->image_path);
            } else {
                $threadImageUrl = $threadImage;
            }
        @endphp
        @if($thread->image_path)
        <div class="admin-reports-image-section">
            <h3 class="admin-reports-image-title">{{ \App\Services\LanguageService::trans('admin_reports_thread_image', $lang) }}</h3>
            <div class="admin-reports-image-container">
                <img src="{{ $threadImageUrl }}" alt="{{ $thread->title }}" class="admin-reports-image">
            </div>
        </div>
        @endif
    </div>
    <h3>{{ \App\Services\LanguageService::trans('admin_reports_responses_list', $lang) }}</h3>
    <div class="admin-reports-responses-container">
        @forelse($thread->responses as $res)
            <div data-response-id="{{ $res->response_id }}" class="admin-reports-response-item">
                <div class="admin-reports-response-meta">
                    <div>{{ \App\Services\LanguageService::trans('admin_reports_response_id_label', $lang) }}: {{ $res->response_id }} / {{ \App\Services\LanguageService::trans('admin_reports_user_label', $lang) }}: {{ $res->user_name }}</div>
                    <div>{{ optional($res->created_at)->format('Y-m-d H:i') }}</div>
                </div>
                <div class="admin-message">{{ $res->body }}</div>
            </div>
        @empty
            <div class="admin-muted admin-reports-no-responses">{{ \App\Services\LanguageService::trans('admin_reports_no_responses', $lang) }}</div>
        @endforelse
    </div>
    @endif
</div>
@endsection


