<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-mobile-menu side="right">` — the menu of a phone: a fixed top, a body that scrolls on
 * its own and a fixed bottom, in a modal `<dialog>` (spec §6.1). What goes inside is the site's;
 * the shell — focus, scroll lock, height under the browser's bars, Back, swipe — is this.
 *
 * `breakpoint` is the width of the container (the header, THEMES §2) from which the trigger is
 * hidden; null shows it always — which is what the header asks for, deciding on its own.
 */
final class MobileMenu extends Component
{
    public const array SIDES = ['left', 'right', 'top', 'bottom', 'full'];

    public function __construct(
        public string $id = 'webx-mobile-menu',
        public string $side = 'right',
        public ?int $breakpoint = 960,
        public bool $closeOnNavigate = true,
        public bool $history = true,
        public ?string $label = null,
        public ?string $title = null,
        public ?string $close = null,
        public ?string $triggerClass = null,
    ) {
        if (! in_array($side, self::SIDES, true)) {
            throw new InvalidArgumentException('A mobile menu opens from one of: '.implode(', ', self::SIDES).", not \"{$side}\".");
        }
    }

    public function render(): View
    {
        Widgets::need('mobile-menu');

        return view('webx-widgets::components.mobile-menu', [
            'openLabel' => $this->label ?? __('webx-widgets::widgets.mobile_menu.open'),
            'titleLabel' => $this->title ?? __('webx-widgets::widgets.mobile_menu.title'),
            'closeLabel' => $this->close ?? __('webx-widgets::widgets.mobile_menu.close'),
        ]);
    }
}
