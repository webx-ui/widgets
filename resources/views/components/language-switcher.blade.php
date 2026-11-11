{{--
    The same page in the site's other languages (spec §13). `dropdown` shows the current
    language on the button of a dropdown (§6.3) with every language in its panel; `list` is
    every language in a row. Each is named in itself and carries `hreflang` and `lang`; a page
    with no translation leads to that language's home page, `is-fallback`, with a title in that
    language saying so. No flags: a flag is a country, not a language.

    The offer of the first visit's language (ROUTING §18.6), when the server decided there is one:
    a non-modal dialog over the page beside the switcher, printed `hidden` — the script shows it,
    since without it a refusal could not be kept. Its words are in the language offered.

    Classes: webx-language-switcher, --dropdown, --list, --offer (carries one), __current, __list,
    __item, __link, __name, __code; is-current, is-fallback; webx-language-offer, __question,
    __actions, __accept, __stay, __close, __icon.
--}}
@php($fallbackTitle = fn ($link) => $fallbackLabel ?? __('webx-widgets::widgets.language.fallback', [], $link->code))
<nav {{ $attributes->class(['webx-language-switcher', 'webx-language-switcher--'.$layout, 'webx-language-switcher--offer' => $offerBox !== null]) }} aria-label="{{ $labelText }}">
    @if ($layout === 'dropdown')
        <x-webx-dropdown class="webx-language-switcher__dropdown" :placement="$placement">
            <x-slot:trigger class="webx-language-switcher__current"><x-webx-icon name="globe" /><span class="webx-language-switcher__name" lang="{{ $current->tag() }}">{{ $codes ? strtoupper($current->tag()) : $current->name }}</span></x-slot:trigger>
            @include('webx-widgets::partials.language-list')
        </x-webx-dropdown>
    @else
        @include('webx-widgets::partials.language-list')
    @endif
    @if ($offerBox !== null)
        @include('webx-widgets::partials.language-offer', ['offer' => $offerBox])
    @endif
</nav>
