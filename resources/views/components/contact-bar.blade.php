{{--
    The bottom bar on a phone (spec §12.4). The root stands in the flow at the end of the page, as
    wide as it, and is the container both children ask: the spacer keeps the bar's height at the
    end of the page — so the footer is never under the bar — and the bar is fixed to the bottom of
    the window above the home indicator. Both go once the page is `breakpoint` wide: a container
    query cannot read a custom property, so the width is written here.

    Classes: webx-contact-bar, __spacer, __bar, __item, __item--call, __item--write, __item--form.
--}}
@php($id = 'webx-contact-bar-'.substr(md5(serialize($links)), 0, 8))
<div {{ $attributes->class(['webx-contact-bar'])->merge(['id' => $id]) }}>
    <style>@container (min-width: {{ $breakpoint }}px) { #{{ $id }}-spacer, #{{ $id }}-bar { display: none; } }</style>
    <div class="webx-contact-bar__spacer" id="{{ $id }}-spacer"></div>
    <nav class="webx-contact-bar__bar" id="{{ $id }}-bar" aria-label="{{ __('webx-widgets::widgets.contact.bar') }}">
        @foreach ($links as $link)
            <a class="webx-contact-bar__item webx-contact-bar__item--{{ $link['kind'] }}" href="{{ $link['href'] }}"@if ($link['external']) target="_blank" rel="noopener"@endif>
                <x-webx-icon :name="$link['icon']" />
                <span>{{ $link['text'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
