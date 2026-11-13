<template id="list-row-ad-template">
    <div class="posts-grid-ad-row main-page-ad-slot post-list-margin">
        @include('components.adsense-inline-banner', [
            'instanceId' => 'list-row-ad',
            'network' => $network ?? 'gam',
        ])
    </div>
</template>
