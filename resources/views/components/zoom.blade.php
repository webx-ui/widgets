{{--
    A picture whose details come closer (spec §14). It is a link to the original that opens the
    lightbox: a keyboard and a screen reader have no hover, and without JavaScript the link opens
    the original itself. The script adds the magnified view on a hover and under two fingers, and
    fetches the original only then.

    Classes: webx-zoom, webx-zoom-link, --inner | --side, __picture, __badge, __icon; the script's
    __view, __large, __lens, __panel; is-active, is-zoomed, is-side, is-hidden, is-easing.
    data-zoom-frame only for frame="container": the mouse's window is then the whole link.
--}}
<a {{ $attributes->class(['webx-zoom', 'webx-zoom-link', "webx-zoom--{$mode}"])->merge(array_filter([
    'href' => $large,
    'data-webx-zoom' => $large,
    'data-zoom-mode' => $mode,
    'data-zoom-scale' => $magnify,
    'data-zoom-frame' => $frame === 'picture' ? null : $frame,
    'data-webx-lightbox' => $group,
    'data-width' => $pictureWidth,
    'data-height' => $pictureHeight,
    'aria-label' => $name,
], static fn ($value): bool => $value !== null)) }}><img class="webx-zoom__picture" src="{{ $shown }}" alt="{{ $text }}" @if ($pictureWidth && $pictureHeight) width="{{ $pictureWidth }}" height="{{ $pictureHeight }}" @endif @unless ($eager) loading="lazy" @endunless decoding="async" draggable="false"><span class="webx-zoom__badge" aria-hidden="true"><x-webx-icon name="zoom" class="webx-zoom__icon" /></span></a>
