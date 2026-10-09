<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Widgets\Menu\Item;
use WebxUi\Widgets\View\HeaderNavigation;

/**
 * `<x-webx-header.nav :items="menu('header')" :mega="['services' => 'components.mega.services']">`
 * — the header's navigation with dropdowns (spec §6.2).
 *
 * A dropdown is the item's link and a toggle beside it: the link still goes where it says, the
 * toggle — or a hover, or the first tap on a touch screen — opens the list. An item in `mega`
 * opens a panel the width of the header instead, drawn by the theme's view with `$item`; the key
 * is the item's address without slashes (`services` for `/services`) or its label.
 */
final class HeaderNav extends Component
{
    private static int $count = 0;

    /** @var list<Item> */
    public array $tree;

    public string $prefix;

    /**
     * @param  iterable<mixed>|null  $items
     * @param  array<string, string>  $mega
     */
    public function __construct(
        ?iterable $items = null,
        public array $mega = [],
        public ?string $submenu = null,
    ) {
        $this->tree = Item::list($items);
        $this->prefix = 'webx-header-nav-'.++self::$count;
    }

    public function shouldRender(): bool
    {
        return $this->tree !== [];
    }

    public function render(): View
    {
        app(HeaderNavigation::class)->hand($this->tree);

        return view('webx-widgets::components.header.nav');
    }

    /** The view of the item's mega panel, if the layout gave it one. */
    public function megaView(Item $item): ?string
    {
        if ($this->mega === []) {
            return null;
        }

        $path = parse_url((string) $item->href(), PHP_URL_PATH);
        $keys = [trim(is_string($path) ? $path : '', '/'), mb_strtolower($item->label)];

        foreach ($this->mega as $key => $view) {
            if (in_array(mb_strtolower(trim($key, '/')), $keys, true)) {
                return $view;
            }
        }

        return null;
    }

    public function submenuLabel(Item $item): string
    {
        return $this->submenu !== null
            ? str_replace(':label', $item->label, $this->submenu)
            : __('webx-widgets::widgets.header.submenu', ['label' => $item->label]);
    }
}
