<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-back-to-top />` — a round button in a corner of the window that takes the page back
 * up (spec §14). It comes once the page has been scrolled `after` screens down (two by default),
 * scrolls smoothly unless the visitor asked for reduced motion, and leaves the focus at the top of
 * the page, where a keyboard goes on from. While the cookie banner, the quick-contact button of the
 * same corner or the contact bar is on the screen, it stands above them.
 *
 * Without JavaScript it is a link to `#top` where the template wrote it — the footer, usually —
 * and the browser goes up by itself.
 */
final class BackToTop extends Component
{
    public function __construct(
        public string $corner = 'bottom-end',
        public int|float $after = 2,
        public ?string $label = null,
    ) {
        if (! in_array($corner, ContactButton::CORNERS, true)) {
            throw new InvalidArgumentException("<x-webx-back-to-top corner=\"{$corner}\">: bottom-end or bottom-start.");
        }

        if ($after < 0 || $after > 20) {
            throw new InvalidArgumentException("<x-webx-back-to-top :after=\"{$after}\">: the screens scrolled before it shows, 0 to 20.");
        }
    }

    public function render(): View
    {
        Widgets::need('back-to-top');

        return view('webx-widgets::components.back-to-top', [
            'labelText' => $this->label ?? __('webx-widgets::widgets.back_to_top.label'),
        ]);
    }
}
