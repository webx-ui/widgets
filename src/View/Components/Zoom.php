<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-zoom :image="$photo" mode="side" />` — a picture whose details come closer (spec §14):
 * a mouse over it magnifies it inside its frame (`inner`) or in a panel beside it (`side`, which
 * is `inner` where its container leaves no room beside); two fingers pinch and pan it, a double
 * tap is 2× and back. A keyboard and a screen reader have no hover: the picture is a link that
 * opens the lightbox (§8), and without JavaScript a link to the original.
 *
 * `image` is what a template has in hand, as for `<x-webx-lightbox :image>`: a value of a media
 * field of the library, a product's photo, any object with `url()` and `width`/`height`. Its
 * address is the large picture — the original, fetched on the first hover or touch only. `src`
 * is the picture shown, a smaller copy where the template has one (a product photo's
 * `thumbUrl()`); without it the original is shown and magnified alike.
 *
 * `data-webx-zoom` on any element that holds a picture does the same without the component: a
 * gallery's lightbox link, a slide (§14, WIDGETS §20 W5.5).
 */
final class Zoom extends Component
{
    public const array MODES = ['inner', 'side'];

    /** An element that asks for the zoom, wherever on the page it was written. */
    private const string ELEMENT = '~<[a-z][a-z0-9-]*\b[^>]*\sdata-webx-zoom(?:\s*=|[\s>/])~i';

    public string $large;

    public string $shown;

    public ?int $pictureWidth;

    public ?int $pictureHeight;

    public string $text;

    public string $name;

    public string $magnify;

    public function __construct(
        mixed $image = null,
        ?string $src = null,
        ?string $large = null,
        int|string|null $width = null,
        int|string|null $height = null,
        ?string $alt = null,
        public string $mode = 'inner',
        int|float|string $scale = 2.5,
        public string $group = '',
        ?string $label = null,
        public bool $eager = false,
    ) {
        $this->large = self::filled($large) ?? self::filled(self::read($image, 'url')) ?? self::filled($src)
            ?? throw new InvalidArgumentException('<x-webx-zoom>: no picture — an `image` with a url, `large` or `src`.');

        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("<x-webx-zoom mode=\"{$mode}\">: inner or side.");
        }

        if (! is_numeric($scale) || (float) $scale < 1.25 || (float) $scale > 6) {
            throw new InvalidArgumentException("<x-webx-zoom scale=\"{$scale}\">: how many times larger, 1.25 to 6.");
        }

        $this->magnify = (string) round((float) $scale, 2);
        $this->shown = self::filled($src) ?? $this->large;
        $this->pictureWidth = self::size($width ?? self::read($image, 'width'));
        $this->pictureHeight = self::size($height ?? self::read($image, 'height'));
        $this->text = $alt ?? (is_string($said = self::read($image, 'alt')) ? $said : '');
        $this->name = self::filled($label) ?? ($this->text !== ''
            ? (string) __('webx-widgets::widgets.zoom.named', ['name' => $this->text])
            : (string) __('webx-widgets::widgets.zoom.label'));
    }

    public function render(): View
    {
        Widgets::need('zoom');
        Widgets::need('lightbox');

        return view('webx-widgets::components.zoom');
    }

    /** Whether an element on the page asks for the zoom — a gallery's link, a slide, a module's view. */
    public static function wanted(string $html): bool
    {
        return preg_match(self::ELEMENT, $html) === 1;
    }

    /** A key of an array, a method or a property of an object; nothing else has one. */
    private static function read(mixed $image, string $key): mixed
    {
        return match (true) {
            is_array($image) => $image[$key] ?? null,
            is_object($image) && method_exists($image, $key) => $image->{$key}(),
            is_object($image) && isset($image->{$key}) => $image->{$key},
            default => null,
        };
    }

    private static function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function size(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
