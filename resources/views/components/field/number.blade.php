{{-- A number. inputmode is numeric for a whole step and decimal otherwise — the dial pad has no comma. --}}
<input type="number" class="webx-field__control" id="{{ $fieldId }}" name="{{ $inputName }}" value="{{ $shown }}" {{ $control($attributes) }}>
