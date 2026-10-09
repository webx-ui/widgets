{{--
    The slider (spec §7). Without JavaScript the track is a strip that scrolls sideways and snaps
    to a slide; the controls are printed `hidden` and shown by the script, which would otherwise
    leave buttons that do nothing. `gallery`'s thumbnails are the script's too.

    Slides per view by the width of the slider (a container query cannot read a custom property,
    so the widths are written here); the script reads back what the browser chose.

    Classes: webx-slider, webx-slider--<variant>, __viewport, __track, __slide, __controls,
    __button, __prev, __next, __pause, __pagination, __bullet, __thumbs, __thumb; is-ready.
--}}
@php
    $sliders = app(\WebxUi\Widgets\View\Sliders::class);
    $count = $sliders->close();
    $id ??= $sliders->id((string) $slot.json_encode($config));
    $rules = '';
    foreach ($views as [$from, $perView]) {
        $rule = "#{$id}-track { --webx-slider-per-view: {$perView}; }";
        $rules .= $from === 0 ? $rule : " @container webx-slider (min-width: {$from}px) { {$rule} }";
    }
@endphp
<section {{ $attributes->class(['webx-slider', 'webx-slider--'.$variant])->merge(array_filter([
    'id' => $id,
    'data-webx-slider' => json_encode($config + ['words' => $words], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'aria-roledescription' => __('webx-widgets::widgets.slider.carousel'),
    'aria-label' => $label,
])) }}>
    <style>{!! $rules !!}</style>

    <div class="webx-slider__viewport">
        <div class="webx-slider__track" id="{{ $id }}-track">{{ $slot }}</div>
    </div>

    @if ($count > 1 && ($hasArrows || $hasPagination || $hasPause))
        <div class="webx-slider__controls" hidden>
            @if ($hasPause)
                <button type="button" class="webx-slider__button webx-slider__pause" aria-label="{{ $words['pause'] }}">
                    <x-webx-icon name="pause" class="webx-slider__pause-icon" />
                    <x-webx-icon name="play" class="webx-slider__play-icon" />
                </button>
            @endif
            @if ($hasArrows)
                <button type="button" class="webx-slider__button webx-slider__prev" aria-label="{{ $words['prev'] }}" aria-controls="{{ $id }}-track"><x-webx-icon name="chevron-left" /></button>
            @endif
            @if ($hasPagination)
                <div class="webx-slider__pagination"></div>
            @endif
            @if ($hasArrows)
                <button type="button" class="webx-slider__button webx-slider__next" aria-label="{{ $words['next'] }}" aria-controls="{{ $id }}-track"><x-webx-icon name="chevron-right" /></button>
            @endif
        </div>
    @endif
</section>
