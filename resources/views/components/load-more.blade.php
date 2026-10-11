{{--
    "Show more" over a Laravel pagination (spec §14). The list and the links of the pages are what
    a page without JavaScript and a search engine get; the script shows the button (`hidden` here),
    covers the links with it while there is a next page (`is-covered`, unless pages="shown"),
    fetches the next page and takes the items of the list with the same `data-webx-load-more` out
    of it. The status line says what was loaded.

    Classes: webx-load-more, --shown, __list, __more, __button, __status, __pages; is-covered,
    is-loading. A `links` slot replaces <x-webx-pagination> with a module's own links.
--}}
@php($marked = str_contains((string) $slot, 'data-webx-load-more-list'))
<div {{ $attributes->class(['webx-load-more', 'webx-load-more--shown' => $pages === 'shown'])->merge(array_filter([
    'data-webx-load-more' => $key,
    'data-pages' => $pages,
    'data-page' => $page,
    'data-last' => $last,
    'data-next' => $next,
    'data-words' => json_encode($words, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
], static fn ($value): bool => $value !== null)) }}>
    @if ($marked)
        {{ $slot }}
    @else
        <div class="webx-load-more__list" data-webx-load-more-list>{{ $slot }}</div>
    @endif
    @if ($next !== null)
        <div class="webx-load-more__more">
            <button type="button" class="webx-load-more__button" hidden>{{ $labelText }}</button>
        </div>
    @endif
    <p class="webx-load-more__status" role="status"></p>
    @if (isset($links) && $links->isNotEmpty())
        <div class="webx-load-more__pages">{{ $links }}</div>
    @elseif ($paginator->hasPages())
        <div class="webx-load-more__pages"><x-webx-pagination :paginator="$paginator" /></div>
    @endif
</div>
