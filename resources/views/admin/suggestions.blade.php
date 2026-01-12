@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_suggestions_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    <p class="admin-back-link"><a href="{{ route('admin.dashboard') }}" class="admin-link">← {{ \App\Services\LanguageService::trans('admin_dashboard_back', $lang) }}</a></p>
    <h1 class="admin-title">{{ \App\Services\LanguageService::trans('admin_suggestions_title', $lang) }} @if(!empty($newSuggestionsCount) && $newSuggestionsCount>0)<span class="admin-new-badge">NEW {{ $newSuggestionsCount }}</span>@endif</h1>
    <form method="get" action="{{ route('admin.suggestions') }}" class="admin-form-margin">
        <label class="admin-label-margin">
            <input type="checkbox" name="show_completed" value="1" {{ !empty($showCompleted) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_show_completed', $lang) }}
        </label>
        <label class="admin-label-margin">
            <input type="checkbox" name="only_starred" value="1" {{ !empty($onlyStarred) ? 'checked' : '' }}>
            {{ \App\Services\LanguageService::trans('admin_only_starred', $lang) }}
        </label>
        <button type="submit">{{ \App\Services\LanguageService::trans('admin_apply', $lang) }}</button>
    </form>
    <table class="admin-table">
        <thead>
            <tr>
                <th>{{ \App\Services\LanguageService::trans('admin_id', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_user_id', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_suggestion_content', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_status', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_star', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_created_at', $lang) }}</th>
                <th>{{ \App\Services\LanguageService::trans('admin_actions', $lang) }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($suggestions as $s)
            <tr>
                <td>{{ $s->id }}</td>
                <td>{{ $s->user_id }}</td>
                <td class="admin-message">{{ $s->message }}</td>
                <td>
                    @if($s->completed === null)
                        {{ \App\Services\LanguageService::trans('admin_pending', $lang) }}
                    @elseif($s->completed)
                        {{ \App\Services\LanguageService::trans('admin_approve', $lang) }}
                    @else
                        {{ \App\Services\LanguageService::trans('admin_reject', $lang) }}
                    @endif
                </td>
                <td>{{ $s->starred ? '★' : '☆' }}</td>
                <td>
                    {{ optional($s->created_at)->format('Y-m-d H:i') }}
                    @if(isset($suggestionsSince) && optional($s->created_at)->gt($suggestionsSince))
                        <span class="admin-new-inline">NEW</span>
                    @endif
                </td>
                <td>
                    @if($s->completed === null)
                        <form method="post" action="{{ route('admin.suggestions.approve', $s) }}" class="admin-form-inline">
                            @csrf
                            <label class="admin-label-margin">
                                {{ \App\Services\LanguageService::trans('admin_coin_amount', $lang) }}:
                                <select name="coin_amount" required class="admin-select-margin">
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5" selected>5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                </select>
                            </label>
                            <button type="submit">{{ \App\Services\LanguageService::trans('admin_approve', $lang) }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.suggestions.reject', $s) }}" class="admin-form-inline admin-button-margin">
                            @csrf
                            <button type="submit">{{ \App\Services\LanguageService::trans('admin_reject', $lang) }}</button>
                        </form>
                    @else
                        <span class="admin-status-processed">{{ \App\Services\LanguageService::trans('admin_processed', $lang) }}</span>
                        @if($s->completed && $s->coin_amount)
                            <span class="admin-status-processed admin-status-processed-margin">({{ \App\Services\LanguageService::trans('admin_coins', $lang) }}: {{ $s->coin_amount }})</span>
                        @endif
                    @endif
                    <form method="post" action="{{ route('admin.suggestions.toggle-star', $s) }}" class="admin-form-inline admin-button-margin">
                        @csrf
                        <button type="submit">{{ $s->starred ? \App\Services\LanguageService::trans('admin_remove_star', $lang) : \App\Services\LanguageService::trans('admin_toggle_star', $lang) }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7">{{ \App\Services\LanguageService::trans('admin_no_suggestions', $lang) }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection


