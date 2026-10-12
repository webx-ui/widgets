{{-- The one tick that says yes — terms, privacy, being written back to. Its words are a sentence,
     often with a link in it: the slot when there is one (written by the page, printed as it is),
     else the label. --}}
<label class="webx-field__choice webx-field__choice--consent" for="{{ $fieldId }}">
    <input type="checkbox" class="webx-field__check" id="{{ $fieldId }}" name="{{ $inputName }}" value="1" @checked($ticked) {{ $control($attributes) }}>
    <span class="webx-field__choice-label">@if ($slot->isNotEmpty()){{ $slot }}@else{{ $label }}@endif{!! $marker !!}</span>
</label>
