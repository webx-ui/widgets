{{--
    The summary of a form (FORM_STEPS §7): what the visitor has answered so far, each answer with
    «Edit». Hidden without JavaScript — every answer stands on the page above it; summary.js fills
    __list from the form and shows it while there is something to show.

    `data-webx-summary-fields` — the names of the fields it shows (JSON), none: every field above it.
    `data-webx-summary-edit`, `data-webx-summary-field` — the words of «Edit» and of its name for a
    reader of the screen («Edit: :field»).

    Override: <layer>/views/vendor/webx-widgets/components/form/summary.blade.php — keep
    `data-webx-summary`, its words and `__list`.

    Classes: webx-form-summary, __title, __hint, __list, __row, __term, __value, __actions, __edit.
--}}
<section {{ $attributes->class(['webx-form-summary'])->merge(['id' => $summaryId]) }} data-webx-summary data-webx-summary-edit="{{ $words['edit'] }}" data-webx-summary-field="{{ $words['field'] }}"{!! $names !== null ? ' data-webx-summary-fields="'.e($names).'"' : '' !!}{!! $heading !== null ? ' aria-labelledby="'.e($summaryId).'-title"' : '' !!} hidden>
    @if ($heading !== null)
        <p class="webx-form-summary__title" id="{{ $summaryId }}-title" role="heading" aria-level="{{ $level }}">{{ $heading }}</p>
    @endif
    @if ($hint !== null && trim($hint) !== '')
        <p class="webx-form-summary__hint">{{ $hint }}</p>
    @endif
    <dl class="webx-form-summary__list"></dl>
</section>
