<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Settings\Contacts\Channel;
use WebxUi\Widgets\Contacts\ContactsSource;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-socials />` — a row of the site's networks (spec §12.5), from the Contacts tab or
 * `:socials`. Each is an icon (`<x-webx-icon>`, which a theme replaces with its own) and a link
 * with the network's name for a screen reader, opening in a new tab with `rel="noopener"`.
 */
final class Socials extends Component
{
    /** @var list<Channel> */
    public array $networks;

    /**
     * @param  list<Channel>|null  $socials
     */
    public function __construct(
        ?array $socials = null,
        public ?string $label = null,
    ) {
        $this->networks = $socials ?? ContactsSource::get()?->socials() ?? [];
    }

    public function shouldRender(): bool
    {
        return $this->networks !== [];
    }

    public function render(): View
    {
        Widgets::need('contacts');

        return view('webx-widgets::components.socials', [
            'labelText' => $this->label ?? __('webx-widgets::widgets.socials.label'),
        ]);
    }
}
