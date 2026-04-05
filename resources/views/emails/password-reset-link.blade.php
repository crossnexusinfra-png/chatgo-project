@if($lang === 'ja')
<p>パスワード再設定のリクエストを受け付けました。以下のリンクから新しいパスワードを設定してください。</p>
<p><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>
<p>心当たりがない場合はこのメールを無視してください。</p>
@else
<p>We received a request to reset your password. Use the link below to set a new password.</p>
<p><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>
<p>If you did not request this, you can ignore this email.</p>
@endif
