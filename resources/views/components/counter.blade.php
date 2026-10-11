{{--
    A counter (spec §14): the final number in the markup — for search engines, screen readers and
    a page without JavaScript — which the script counts up to when it comes into view.

    Classes: webx-counter, __prefix, __number, __suffix, __final (the number a screen reader is
    told while the visible one counts); is-counting.
--}}
<span {{ $attributes->class(['webx-counter']) }} data-webx-counter="{{ json_encode(['value' => $number, 'decimals' => $places, 'duration' => $duration], JSON_THROW_ON_ERROR) }}">@if ($prefix)<span class="webx-counter__prefix">{{ $prefix }}</span>@endif<span class="webx-counter__number">{{ $final }}</span>@if ($suffix)<span class="webx-counter__suffix">{{ $suffix }}</span>@endif</span>
