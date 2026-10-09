{{--
    One slide (spec §7). The script names it "2 / 5" for a screen reader; its pictures are lazy
    unless it is among the first in view.

    Classes: webx-slider__slide; is-active, is-visible, is-next, is-prev — the script's.
--}}
<div {{ $attributes->class(['webx-slider__slide'])->merge(array_filter([
    'role' => 'group',
    'aria-roledescription' => __('webx-widgets::widgets.slider.slide'),
    'data-thumb' => $thumb,
])) }}>{{ $eager ? $slot : \WebxUi\Widgets\View\Components\Slide::lazy((string) $slot) }}</div>
