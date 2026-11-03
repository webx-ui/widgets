{{--
    A list with a search (spec §17, decision 36 of the delivery spec). Without JavaScript: a
    `<select>` of the options given (`strict`), or a text field (`:strict="false"`). The script keeps
    that element as the holder of the value — hidden, its `change` fired on each choice — and puts
    the combobox beside it: the field the visitor types into, the list under it, the status line
    for a screen reader.

    `data-webx-select` — the server's address of the search (empty: the loaded options only);
    `data-strict` — only an option can be the value; `data-inline` — the list opens in the flow
    (`__list--inline`), not over the page: inside a dialog.

    Classes: webx-select (+ --searchable, --free), __label (--shown), __native, __box, __input,
    __options, __list (--inline), __option, __hint, __more, __empty, __status; states is-open, is-loading, and
    aria-selected="true" on the option chosen by keys.
--}}
<div {{ $attributes->class(['webx-select', 'webx-select--searchable' => $searchable || $source !== null, 'webx-select--free' => ! $strict])->merge([
    'data-webx-select' => ($searchable || $source !== null) ? (string) $source : null,
    'data-param' => $param,
    'data-min' => $min,
    'data-delay' => $delay,
    'data-strict' => $strict ? 'true' : 'false',
    'data-inline' => $inline ? 'true' : null,
    'data-words' => json_encode($words, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
]) }}>
    <label @class(['webx-select__label', 'webx-select__label--shown' => $showLabel]) for="{{ $key }}">{{ $labelText }}</label>
    @if ($strict)
        <select class="webx-select__native" id="{{ $key }}" name="{{ $name }}" @required($required) @disabled($disabled)>
            <option value="">{{ $placeholderText }}</option>
            @foreach ($choices as $choice)
                <option value="{{ $choice['value'] }}" @if ($choice['hint'] !== null) data-hint="{{ $choice['hint'] }}" @endif @selected($choice['value'] === $current) @disabled($choice['disabled'])>{{ $choice['label'] }}</option>
            @endforeach
            {{-- A value the loaded page does not hold — chosen from the server's search — stays chosen. --}}
            @if ($current !== '' && $chosenLabel === '')
                <option value="{{ $current }}" selected>{{ $current }}</option>
            @endif
        </select>
    @else
        <input class="webx-select__native" type="text" id="{{ $key }}" name="{{ $name }}" value="{{ $current }}" placeholder="{{ $placeholderText }}" autocomplete="off" @required($required) @disabled($disabled)>
        @if ($choices !== [])
            <template class="webx-select__options">
                @foreach ($choices as $choice)
                    <option value="{{ $choice['value'] }}" @if ($choice['hint'] !== null) data-hint="{{ $choice['hint'] }}" @endif>{{ $choice['label'] }}</option>
                @endforeach
            </template>
        @endif
    @endif
    <p class="webx-select__status" role="status"></p>
</div>
