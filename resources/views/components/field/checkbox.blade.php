{{--
    Several of several — or, without :choices, one tick with its words beside it.

    A group has no required on its boxes: a browser reads it as "this one must be ticked", not
    "one of these must be". How many is the server's rule (`required`, `min`), its error shown on
    the group. One tick sends its value ("1" unless told) when ticked and nothing when not.
--}}
@if ($options === [])
    <label class="webx-field__choice webx-field__choice--single" for="{{ $fieldId }}">
        <input type="checkbox" class="webx-field__check" id="{{ $fieldId }}" name="{{ $inputName }}" value="{{ is_scalar($value) && (string) $value !== '' ? $value : '1' }}" @checked($ticked) {{ $control($attributes) }}>
        <span class="webx-field__choice-label">{{ $label }}{!! $marker !!}</span>
    </label>
@else
    <div class="webx-field__choices">
        @foreach ($options as $option)
            <label @class(['webx-field__choice', 'webx-field__choice--inline' => $inline]) for="{{ $fieldId }}-{{ $loop->index }}">
                <input type="checkbox" class="webx-field__check" id="{{ $fieldId }}-{{ $loop->index }}" name="{{ $inputName }}" value="{{ $option['value'] }}" @checked(in_array($option['value'], $chosen, true)) @disabled($disabled || $option['disabled']) @if ($invalid) aria-invalid="true" @endif>
                <span class="webx-field__choice-label">{{ $option['label'] }}</span>
            </label>
        @endforeach
    </div>
@endif
