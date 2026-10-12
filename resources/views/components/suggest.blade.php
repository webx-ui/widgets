{{--
    Suggestions while typing (spec §18, W6.5). With `action`: a GET form to the results page —
    without JavaScript nothing more. Without it: a `<div>` with the field, to stand inside another
    form. The script gives the field the combobox's ARIA, asks `source` as the visitor types and
    puts the HTML it answers into the panel (`hidden` and empty here). The status line, read by a
    screen reader only, says how many suggestions came.

    Classes: webx-suggest, --field, __label (--shown), __box, __input (--alone), __submit, __panel, __status;
    the script's, for what it builds or marks in the site's HTML: __section, __heading, __option,
    __clear, __empty; states is-open, is-loading, and aria-selected="true" on the option chosen by keys.
--}}
@php($settings = [
    'data-webx-suggest' => $source,
    'data-param' => $query,
    'data-min' => $min,
    'data-delay' => $delay,
    'data-recent' => $recent,
    'data-words' => json_encode($words, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
])
@if ($action !== null)
<form {{ $attributes->class(['webx-suggest'])->merge(['method' => 'get', 'action' => $action, 'role' => 'search', ...$settings]) }}>
@else
<div {{ $attributes->class(['webx-suggest', 'webx-suggest--field'])->merge($settings) }}>
@endif
    <label @class(['webx-suggest__label', 'webx-suggest__label--shown' => $showLabel]) for="{{ $key }}">{{ $labelText }}</label>
    <div class="webx-suggest__box">
        <input @class(['webx-suggest__input', 'webx-suggest__input--alone' => $action === null]) type="{{ $action !== null ? 'search' : 'text' }}" id="{{ $key }}" name="{{ $name }}" value="{{ $term }}"
               placeholder="{{ $placeholderText }}"@if ($action !== null) enterkeyhint="search"@endif spellcheck="false" autocapitalize="none">
        @if ($action !== null)
            <button type="submit" class="webx-suggest__submit">
                <x-webx-icon name="search" />
                <span class="webx-suggest__submit-text">{{ $submitText }}</span>
            </button>
        @endif
        <div class="webx-suggest__panel" id="{{ $key }}-panel" hidden></div>
    </div>
    <p class="webx-suggest__status" role="status"></p>
@if ($action !== null)
</form>
@else
</div>
@endif
