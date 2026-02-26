@forelse($messages as $m)
    @php
        $isUnread = !($m->is_read ?? false);
    @endphp
    <article 
        class="notification-item" 
        data-message-id="{{ $m->id }}"
        data-is-read="{{ $isUnread ? 'false' : 'true' }}"
        data-unlimited-reply="{{ ($m->unlimited_reply ?? false) ? 'true' : 'false' }}"
    >
        <div class="notification-header">
            @if($isUnread)
                <span class="unread-mark"></span>
            @endif
            <div class="notification-content">
                <h2 class="notification-title">
                    {{ $m->translated_title ?? $m->title ?? \App\Services\LanguageService::trans('notification_no_title', $lang) }}
                    @if($m->coin_amount && $m->coin_amount > 0)
                        <span class="coin-badge">{{ str_replace('{amount}', $m->coin_amount, \App\Services\LanguageService::trans('notification_coin_badge', $lang)) }}</span>
                    @endif
                </h2>
                <div class="notification-date">
                    @php
                        $dateTime = optional($m->published_at ?? $m->created_at);
                    @endphp
                    @if($dateTime)
                        <span data-utc-datetime="{{ $dateTime->format('Y-m-d H:i:s') }}" data-format="en">{{ $dateTime->format('Y-m-d H:i') }}</span>
                    @endif
                </div>
            </div>
            <span class="toggle-icon">▼</span>
        </div>
        <div class="message-body"></div>
        @if($m->coin_amount && $m->coin_amount > 0 && auth()->check())
            <div class="coin-reward-section" data-message-id="{{ $m->id }}">
                @if($m->has_received_coin ?? false)
                    <div class="coin-received-message">{{ \App\Services\LanguageService::trans('notification_coin_already_received', $lang) }}</div>
                @else
                    <button type="button" class="coin-receive-btn" data-message-id="{{ $m->id }}" data-coin-amount="{{ $m->coin_amount }}">
                        {{ \App\Services\LanguageService::trans('notification_receive_coin', $lang) }}
                    </button>
                @endif
            </div>
        @endif
        @if(isset($m->title_key) && $m->title_key === 'r18_change_request_title' && $m->thread_id && !($m->reply_used ?? false))
            <div class="r18-change-section" data-message-id="{{ $m->id }}">
                <div class="r18-change-buttons">
                    <button type="button" class="r18-approve-btn" data-message-id="{{ $m->id }}">
                        {{ \App\Services\LanguageService::trans('r18_change_approve_button', $lang) }}
                    </button>
                    <button type="button" class="r18-reject-btn" data-message-id="{{ $m->id }}">
                        {{ \App\Services\LanguageService::trans('r18_change_reject_button', $lang) }}
                    </button>
                </div>
            </div>
        @elseif(isset($m->allows_reply) && $m->allows_reply && (($m->unlimited_reply ?? false) || !($m->reply_used ?? false)))
            <div class="reply-section" data-message-id="{{ $m->id }}">
                <form class="reply-form" data-message-id="{{ $m->id }}">
                    @csrf
                    <div class="js-notification-reply-fields">
                    <textarea name="reply_body" rows="3" maxlength="2000" placeholder="{{ \App\Services\LanguageService::trans('reply_placeholder', $lang) }}" class="reply-textarea js-notification-reply-body" required></textarea>
                    </div>
                    <div class="reply-submit-container">
                        <button type="submit" class="reply-submit-btn">{{ \App\Services\LanguageService::trans('reply_submit', $lang) }}</button>
                    </div>
                </form>
            </div>
        @endif
    </article>
@empty
    <p class="notification-empty">{{ \App\Services\LanguageService::trans('notifications_empty', $lang) }}</p>
@endforelse

