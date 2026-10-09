{{--
    A picture that opens over the page (spec §8). Without JavaScript it is a link to the picture;
    with it the picture opens in the lightbox, paging through the links of the same group.

    Classes: webx-lightbox-link, __image; is-ready — the script's.
--}}
<a {{ $attributes->class(['webx-lightbox-link'])->merge(array_filter([
    'href' => $href,
    'data-webx-lightbox' => $group,
    'data-width' => $pictureWidth,
    'data-height' => $pictureHeight,
], static fn ($value): bool => $value !== null)) }}>@if ($slot->isEmpty())<img class="webx-lightbox-link__image" src="{{ $preview }}" alt="{{ $text }}" loading="lazy">@else{{ $slot }}@endif</a>
