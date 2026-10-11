{{--
    Before and after (spec §14): the after picture over the before one, cut at the divider. The
    frame has the pictures' shape before they load, so nothing below it moves. The divider is a
    range input — a slider for a screen reader, the arrows for a keyboard — which the script ties
    to the frame for a mouse and a finger. Without JavaScript the two stand side by side, or one
    under the other in a narrow column, each with its label.

    Left to right whatever the language: "before" on the left is how the pictures are taken and
    shown, and a divider that ran the other way would be a second convention to learn.

    Classes: webx-compare, __frame, __side, __side--before | --after, __picture, __label,
    __handle, __range, __caption; is-dragging, is-focused.
--}}
<figure style="--webx-compare-ratio: {{ $shape }}; --webx-compare-position: {{ $position }}%" {{ $attributes->class(['webx-compare']) }} data-webx-compare>
    <div class="webx-compare__frame" dir="ltr">
        <div class="webx-compare__side webx-compare__side--before">
            <img class="webx-compare__picture" src="{{ $beforeUrl }}" alt="{{ $beforeAlt }}" @if ($beforeWidth && $beforeHeight) width="{{ $beforeWidth }}" height="{{ $beforeHeight }}" @endif loading="lazy" decoding="async" draggable="false">
            <span class="webx-compare__label webx-compare__label--before">{{ $beforeText }}</span>
        </div>
        <div class="webx-compare__side webx-compare__side--after">
            <img class="webx-compare__picture" src="{{ $afterUrl }}" alt="{{ $afterAlt }}" @if ($afterWidth && $afterHeight) width="{{ $afterWidth }}" height="{{ $afterHeight }}" @endif loading="lazy" decoding="async" draggable="false">
            <span class="webx-compare__label webx-compare__label--after">{{ $afterText }}</span>
        </div>
        <span class="webx-compare__handle" aria-hidden="true"></span>
        <input class="webx-compare__range" type="range" min="0" max="100" step="any" value="{{ $position }}" aria-label="{{ $sliderLabel }}" aria-valuetext="{{ $valueText }}">
    </div>
    @if (trim((string) $slot) !== '')
    <figcaption class="webx-compare__caption">{{ $slot }}</figcaption>
    @endif
</figure>
