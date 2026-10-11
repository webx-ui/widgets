{{--
    A countdown (spec §14): the time left at the response, which the script counts down; the date
    it ends on, which is what a page without JavaScript shows and a screen reader is told; at the
    end the `ended` text in its place, or nothing.

    Classes: webx-countdown, __date, __units, __unit, __unit--days | --hours | --minutes |
    --seconds, __value, __label, __ended; is-over.
--}}
<div {{ $attributes->class(['webx-countdown', 'is-over' => $over]) }} data-webx-countdown="{{ json_encode(['end' => $end->getTimestamp() * 1000], JSON_THROW_ON_ERROR) }}">
    @unless ($over)
    <p class="webx-countdown__date"><time datetime="{{ $end->toAtomString() }}">{{ __('webx-widgets::widgets.countdown.ends', ['date' => $date]) }}</time></p>
    <div class="webx-countdown__units" aria-hidden="true">
        @foreach ($left as $unit => $digits)
        <span class="webx-countdown__unit webx-countdown__unit--{{ $unit }}"><span class="webx-countdown__value" data-unit="{{ $unit }}">{{ $digits }}</span><span class="webx-countdown__label">{{ __("webx-widgets::widgets.countdown.{$unit}") }}</span></span>
        @endforeach
    </div>
    @endunless
    @if ($ended !== null)
    <p class="webx-countdown__ended" @unless ($over) hidden @endunless>{{ $ended }}</p>
    @endif
</div>
