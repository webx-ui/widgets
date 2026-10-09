<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View;

use WebxUi\Widgets\Menu\Item;

/**
 * What `<x-webx-header.nav>` hands the header it stands in (spec §6.2): the header draws its
 * mobile menu out of the same tree, as a mobile navigation rather than a copy of the desktop
 * markup with its dropdowns.
 *
 * Blade renders a component's slots before its view, so the navigation inside the header has
 * put its tree here by the time the header's view asks for it. Scoped, like the claims.
 */
final class HeaderNavigation
{
    /** @var list<Item>|null */
    private ?array $tree = null;

    /**
     * @param  list<Item>  $tree
     */
    public function hand(array $tree): void
    {
        $this->tree = $tree;
    }

    /**
     * The tree handed since the last call, once.
     *
     * @return list<Item>|null
     */
    public function take(): ?array
    {
        [$tree, $this->tree] = [$this->tree, null];

        return $tree;
    }
}
