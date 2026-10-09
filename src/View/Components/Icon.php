<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * `<x-webx-icon name="instagram" />` — one of the package's pictures, inline, in `currentColor`:
 * the networks and messengers (Simple Icons, CC0) and its own phone, chat, mail, clock, link.
 *
 * Each is a view, `webx-widgets::icons.<name>`, of what goes inside a 24×24 `<svg>`: a theme
 * replaces one with its own at `<layer>/views/vendor/webx-widgets/icons/<name>.blade.php`, and
 * every widget that shows it shows the theme's (spec §5). Decorative — the link or the button it
 * stands in carries the words.
 */
final class Icon extends Component
{
    public function __construct(public string $name)
    {
        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1 || ! view()->exists("webx-widgets::icons.{$name}")) {
            throw new InvalidArgumentException("<x-webx-icon name=\"{$name}\">: there is no such icon.");
        }
    }

    public function render(): View
    {
        return view('webx-widgets::components.icon');
    }
}
