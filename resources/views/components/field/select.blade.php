{{-- The empty option comes first, required or not: a select that opens already answered collects
     that answer from everybody who did not read it. The arrow is the frame's, in currentColor —
     the browser's own stays light on a dark preset. --}}
<span @class(['webx-field__select', 'webx-field__select--multiple' => $multiple])>
    <select class="webx-field__control webx-field__control--select" id="{{ $fieldId }}" name="{{ $inputName }}" {{ $control($attributes) }}>
        @unless ($multiple)
            <option value="">{{ $placeholder ?? $words['choose'] }}</option>
        @endunless
        @foreach ($options as $option)
            <option value="{{ $option['value'] }}" @selected(in_array($option['value'], $chosen, true)) @disabled($option['disabled'])>{{ $option['label'] }}</option>
        @endforeach
    </select>
</span>
