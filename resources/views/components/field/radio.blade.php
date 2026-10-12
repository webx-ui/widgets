{{-- One of several. required goes on every radio, which is how a browser reads a group: answered
     as soon as any one of them is. A row is as tall as a finger, and the label is the target. --}}
<div class="webx-field__choices">
    @foreach ($options as $option)
        <label @class(['webx-field__choice', 'webx-field__choice--inline' => $inline]) for="{{ $fieldId }}-{{ $loop->index }}">
            <input type="radio" class="webx-field__check" id="{{ $fieldId }}-{{ $loop->index }}" name="{{ $inputName }}" value="{{ $option['value'] }}" @checked(in_array($option['value'], $chosen, true)) @required($required) @disabled($disabled || $option['disabled']) @if ($invalid) aria-invalid="true" @endif>
            <span class="webx-field__choice-label">{{ $option['label'] }}</span>
        </label>
    @endforeach
</div>
