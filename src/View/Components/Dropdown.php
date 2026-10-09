<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-dropdown placement="bottom-start" open-on="click">` with `<x-slot:trigger>` — a panel
 * tied to its button (spec §6.3): the phones, the hours, the languages, "share". A `<details>`,
 * so without JavaScript a click opens it under the trigger; the runtime adds the rest.
 */
final class Dropdown extends Component
{
    public const array PLACEMENTS = ['bottom-start', 'bottom-end', 'bottom', 'top-start', 'top-end', 'top'];

    public const array OPEN_ON = ['click', 'hover'];

    public function __construct(
        public string $placement = 'bottom-start',
        public string $openOn = 'click',
        public ?string $label = null,
        public bool $open = false,
    ) {
        // A typo here would be a dropdown that opens in the wrong place without a word.
        if (! in_array($placement, self::PLACEMENTS, true)) {
            throw new InvalidArgumentException("<x-webx-dropdown placement=\"{$placement}\">: one of ".implode(', ', self::PLACEMENTS).'.');
        }

        if (! in_array($openOn, self::OPEN_ON, true)) {
            throw new InvalidArgumentException("<x-webx-dropdown open-on=\"{$openOn}\">: click or hover.");
        }
    }

    public function render(): View
    {
        Widgets::need('dropdown');

        return view('webx-widgets::components.dropdown');
    }
}
