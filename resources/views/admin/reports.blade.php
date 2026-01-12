@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_reports_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    <p class="admin-reports-back-link"><a href="{{ route('admin.dashboard') }}" class="admin-link">← {{ \App\Services\LanguageService::trans('admin_dashboard_back', $lang) }}</a></p>
    <h1 class="admin-title">{{ \App\Services\LanguageService::trans('admin_reports_title', $lang) }} @if(!empty($newReportsCount) && $newReportsCount>0)<span class="admin-new-badge">NEW {{ $newReportsCount }}</span>@endif</h1>
    <form method="get" action="{{ route('admin.reports') }}" class="admin-form-margin">
        <label class="admin-label-margin">
            <input type="checkbox" name="show_approved" value="1" {{ !empty($showApproved) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_reports_show_approved', $lang) }}
        </label>
        <label class="admin-label-margin">
            <input type="checkbox" name="show_rejected" value="1" {{ !empty($showRejected) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_reports_show_rejected', $lang) }}
        </label>
        <label class="admin-label-margin">
            <input type="checkbox" name="only_flagged" value="1" {{ !empty($onlyFlagged) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_only_starred', $lang) }}
        </label>
        <label class="admin-label-margin">
            <input type="checkbox" name="only_re_reported" value="1" {{ !empty($onlyReReported) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_reports_only_re_reported', $lang) }}
        </label>
        <button type="submit">{{ \App\Services\LanguageService::trans('admin_apply', $lang) }}</button>
    </form>

    <table class="admin-table">
        <thead>
            <tr>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_target', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_count', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_first', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_last', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_status', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_reports_actions', $lang) }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($groups as $g)
            <tr>
                <td>
                    @if ($g->thread_id)
                        {!! $g->any_flagged ? '★' : '☆' !!} {{ \App\Services\LanguageService::trans('admin_reports_thread_id', $lang) }}: {{ $g->thread_id }}
                        @if(isset($g->has_previous_rejection) && $g->has_previous_rejection)
                            {{ \App\Services\LanguageService::trans('admin_reports_re_reported', $lang) }}
                        @endif
                        <div><a href="{{ route('admin.reports.thread', ['threadId' => $g->thread_id, 'show_approved' => request('show_approved'), 'show_rejected' => request('show_rejected'), 'only_flagged' => request('only_flagged'), 'only_re_reported' => request('only_re_reported')]) }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_reports_view_content', $lang) }}</a></div>
                    @elseif ($g->response_id)
                        {!! $g->any_flagged ? '★' : '☆' !!} {{ \App\Services\LanguageService::trans('admin_reports_response_id', $lang) }}: {{ $g->response_id }}
                        @if(isset($g->has_previous_rejection) && $g->has_previous_rejection)
                            {{ \App\Services\LanguageService::trans('admin_reports_re_reported', $lang) }}
                        @endif
                        <div><a href="{{ route('admin.reports.response', ['responseId' => $g->response_id, 'show_approved' => request('show_approved'), 'show_rejected' => request('show_rejected'), 'only_flagged' => request('only_flagged'), 'only_re_reported' => request('only_re_reported')]) }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_reports_view_content', $lang) }}</a></div>
                    @else
                        {{ \App\Services\LanguageService::trans('admin_reports_unknown', $lang) }}
                    @endif
                </td>
                <td>{{ $g->reports_count }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($g->first_reported_at)->format('Y-m-d H:i') }}</td>
                <td>
                    {{ \Illuminate\Support\Carbon::parse($g->last_reported_at)->format('Y-m-d H:i') }}
                    @if(isset($reportsSince) && \Illuminate\Support\Carbon::parse($g->last_reported_at)->gt($reportsSince))
                        <span class="admin-new-inline">NEW</span>
                    @endif
                </td>
                <td>
                    @if ($g->any_approved && !$g->any_rejected)
                        {{ \App\Services\LanguageService::trans('admin_reports_approved', $lang) }}
                    @elseif ($g->any_rejected && !$g->any_approved)
                        {{ \App\Services\LanguageService::trans('admin_reports_rejected', $lang) }}
                    @elseif ($g->any_rejected && $g->any_approved)
                        {{ \App\Services\LanguageService::trans('admin_reports_mixed', $lang) }}
                    @else
                        {{ \App\Services\LanguageService::trans('admin_reports_pending', $lang) }}
                    @endif
                </td>
                <td>
                    @if ($g->thread_id)
                        <form method="post" action="{{ route('admin.reports.thread.approve', $g->thread_id) }}" class="admin-form-inline">
                            @csrf
                            <button type="submit">{{ \App\Services\LanguageService::trans('admin_reports_approve', $lang) }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.reports.thread.reject', $g->thread_id) }}" class="admin-form-inline admin-button-margin">
                            @csrf
                            <button type="submit">{{ \App\Services\LanguageService::trans('admin_reports_reject', $lang) }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.reports.thread.toggle-flag', $g->thread_id) }}" class="admin-form-inline admin-button-margin">
                            @csrf
                            <button type="submit">{{ $g->any_flagged ? \App\Services\LanguageService::trans('admin_remove_star', $lang) : \App\Services\LanguageService::trans('admin_toggle_star', $lang) }}</button>
                        </form>
                    @elseif ($g->response_id)
                        <form method="post" action="{{ route('admin.reports.response.approve', $g->response_id) }}" class="admin-form-inline">
                            @csrf
                            <button type="submit">{{ \App\Services\LanguageService::trans('admin_reports_approve', $lang) }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.reports.response.reject', $g->response_id) }}" class="admin-form-inline admin-button-margin">
                            @csrf
                            <button type="submit">{{ \App\Services\LanguageService::trans('admin_reports_reject', $lang) }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.reports.response.toggle-flag', $g->response_id) }}" class="admin-form-inline admin-button-margin">
                            @csrf
                            <button type="submit">{{ $g->any_flagged ? \App\Services\LanguageService::trans('admin_remove_star', $lang) : \App\Services\LanguageService::trans('admin_toggle_star', $lang) }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6">{{ \App\Services\LanguageService::trans('admin_reports_no_reports', $lang) }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection


