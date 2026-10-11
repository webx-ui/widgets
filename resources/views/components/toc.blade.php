{{--
    A table of contents (spec §14). The list is a marker here: the server fills it once the page
    is finished (`Prose\Contents`, view `webx-widgets::toc`) from the slot below, the element
    `for` names, or <main>. With a slot the root is a grid — the list beside the text, sticky
    under the header — that puts the list above the text in a narrow container.

    Classes: webx-toc, __layout, __layout--start | --end | --alone, __content; the list's are in
    toc.blade.php.
--}}
@php($wrapped = trim((string) $slot) !== '')
<div {{ $attributes->class(['webx-toc']) }} data-webx-toc @if ($fold === 'never') data-fold="never" @endif>
    <div class="webx-toc__layout webx-toc__layout--{{ $wrapped ? $side : 'alone' }}">
        {!! $marker($for ?? ($wrapped ? $id.'-content' : null), $wrapped) !!}
        @if ($wrapped)
        <div class="webx-toc__content" id="{{ $id }}-content">{{ $slot }}</div>
        @endif
    </div>
</div>
