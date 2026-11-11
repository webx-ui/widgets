<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-tabs>` with `<x-webx-tabs.panel title="…">` inside (spec §6). The markup is what a
 * visitor without JavaScript reads — a heading above each panel; the runtime turns the headings
 * into a tab list. `transition="fade"` cross-fades the panels and keeps them in one cell, so the
 * block is as tall as its tallest panel and does not jump between tabs; anything else switches at
 * once, as before.
 */
final class Tabs extends Component
{
    public function __construct(
        public ?string $label = null,
        public ?string $transition = null,
    ) {}

    public function render(): View
    {
        Widgets::need('tabs');

        return view('webx-widgets::components.tabs', [
            'fade' => $this->transition === 'fade',
        ]);
    }
}
