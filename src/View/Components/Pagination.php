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
 * `<x-webx-pagination :paginator="$articles" />` — the links of the pages of a list (spec §14):
 * previous, the numbers around this page with the first and the last always there and a gap
 * between, next. What `->paginate()` answered as it is; `->simplePaginate()` knows no last page
 * and gets previous and next only.
 *
 * Not `$paginator->links()`: that prints the markup of a CSS framework the site does not have. A
 * stylesheet and no script; `<x-webx-load-more>` prints its links with it.
 */
final class Pagination extends Component
{
    public int $page;

    public ?int $last;

    /** @param  Paginator<array-key, mixed>  $paginator */
    public function __construct(
        public Paginator $paginator,
        public int $around = 2,
        public ?string $label = null,
    ) {
        if ($around < 0 || $around > 5) {
            throw new InvalidArgumentException("<x-webx-pagination :around=\"{$around}\">: the numbers either side of this page, 0 to 5.");
        }

        $this->page = $paginator->currentPage();
        $this->last = $paginator instanceof LengthAwarePaginator ? $paginator->lastPage() : null;
    }

    public function shouldRender(): bool
    {
        return $this->paginator->hasPages();
    }

    public function render(): View
    {
        Widgets::need('pagination');

        return view('webx-widgets::components.pagination', [
            'labelText' => $this->label ?? __('webx-widgets::widgets.pagination.label'),
        ]);
    }

    /**
     * The address of a page. The first is the list's own address, without `?page=1`: the same
     * page under two addresses is a duplicate to a search engine, and a crawler that follows the
     * links would meet it on every page.
     */
    public function href(int $number): string
    {
        $url = $this->paginator->url($number);

        if ($number !== 1 || ! $this->paginator instanceof AbstractPaginator) {
            return $url;
        }

        $name = preg_quote($this->paginator->getPageName(), '/');
        $url = (string) preg_replace('/([?&])'.$name.'=1(?:&|$)/', '$1', $url);

        return rtrim($url, '?&');
    }

    /**
     * The numbers to print: this page and `around` either side, the first and the last always,
     * null for a gap — a gap of one page is that page instead. Empty without a last page.
     *
     * @return list<int|null>
     */
    public function numbers(): array
    {
        if ($this->last === null || $this->last < 2) {
            return [];
        }

        $shown = [1, $this->last, ...range(max(1, $this->page - $this->around), min($this->last, $this->page + $this->around))];
        $shown = array_values(array_unique($shown));
        sort($shown);

        $numbers = [];
        $before = 0;

        foreach ($shown as $number) {
            if ($number === $before + 2) {
                $numbers[] = $before + 1;
            } elseif ($number > $before + 2) {
                $numbers[] = null;
            }

            $numbers[] = $number;
            $before = $number;
        }

        return $numbers;
    }
}
