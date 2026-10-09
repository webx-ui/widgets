<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Menu\Item;
use WebxUi\Widgets\View\HeaderNavigation;

/**
 * `<x-webx-header sticky="hide-on-scroll" collapse="auto">` — the behaviour and the skeleton of a
 * site's header (spec §6.2); how it looks and in which order its parts stand is the theme's.
 *
 * Unless told otherwise it builds its own mobile menu out of what it already has: `brand` on
 * top, the navigation in the body, `actions` and `mobile-bottom` at the bottom.
 *
 *     collapse    auto — the navigation folds into the menu when its items no longer fit in a
 *                 row (measured); a number — when the header is narrower than that; never
 *     breakpoint  the width `auto` folds at without JavaScript, which has nothing to measure with
 *     sticky      none | sticky | hide-on-scroll
 *     overlay     transparent over the first screen until scrolled past its own height
 *     skip        the target of the "Skip to content" link, or false when the layout has one
 */
final class Header extends Component
{
    public const array STICKY = ['none', 'sticky', 'hide-on-scroll'];

    public string|int $collapse;

    public function __construct(
        public string $id = 'webx-header',
        string|int $collapse = 'auto',
        public int $breakpoint = 960,
        public string $sticky = 'none',
        public bool $overlay = false,
        public string|bool $skip = '#content',
        public string $menuId = 'webx-mobile-menu',
        public string $side = 'right',
        public string $mode = 'accordion',
        public ?string $label = null,
        public ?string $skipLabel = null,
    ) {
        if (! in_array($sticky, self::STICKY, true)) {
            throw new InvalidArgumentException('A header is sticky as one of: '.implode(', ', self::STICKY).", not \"{$sticky}\".");
        }

        $this->collapse = match (true) {
            is_int($collapse) || ctype_digit($collapse) => (int) $collapse,
            in_array($collapse, ['auto', 'never'], true) => $collapse,
            default => throw new InvalidArgumentException("A header collapses \"auto\", \"never\" or at a width in pixels, not \"{$collapse}\"."),
        };

        if ($skip === true) {
            $this->skip = '#content';
        }
    }

    public function render(): View
    {
        Widgets::need('header');

        // A navigation rendered outside any header earlier on the page must not land in this one.
        app(HeaderNavigation::class)->take();

        return view('webx-widgets::components.header', [
            'navLabel' => $this->label ?? __('webx-widgets::widgets.header.nav'),
            'skipText' => $this->skipLabel ?? __('webx-widgets::widgets.header.skip'),
            // The width the navigation folds at without JavaScript; `auto` has nothing to measure with.
            'foldsAt' => is_int($this->collapse) ? $this->collapse : $this->breakpoint,
        ]);
    }

    /**
     * The tree `<x-webx-header.nav>` drew in the default slot, for the mobile menu; null when the
     * slot holds markup of its own, which the menu then repeats as it is.
     *
     * @return list<Item>|null
     */
    public function navigation(): ?array
    {
        return app(HeaderNavigation::class)->take();
    }
}
