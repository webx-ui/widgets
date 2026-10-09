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
    ) {
        if (! in_array($mode, ['accordion', 'drill'], true)) {
            throw new InvalidArgumentException("A mobile navigation is \"accordion\" or \"drill\", not \"{$mode}\".");
        }

        $this->tree = Item::list($items);
    }

    public function shouldRender(): bool
    {
        return $this->tree !== [];
    }

    public function render(): View
    {
        return view('webx-widgets::components.mobile-menu.nav', [
            'navLabel' => $this->label ?? __('webx-widgets::widgets.header.nav'),
            'backLabel' => $this->back ?? __('webx-widgets::widgets.mobile_menu.back'),
        ]);
    }
}
