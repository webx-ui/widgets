{{--
    The links of the pages of a list (spec §14). `data-page` on every number: `<x-webx-load-more>`
    marks the pages it has loaded with it.

    Two rows in one: what is only in the full row is `is-extra`, what is only in the compact one
    (the first, this page and the last, with their gaps) is `is-compact`, and the stylesheet shows
    one by the width the pagination has. Previous and next carry an arrow and their word; in the
    compact row the word is there for a screen reader only.

    Classes: webx-pagination, __link, __link--prev, __link--next, __gap, __arrow, __word;
    is-current, is-loaded, is-extra, is-compact.
--}}
<nav {{ $attributes->class(['webx-pagination']) }} aria-label="{{ $labelText }}">
    @if ($paginator->previousPageUrl() !== null)
        <a class="webx-pagination__link webx-pagination__link--prev" rel="prev" href="{{ $href($page - 1) }}"><x-webx-icon name="chevron-left" class="webx-pagination__arrow" /><span class="webx-pagination__word">{{ __('webx-widgets::widgets.pagination.previous') }}</span></a>
    @endif
    @foreach ($items() as $item)
        @if ($item['number'] === null)
            <span @class(['webx-pagination__gap', 'is-extra' => $item['only'] === 'extra', 'is-compact' => $item['only'] === 'compact']) aria-hidden="true">…</span>
        @elseif ($item['number'] === $page)
            <span @class(['webx-pagination__link', 'is-current', 'is-extra' => $item['only'] === 'extra', 'is-compact' => $item['only'] === 'compact']) aria-current="page" data-page="{{ $item['number'] }}">{{ $item['number'] }}</span>
        @else
            <a @class(['webx-pagination__link', 'is-extra' => $item['only'] === 'extra', 'is-compact' => $item['only'] === 'compact']) href="{{ $href($item['number']) }}" data-page="{{ $item['number'] }}">{{ $item['number'] }}</a>
        @endif
    @endforeach
    @if ($paginator->hasMorePages())
        <a class="webx-pagination__link webx-pagination__link--next" rel="next" href="{{ $paginator->nextPageUrl() }}"><span class="webx-pagination__word">{{ __('webx-widgets::widgets.pagination.next') }}</span><x-webx-icon name="chevron-right" class="webx-pagination__arrow" /></a>
    @endif
</nav>
