<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-load-more :paginator="$articles">…the items…</x-webx-load-more>` — "Show more" over an
 * ordinary Laravel pagination (spec §14): what `->paginate()` or `->simplePaginate()` answered.
 *
 * The server prints the list and the links of the pages (`<x-webx-pagination>`), and that is what
 * a page without JavaScript and a search engine get. The script shows the button: it fetches the
 * next page as it is, takes the items of the same list out of its HTML, adds them here, moves the
 * focus to the first of them and the address to `?page=N`. No endpoint of its own: the page a
 * module already renders is the only markup of an item there is, a theme's override included.
 *
 * `pages`: `covered` (by default) — the button takes the links' place while there is a next page;
 * `shown` — both, the links under the button following what was loaded, the pages on the screen
 * marked. The list is the element marked `data-webx-load-more-list` inside the slot, its items
 * that element's children; a slot without the mark is the items themselves, wrapped here. A
 * `links` slot replaces the links with a module's own — they must stay inside.
 */
final class LoadMore extends Component
{
    public const array PAGES = ['covered', 'shown'];

    public string $key;

    public ?string $next;

    public int $page;

    public ?int $last;

    /** @param  Paginator<array-key, mixed>  $paginator */
    public function __construct(
        public Paginator $paginator,
        public string $pages = 'covered',
        public ?string $label = null,
    ) {
        if (! in_array($pages, self::PAGES, true)) {
            throw new InvalidArgumentException("<x-webx-load-more pages=\"{$pages}\">: covered or shown.");
        }

        // The same list on the next page: one pagination per query parameter.
        $this->key = $paginator instanceof AbstractPaginator ? $paginator->getPageName() : 'page';
        $this->next = $paginator->hasMorePages() ? $paginator->nextPageUrl() : null;
        $this->page = $paginator->currentPage();
        $this->last = $paginator instanceof LengthAwarePaginator ? $paginator->lastPage() : null;
    }

    public function render(): View
    {
        Widgets::need('load-more');

        return view('webx-widgets::components.load-more', [
            'labelText' => $this->label ?? __('webx-widgets::widgets.load_more.label'),
            'words' => [
                'loading' => __('webx-widgets::widgets.load_more.loading'),
                'loaded' => __($this->last === null ? 'webx-widgets::widgets.load_more.loaded_page' : 'webx-widgets::widgets.load_more.loaded'),
                'end' => __('webx-widgets::widgets.load_more.end'),
                'failed' => __('webx-widgets::widgets.load_more.failed'),
            ],
        ]);
    }
}
