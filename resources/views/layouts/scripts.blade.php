<meta name="common-config" content="{{ json_encode([
    'isAdult' => auth()->check() && auth()->user() ? auth()->user()->isAdult() : false,
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
        'submitting' => \App\Services\LanguageService::trans('submitting', $lang),
        'confirmReportSubmit' => \App\Services\LanguageService::trans('confirm_report_submit', $lang),
        'confirmCreateThreadSubmit' => \App\Services\LanguageService::trans('confirm_create_thread_submit', $lang),
        'creating_room' => \App\Services\LanguageService::trans('creating_room', $lang),
        'sending_request' => \App\Services\LanguageService::trans('sending_request', $lang),
        'deleting' => \App\Services\LanguageService::trans('deleting', $lang),
        'processing' => \App\Services\LanguageService::trans('processing', $lang)
    ]
]) }}">
<script nonce="{{ $csp_nonce ?? '' }}">
    // common.js の前に commonConfig を読み込む（通報理由ドロップダウン等で使用）
    (function() {
        const meta = document.querySelector('meta[name="common-config"]');
        if (meta) {
            try {
                window.commonConfig = JSON.parse(meta.getAttribute('content'));
            } catch (e) {
                console.error('Failed to parse common-config:', e);
                window.commonConfig = {};
            }
        } else {
            window.commonConfig = {};
        }
    })();
</script>
<script src="{{ asset('js/common-utils.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
<script src="{{ asset('js/common.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
