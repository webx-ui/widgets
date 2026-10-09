<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-tabs>` with `<x-webx-tabs.panel title="…">` inside (spec §6). The markup is what a
 * visitor without JavaScript reads — a heading above each panel; the runtime turns the headings
 * into a tab list.
 */
final class Tabs extends Component
{
    public function __construct(
        public ?string $label = null,
    ) {}

    public function render(): View
    {
        Widgets::need('tabs');

        return view('webx-widgets::components.tabs');
    }
}
