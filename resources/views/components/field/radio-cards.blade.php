{{--
    One of several, as cards (FORM_STEPS §5.2): the same radios as components/field/radio, each
    inside a card that is its label — the whole card is the target, the arrows move through the
    group the way they do in any radio group, and no role is laid over it. A choice's description
    and picture are inside the label too, so they are read with it.

    Classes: __cards (+ --cols-<n>), __card, __card-image, __card-title, __card-text; the chosen
    card is :has(:checked), the focused one :has(:focus-visible). Without CSS it is a list of
    radios with their descriptions.
--}}
<div class="webx-field__choices">
    <div @class(['webx-field__cards', 'webx-field__cards--cols-'.$columns => $columns !== null && $columns > 1])>
        @foreach ($options as $option)
            <label class="webx-field__card" for="{{ $fieldId }}-{{ $loop->index }}">
                <input type="radio" class="webx-field__check webx-field__card-check" id="{{ $fieldId }}-{{ $loop->index }}" name="{{ $inputName }}" value="{{ $option['value'] }}" @checked(in_array($option['value'], $chosen, true)) @required($required && ! $conditional()) @if ($required && $conditional()) data-webx-required="true" @endif @disabled($disabled || $option['disabled']) @if ($invalid) aria-invalid="true" @endif>
                @include('webx-widgets::components.field.partials.card', ['option' => $option])
            </label>
        @endforeach
    </div>
</div>
