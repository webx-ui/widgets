{{--
    The links of the pages of a list (spec §14). `data-page` on every number: `<x-webx-load-more>`
    marks the pages it has loaded with it.

    Classes: webx-pagination, __link, __link--prev, __link--next, __gap; is-current, is-loaded.
--}}
<nav {{ $attributes->class(['webx-pagination']) }} aria-label="{{ $labelText }}">
    @if ($paginator->previousPageUrl() !== null)
        <a class="webx-pagination__link webx-pagination__link--prev" rel="prev" href="{{ $href($page - 1) }}">{{ __('webx-widgets::widgets.pagination.previous') }}</a>
    @endif
    @foreach ($numbers() as $number)
        @if ($number === null)
            <span class="webx-pagination__gap" aria-hidden="true">…</span>
        @elseif ($number === $page)
            <span class="webx-pagination__link is-current" aria-current="page" data-page="{{ $number }}">{{ $number }}</span>
        @else
            <a class="webx-pagination__link" href="{{ $href($number) }}" data-page="{{ $number }}">{{ $number }}</a>
        @endif
    @endforeach
    @if ($paginator->hasMorePages())
        <a class="webx-pagination__link webx-pagination__link--next" rel="next" href="{{ $paginator->nextPageUrl() }}">{{ __('webx-widgets::widgets.pagination.next') }}</a>
    @endif
</nav>
