<meta name="common-config" content="{{ json_encode([
    'isAdult' => auth()->check() && auth()->user() ? auth()->user()->isAdult() : false,
    'canUseMediaPosts' => auth()->check() && auth()->user() ? !auth()->user()->requiresPhoneVerificationRestrictions() : true,
    'routes' => [
        'existingReportRoute' => route('api.reports.existing')
    ],
    'translations' => [
        'r18ThreadAdultOnly' => \App\Services\LanguageService::trans('r18_thread_adult_only', $lang),
        'reportReasonSpam' => \App\Services\LanguageService::trans('report_reason_spam', $lang),
        'reportReasonOffensive' => \App\Services\LanguageService::trans('report_reason_offensive', $lang),
        'reportReasonInappropriateLink' => \App\Services\LanguageService::trans('report_reason_inappropriate_link', $lang),
        'reportReasonContentViolation' => \App\Services\LanguageService::trans('report_reason_content_violation', $lang),
        'reportReasonOpinionImposition' => \App\Services\LanguageService::trans('report_reason_opinion_imposition', $lang),
        'reportReasonImpersonation' => \App\Services\LanguageService::trans('report_reason_impersonation', $lang),
        'reportReasonThreadImageCopyright' => \App\Services\LanguageService::trans('report_reason_thread_image_copyright', $lang),
        'reportReasonThreadImagePersonalInfo' => \App\Services\LanguageService::trans('report_reason_thread_image_personal_info', $lang),
        'reportReasonThreadImageInappropriate' => \App\Services\LanguageService::trans('report_reason_thread_image_inappropriate', $lang),
        'reportReasonAdultContent' => \App\Services\LanguageService::trans('report_reason_adult_content', $lang),
        'other' => \App\Services\LanguageService::trans('other', $lang),
        'noFileSelected' => \App\Services\LanguageService::trans('no_file_selected', $lang),
        'mediaPostPhoneVerificationRequired' => \App\Services\LanguageService::trans('media_post_phone_verification_required', $lang),
        'submitting' => \App\Services\LanguageService::trans('submitting', $lang),
        'confirmReportSubmit' => \App\Services\LanguageService::trans('confirm_report_submit', $lang),
        'confirmCreateThreadSubmit' => \App\Services\LanguageService::trans('confirm_create_thread_submit', $lang),
        'creating_room' => \App\Services\LanguageService::trans('creating_room', $lang),
        'sending_request' => \App\Services\LanguageService::trans('sending_request', $lang),
        'deleting' => \App\Services\LanguageService::trans('deleting', $lang),
        'processing' => \App\Services\LanguageService::trans('processing', $lang)
    ]
]) }}">
<script src="{{ asset('js/common-utils.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@php
    $gamInterstitialPath = null;
    if (!empty($gamPageEnabled) && empty($exoclickPageEnabled)) {
        $gamInterstitialPath = app(\App\Services\AdNetworkService::class)->gamSlotPath('interstitial');
    }
@endphp
@if($gamInterstitialPath)
<div id="gpt-interstitial"
     data-gpt-slot
     data-gpt-path="{{ e($gamInterstitialPath) }}"
     data-gpt-size="interstitial"
     hidden></div>
@endif
@if(!empty($gamRewardedPath))
<div id="gpt-rewarded"
     data-gpt-slot
     data-gpt-path="{{ e($gamRewardedPath) }}"
     data-gpt-size="rewarded"
     hidden></div>
@endif
@if(!empty($gamPageEnabled) && empty($exoclickPageEnabled))
<script src="{{ asset('js/gpt-init.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endif
@if(!empty($exoclickPageEnabled) && empty($gamPageEnabled))
<script src="{{ asset('js/exoclick-init.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endif
<script src="{{ asset('js/common.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
