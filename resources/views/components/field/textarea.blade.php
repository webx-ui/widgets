{{-- A letter rather than a line. The value goes between the tags with nothing around it: a newline
     after <textarea> is eaten by the parser, one before </textarea> is not. --}}
<textarea class="webx-field__control webx-field__control--textarea" id="{{ $fieldId }}" name="{{ $inputName }}" rows="{{ $rows ?? 5 }}" {{ $control($attributes) }}>{{ $shown }}</textarea>
