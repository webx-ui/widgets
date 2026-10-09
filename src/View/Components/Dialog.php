<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-dialog id="…" title="…">` — a modal `<dialog>` any link or button opens with
 * `data-webx-dialog="<id>"` (spec §6). Without JavaScript the link `#<id>` shows it through
 * `:target`, and its close link goes back to the page.
 */
final class Dialog extends Component
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $close = null,
    ) {}

    public function render(): View
    {
        Widgets::need('dialog');

        return view('webx-widgets::components.dialog', [
            'closeLabel' => $this->close ?? __('webx-widgets::widgets.dialog.close'),
        ]);
    }
}
