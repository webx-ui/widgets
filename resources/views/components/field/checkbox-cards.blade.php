{{--
    Several of several, as cards (FORM_STEPS §5.2): the ticks of components/field/checkbox, each
    inside a card that is its label. No `required` on a box, as in the list: how many is the
    server's rule, its error shown on the group. Classes — as components/field/radio-cards.
--}}
<div class="webx-field__choices">
    <div @class(['webx-field__cards', 'webx-field__cards--cols-'.$columns => $columns !== null && $columns > 1])>
        @foreach ($options as $option)
            <label class="webx-field__card" for="{{ $fieldId }}-{{ $loop->index }}">
                <input type="checkbox" class="webx-field__check webx-field__card-check" id="{{ $fieldId }}-{{ $loop->index }}" name="{{ $inputName }}" value="{{ $option['value'] }}" @checked(in_array($option['value'], $chosen, true)) @disabled($disabled || $option['disabled']) @if ($invalid) aria-invalid="true" @endif>
                @include('webx-widgets::components.field.partials.card', ['option' => $option])
            </label>
        @endforeach
    </div>
</div>
