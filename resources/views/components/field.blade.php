{{--
    A field of a form (spec §17.2): the label, the control, the hint, the place of the error. The
    control is a view per type — components/field/<type>.blade.php — so a theme that wants another
    select overrides that one file and keeps the frame.

    Classes: webx-field, --<type>, __label (--legend on a group), __required, __optional, __hint,
    __error, and the controls' (see each type); states is-invalid, is-required, is-disabled.

    The error is always there, empty and hidden until there is something to say, and the control
    is described by it: the form's script writes into it rather than building it.
    `data-webx-field` is the validator's name of the field — what the errors of a 422 are keyed by.
    `data-webx-rules` — the checks of FormRules with the validator's words, what form.js checks by:
    on the frame, so a theme that overrides the view of a type keeps them.
--}}
@if ($type === 'hidden')
    @include('webx-widgets::components.field.hidden')
@else
    @php
        $classes = ['webx-field', 'webx-field--'.$type, 'is-invalid' => $invalid, 'is-required' => $required, 'is-disabled' => $disabled];
        $marker = $required
            ? '<span class="webx-field__required" aria-hidden="true">*</span>'
            : ($optional ? '<span class="webx-field__optional">('.e($words['optional']).')</span>' : '');
    @endphp
    @if ($grouped())
        <fieldset {{ $attributes->except('class')->merge(['aria-describedby' => $describedBy()]) }} {{ $attributes->only('class')->class($classes) }} data-webx-field="{{ $key }}"@if ($checks !== null) data-webx-rules="{{ $checks }}"@endif>
            <legend class="webx-field__label webx-field__label--legend">{{ $label }}{!! $marker !!}</legend>
            @include('webx-widgets::components.field.'.$type)
    @else
        <div {{ $attributes->only('class')->class($classes) }} data-webx-field="{{ $key }}"@if ($checks !== null) data-webx-rules="{{ $checks }}"@endif>
            @if ($tick())
                @include('webx-widgets::components.field.'.$type)
            @else
                @if ($label !== null && $label !== '')
                    <label class="webx-field__label" for="{{ $fieldId }}">{{ $label }}{!! $marker !!}</label>
                @endif
                @include('webx-widgets::components.field.'.$type)
            @endif
    @endif
        @if ($hint !== null && trim($hint) !== '')
            <p class="webx-field__hint" id="{{ $hintId }}">{{ $hint }}</p>
        @endif
        <p class="webx-field__error" id="{{ $errorId }}" @if (! $invalid) hidden @endif>{{ $message }}</p>
    @if ($grouped())
        </fieldset>
    @else
        </div>
    @endif
@endif
