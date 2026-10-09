<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** One panel of `<x-webx-tabs>`: its title is the heading without JavaScript and the tab with it. */
final class TabsPanel extends Component
{
    public function __construct(
        public string $title,
        public bool $selected = false,
        public int $level = 3,
    ) {}

    public function render(): View
    {
        return view('webx-widgets::components.tabs.panel', [
            'heading' => 'h'.min(6, max(2, $this->level)),
        ]);
    }
}
