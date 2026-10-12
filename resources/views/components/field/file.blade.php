{{--
    A file, or several. The browser's own button speaks the browser's language, not the page's: with
    the script the input lies invisible over a button and a line in the page's words ("Choose a
    file", "No file chosen", the name once chosen) and is still the control a keyboard and a screen
    reader use. Without it the input is shown as it is.

    With files.js the field also takes files dropped on it, and lists the chosen under the button —
    the name, the size, a picture's preview, a button that removes one before sending. The list and
    the drop hint are here, empty and hidden, so a theme styles them like the rest of the field.

    accept is a hint to the picker, nothing more — the server checks the file. No value comes back
    after an error: a browser lets no page choose a file.
--}}
<span class="webx-field__file" data-webx-file="{{ json_encode(['none' => $words['no_file'], 'many' => $words['files']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}" data-webx-files="{{ json_encode(['remove' => $words['remove']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
    <input type="file" class="webx-field__control webx-field__control--file" id="{{ $fieldId }}" name="{{ $inputName }}" {{ $control($attributes) }}>
    <span class="webx-field__file-button" aria-hidden="true">{{ $words['file'] }}</span>
    <span class="webx-field__file-name" aria-hidden="true">{{ $words['no_file'] }}</span>
</span>
<span class="webx-field__drop" hidden>{{ $words['drop'] }}</span>
<ul class="webx-field__files" hidden></ul>
