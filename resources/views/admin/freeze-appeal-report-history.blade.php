@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_freeze_appeal_report_history_title', $lang, ['id' => $target->user_id]) }}
@endsection

@section('content')
<div class="admin-page">
    <p class="admin-back-link"><a href="{{ route('admin.freeze-appeals') }}" class="admin-link">← {{ \App\Services\LanguageService::trans('admin_freeze_appeals_title', $lang) }}</a></p>
    <h1 class="admin-title">{{ \App\Services\LanguageService::trans('admin_freeze_appeal_report_history_title', $lang, ['id' => $target->user_id]) }}</h1>
    <p>{{ $target->username ?? ('user #' . $target->user_id) }}</p>
    <table class="admin-table">
        <thead>
            <tr>
                <th>{{ \App\Services\LanguageService::trans('admin_id', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_freeze_appeal_report_type_thread', $lang) }} / {{ \App\Services\LanguageService::trans('admin_freeze_appeal_report_target', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_freeze_appeal_report_reason', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_freeze_appeal_report_description', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_status', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_freeze_appeal_report_out', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_freeze_appeal_report_approved_at', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_created_at', $lang) }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($reports as $r)
            @php
                if ($r->thread_id) {
                    $typeLabel = \App\Services\LanguageService::trans('admin_freeze_appeal_report_type_thread', $lang);
                    $targetLabel = optional($r->thread)->title ?? ('thread #' . $r->thread_id);
                } elseif ($r->response_id) {
                    $typeLabel = \App\Services\LanguageService::trans('admin_freeze_appeal_report_type_response', $lang);
                    $body = strip_tags((string) optional($r->response)->body);
                    $targetLabel = \Illuminate\Support\Str::limit($body, 80);
                } else {
                    $typeLabel = \App\Services\LanguageService::trans('admin_freeze_appeal_report_type_profile', $lang);
                    $targetLabel = $target->username ?? ('user #' . $target->user_id);
                }
            @endphp
            <tr>
                <td>{{ $r->report_id }}</td>
                <td>{{ $typeLabel }}: {{ $targetLabel }}</td>
                <td>{{ $r->reason }}</td>
                <td class="admin-message">{{ $r->description }}</td>
                <td>
                    @if($r->approved_at === null)
                        {{ \App\Services\LanguageService::trans('admin_reports_pending', $lang) }}
                    @elseif($r->is_approved)
                        {{ \App\Services\LanguageService::trans('admin_reports_approved', $lang) }}
                    @else
                        {{ \App\Services\LanguageService::trans('admin_reports_rejected', $lang) }}
                    @endif
                </td>
                <td>{{ $r->out_count }}</td>
                <td>{{ optional($r->approved_at)->format('Y-m-d H:i') ?? '—' }}</td>
                <td>{{ optional($r->created_at)->format('Y-m-d H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="8">{{ \App\Services\LanguageService::trans('admin_reports_no_reports', $lang) }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
