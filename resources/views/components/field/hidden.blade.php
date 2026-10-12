{{-- What the page fills in and the visitor never sees: which page, which product. No frame, no
     label, no error — nothing to read. --}}
<input type="hidden" id="{{ $fieldId }}" name="{{ $inputName }}" value="{{ $shown }}" {{ $attributes->except('class') }}>
