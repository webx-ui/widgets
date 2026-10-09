<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Widgets\Consent;

/**
 * `<x-webx-consent-link />` — "Cookie settings" for the footer: reopens the consent dialog, so
 * taking an answer back is as easy as giving it (spec §9.1). Nothing with the banner off.
 */
final class ConsentLink extends Component
{
    public function __construct(
        public ?string $label = null,
    ) {}

    public function shouldRender(): bool
    {
        return app(Consent::class)->enabled();
    }

    public function render(): View
    {
        return view('webx-widgets::components.consent-link', [
            'dialog' => Consent::DIALOG,
            'text' => $this->label ?? __('webx-widgets::widgets.consent.link'),
        ]);
    }
}
