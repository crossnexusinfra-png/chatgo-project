@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_freeze_appeals_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <p class="admin-back-link"><a href="{{ route('admin.dashboard') }}" class="admin-link">← {{ \App\Services\LanguageService::trans('admin_dashboard_back', $lang) }}</a></p>
    <h1 class="admin-title">{{ \App\Services\LanguageService::trans('admin_freeze_appeals_title', $lang) }} @if(!empty($newAppealsCount) && $newAppealsCount > 0)<span class="admin-new-badge">NEW {{ $newAppealsCount }}</span>@endif</h1>
    <form method="get" action="{{ route('admin.freeze-appeals') }}" class="admin-form-margin">
        <label class="admin-label-margin">
            {{ \App\Services\LanguageService::trans('admin_sort_order', $lang) }}:
            <select name="sort" class="admin-select-margin">
                <option value="latest" {{ (($sort ?? 'latest') === 'latest') ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_sort_latest', $lang) }}</option>
                <option value="oldest" {{ (($sort ?? '') === 'oldest') ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_sort_oldest', $lang) }}</option>
            </select>
        </label>
        <label class="admin-label-margin">
            <input type="checkbox" name="show_approved" value="1" {{ !empty($showApproved) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_reports_show_approved', $lang) }}
        </label>
        <label class="admin-label-margin">
            <input type="checkbox" name="show_rejected" value="1" {{ !empty($showRejected) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_reports_show_rejected', $lang) }}
        </label>
        <button type="submit">{{ \App\Services\LanguageService::trans('admin_apply', $lang) }}</button>
    </form>
    <table class="admin-table">
        <thead>
            <tr>
                <th>{{ \App\Services\LanguageService::trans('admin_id', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_user_id', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_freeze_appeal_content', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_freeze_appeal_out_count', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_freeze_appeal_freeze_period', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_status', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_created_at', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_actions', $lang) }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($appeals as $a)
            <tr>
                <td>{{ $a->freeze_appeal_id }}</td>
                <td>
                    @php $copyToken = optional($a->user)->user_identifier ?? (string) $a->user_id; @endphp
                    <code class="admin-copy-token">{{ $copyToken }}</code>
                    <button type="button" class="admin-copy-btn" data-copy-text="{{ $copyToken }}" data-copied-label="{{ \App\Services\LanguageService::trans('copied', $lang) }}">{{ \App\Services\LanguageService::trans('copy', $lang) }}</button>
                </td>
                <td class="admin-message">{{ $a->message }}</td>
                <td>{{ $a->out_count_snapshot }}</td>
                <td>
                    @if($a->is_permanent_snapshot)
                        {{ \App\Services\LanguageService::trans('admin_freeze_appeal_permanent', $lang) }}
                    @elseif($a->frozen_until_snapshot)
                        {{ \App\Services\LanguageService::trans('admin_freeze_appeal_until', $lang, ['until' => $a->frozen_until_snapshot->format('Y-m-d H:i')]) }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if($a->status === 'pending')
                        {{ \App\Services\LanguageService::trans('admin_freeze_appeal_status_pending', $lang) }}
                    @elseif($a->status === 'approved')
                        {{ \App\Services\LanguageService::trans('admin_freeze_appeal_status_approved', $lang) }}
                    @else
                        {{ \App\Services\LanguageService::trans('admin_freeze_appeal_status_rejected', $lang) }}
                    @endif
                </td>
                <td>
                    {{ optional($a->created_at)->format('Y-m-d H:i') }}
                    @if(isset($appealsSince) && optional($a->created_at)->gt($appealsSince) && $a->status === 'pending')
                        <span class="admin-new-inline">NEW</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.freeze-appeals.user-reports', ['userId' => $a->user_id]) }}" class="admin-link admin-button-margin">{{ \App\Services\LanguageService::trans('admin_freeze_appeal_detail_link', $lang) }}</a>
                    @if($a->status === 'pending')
                        @php
                            $targetUser = $a->user;
                            $maxOut = $targetUser ? max(0.25, round($targetUser->calculateOutCount(), 2)) : 0.25;
                        @endphp
                        <form method="post" action="{{ route('admin.freeze-appeals.approve', $a) }}" class="admin-form-inline" data-confirm-message="{{ \App\Services\LanguageService::trans('admin_freeze_appeal_approve_confirm', $lang) }}">
                            @csrf
                            <label class="admin-label-margin">
                                {{ \App\Services\LanguageService::trans('admin_freeze_appeal_out_reduce', $lang) }}:
                                <input type="number" name="out_count_reduced" required class="admin-select-margin" min="0.25" max="{{ $maxOut }}" step="0.25" value="{{ min(1, $maxOut) }}">
                            </label>
                            <button type="submit">{{ \App\Services\LanguageService::trans('admin_approve', $lang) }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.freeze-appeals.reject', $a) }}" class="admin-form-inline admin-button-margin" data-confirm-message="{{ \App\Services\LanguageService::trans('admin_freeze_appeal_reject_confirm', $lang) }}">
                            @csrf
                            <button type="submit">{{ \App\Services\LanguageService::trans('admin_reject', $lang) }}</button>
                        </form>
                    @else
                        <span class="admin-status-processed">{{ \App\Services\LanguageService::trans('admin_processed', $lang) }}</span>
                        @if($a->status === 'approved' && $a->out_count_reduced !== null)
                            <span class="admin-status-processed admin-status-processed-margin">({{ \App\Services\LanguageService::trans('admin_freeze_appeal_out_reduce', $lang) }}: {{ $a->out_count_reduced }})</span>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8">{{ \App\Services\LanguageService::trans('admin_freeze_appeal_no_items', $lang) }}</td></tr>
        @endforelse
        </tbody>
    </table>
    @if(method_exists($appeals, 'hasPages') && $appeals->hasPages())
        <div class="admin-messages-submit-container">
            @if($appeals->onFirstPage())
                <span class="admin-link">←</span>
            @else
                <a class="admin-link" href="{{ $appeals->previousPageUrl() }}">←</a>
            @endif
            <span> {{ $appeals->currentPage() }} / {{ $appeals->lastPage() }} </span>
            @if($appeals->hasMorePages())
                <a class="admin-link" href="{{ $appeals->nextPageUrl() }}">→</a>
            @else
                <span class="admin-link">→</span>
            @endif
        </div>
    @endif
</div>
<script src="{{ asset('js/admin-copy-btn.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endsection
