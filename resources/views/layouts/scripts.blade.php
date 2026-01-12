<meta name="common-config" content="{{ json_encode([
    'isAdult' => auth()->check() && auth()->user() ? auth()->user()->isAdult() : false,
    'routes' => [
        'existingReportRoute' => route('reports.existing')
    ],
    'translations' => [
        'r18ThreadAdultOnly' => \App\Services\LanguageService::trans('r18_thread_adult_only', $lang),
        'reportReasonThreadImageCopyright' => \App\Services\LanguageService::trans('report_reason_thread_image_copyright', $lang),
        'reportReasonThreadImagePersonalInfo' => \App\Services\LanguageService::trans('report_reason_thread_image_personal_info', $lang),
        'reportReasonThreadImageInappropriate' => \App\Services\LanguageService::trans('report_reason_thread_image_inappropriate', $lang),
        'reportReasonAdultContent' => \App\Services\LanguageService::trans('report_reason_adult_content', $lang),
        'noFileSelected' => \App\Services\LanguageService::trans('no_file_selected', $lang)
    ]
]) }}">
<script src="{{ asset('js/common-utils.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
<script src="{{ asset('js/common.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
<script nonce="{{ $csp_nonce ?? '' }}">
    // metaタグから設定を読み込む
    (function() {
        const meta = document.querySelector('meta[name="common-config"]');
        if (meta) {
            try {
                window.commonConfig = JSON.parse(meta.getAttribute('content'));
            } catch (e) {
                console.error('Failed to parse common-config:', e);
                window.commonConfig = {};
            }
        }
    })();
</script>
