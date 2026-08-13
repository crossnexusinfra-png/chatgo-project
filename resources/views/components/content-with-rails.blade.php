@props(['instancePrefix' => 'content'])

<div class="thread-show-outer legal-content-outer">
    <aside class="thread-show-rail thread-show-rail--left" aria-label="Advertisement">
        @for ($i = 1; $i <= 3; $i++)
            @include('components.adsense-rail-unit', [
                'side' => 'left',
                'instanceId' => $instancePrefix.'-rail-left-'.$i,
            ])
        @endfor
    </aside>

    <div class="legal-content-panel">
        {{ $slot }}
    </div>

    <aside class="thread-show-rail thread-show-rail--right" aria-label="Advertisement">
        @for ($i = 1; $i <= 3; $i++)
            @include('components.adsense-rail-unit', [
                'side' => 'right',
                'instanceId' => $instancePrefix.'-rail-right-'.$i,
            ])
        @endfor
    </aside>
</div>
