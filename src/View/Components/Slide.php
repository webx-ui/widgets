<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use WebxUi\Widgets\View\Sliders;

/**
 * `<x-webx-slide>` — one slide of `<x-webx-slider>`, anything inside (spec §7).
 *
 * Its pictures load at once only if it is among the first in view when the page opens; the rest
 * get `loading="lazy"`, unless they say otherwise. `thumb` is the picture of its thumbnail in
 * `gallery` — by default its first image.
 */
final class Slide extends Component
{
    public int $index = 1;

    public bool $eager = true;

    public function __construct(public ?string $thumb = null) {}

    public function render(): View
    {
        ['index' => $this->index, 'eager' => $this->eager] = app(Sliders::class)->slide();

        return view('webx-widgets::components.slide');
    }

    /** Every `<img>` that does not choose for itself loads when it comes near the screen. */
    public static function lazy(string $html): HtmlString
    {
        return new HtmlString((string) preg_replace('/<img\b(?![^>]*\sloading\s*=)/i', '<img loading="lazy"', $html));
    }
}
