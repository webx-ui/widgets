{{--
    The slider (spec §7). Without JavaScript the track is a strip that scrolls sideways and snaps
    to a slide; the controls are printed `hidden` and shown by the script, which would otherwise
    leave buttons that do nothing. `gallery`'s thumbnails are the script's too.

    Slides per view by the width of the slider (a container query cannot read a custom property,
    so the widths are written here); the script reads back what the browser chose.

    Classes: webx-slider, webx-slider--<variant>, __viewport, __track, __slide, __controls,
    __button, __prev, __next, __pause, __pagination, __bullet, __count, __current, __total,
    __thumbs, __thumb; is-ready.

    `pagination="fraction"` prints the counter "1/6" where the dots would be. It is hidden from a
    screen reader: the slides are named "2 / 6" already, and Swiper's live region says the move.

    The `overlay` slot stands over the slides, in the same cell, out of the track: a pane that does
    not move while the slides do. It is printed `hidden` and shown by the script. Its
    `.webx-slider__caption` elements, one per slide in order, follow the slide in view: the current
    one `is-current`, the others `inert` and hidden from a screen reader. `pagination-in` names an
    element of it that takes the dots. Classes: webx-slider--overlaid, __overlay, __captions,
    __caption.
--}}
@php
    $sliders = app(\WebxUi\Widgets\View\Sliders::class);
    $count = $sliders->close();
    $id ??= $sliders->id((string) $slot.json_encode($config));
    $overlay ??= null;
    $overlaid = $overlay !== null && trim((string) $overlay) !== '';
    $rules = '';
    foreach ($views as [$from, $perView]) {
        $rule = "#{$id}-track { --webx-slider-per-view: {$perView}; }";
        $rules .= $from === 0 ? $rule : " @container webx-slider (min-width: {$from}px) { {$rule} }";
    }
@endphp
<section {{ $attributes->class(['webx-slider', 'webx-slider--'.$variant, 'webx-slider--overlaid' => $overlaid])->merge(array_filter([
    'id' => $id,
    'data-webx-slider' => json_encode($config + ['words' => $words], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'aria-roledescription' => __('webx-widgets::widgets.slider.carousel'),
    'aria-label' => $label,
])) }}>
    <style>{!! $rules !!}</style>

    <div class="webx-slider__viewport">
        <div class="webx-slider__track" id="{{ $id }}-track">{{ $slot }}</div>
    </div>

    @if ($overlaid)
        <div class="webx-slider__overlay" hidden>{{ $overlay }}</div>
    @endif

    @if ($count > 1 && ($hasArrows || ($hasPagination && ! $hasOutsideDots) || $hasCount || $hasPause))
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
            @if ($hasPagination && ! $hasOutsideDots)
                <div class="webx-slider__pagination"></div>
            @endif
            @if ($hasCount)
                <div class="webx-slider__count" aria-hidden="true"><span class="webx-slider__current">1</span><span class="webx-slider__count-divider">/</span><span class="webx-slider__total">{{ $count }}</span></div>
            @endif
            @if ($hasArrows)
                <button type="button" class="webx-slider__button webx-slider__next" aria-label="{{ $words['next'] }}" aria-controls="{{ $id }}-track"><x-webx-icon name="chevron-right" /></button>
            @endif
        </div>
    @endif
</section>
