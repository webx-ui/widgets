<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Menu\Item;

/**
 * `<x-webx-mobile-menu.nav :items="menu('header')" mode="drill">` — a menu tree in the body of
 * the mobile menu (spec §6.1). Both modes are `<details>`: without JavaScript a branch opens in
 * place either way; with it, `drill` lays the branch over its parent with a Back button and the
 * branch's name on top. The branch the visitor is in is open.
 *
 * `parent` — what an accordion does with a branch that is a link itself. `repeat`, the default:
 * the name opens the branch, and the link is its first line. `split`: the name is the link, and
 * the branch opens by an arrow beside it — the same place is not offered twice. A choice of the
 * layout, not of the menu: the same tree is a dropdown in the header, where the parent is a link.
 * `drill` keeps its title on top of the branch either way.
 */
final class MobileMenuNav extends Component
{
    /** @var list<Item> */
    public array $tree;

    /**
     * @param  iterable<mixed>|null  $items
     */
    public function __construct(
        ?iterable $items = null,
        public string $mode = 'accordion',
        public ?string $label = null,
        public ?string $back = null,
        public string $parent = 'repeat',
        public ?string $submenu = null,
    ) {
        if (! in_array($mode, ['accordion', 'drill'], true)) {
            throw new InvalidArgumentException("A mobile navigation is \"accordion\" or \"drill\", not \"{$mode}\".");
        }

        if (! in_array($parent, ['repeat', 'split'], true)) {
            throw new InvalidArgumentException("The parent of a branch is \"repeat\" or \"split\", not \"{$parent}\".");
        }

        $this->tree = Item::list($items);
    }

    public function shouldRender(): bool
    {
        return $this->tree !== [];
    }

    /** What the arrow of `split` says: which branch it opens — the header's words for its own arrow. */
    public function submenuLabel(Item $item): string
    {
        return $this->submenu !== null
            ? str_replace(':label', $item->label, $this->submenu)
            : (string) __('webx-widgets::widgets.header.submenu', ['label' => $item->label]);
    }

    public function render(): View
    {
        return view('webx-widgets::components.mobile-menu.nav', [
            'navLabel' => $this->label ?? __('webx-widgets::widgets.header.nav'),
            'backLabel' => $this->back ?? __('webx-widgets::widgets.mobile_menu.back'),
            'split' => $this->mode === 'accordion' && $this->parent === 'split',
        ]);
    }
}
