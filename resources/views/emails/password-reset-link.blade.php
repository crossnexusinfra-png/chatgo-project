<p>{{ \App\Services\LanguageService::trans('password_reset_link_email_intro', $lang) }}</p>
<p><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>
<p>{{ \App\Services\LanguageService::trans('password_reset_link_email_ignore', $lang) }}</p>
