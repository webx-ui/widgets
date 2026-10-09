{{--
    The same page in the site's other languages (spec §13). `dropdown` shows the current
    language on the button of a dropdown (§6.3) with every language in its panel; `list` is
    every language in a row. Each is named in itself and carries `hreflang` and `lang`; a page
    with no translation leads to that language's home page, `is-fallback`, with a title in that
    language saying so. No flags: a flag is a country, not a language.

    Classes: webx-language-switcher, --dropdown, --list, __current, __list, __item, __link,
    __name, __code; is-current, is-fallback.
--}}
@php($fallbackTitle = fn ($link) => $fallbackLabel ?? __('webx-widgets::widgets.language.fallback', [], $link->code))
<nav {{ $attributes->class(['webx-language-switcher', 'webx-language-switcher--'.$layout]) }} aria-label="{{ $labelText }}">
    @if ($layout === 'dropdown')
        <x-webx-dropdown class="webx-language-switcher__dropdown" :placement="$placement">
            <x-slot:trigger class="webx-language-switcher__current"><x-webx-icon name="globe" /><span class="webx-language-switcher__name" lang="{{ $current->tag() }}">{{ $codes ? strtoupper($current->tag()) : $current->name }}</span></x-slot:trigger>
            @include('webx-widgets::partials.language-list')
        </x-webx-dropdown>
    @else
        @include('webx-widgets::partials.language-list')
    @endif
</nav>
