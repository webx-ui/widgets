<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-lightbox :image="$picture" group="product-1" />` — a picture that opens over the page
 * (spec §8): a link to the picture with `data-webx-lightbox` and its sizes, its thumbnail inside.
 *
 * `image` is what a template has in hand: a value of a media field of the library (`url`,
 * `thumb`, `alt`, `width`, `height` — the sizes are stored at upload, so they are always there),
 * or any object with `url()` or `url` and `width`/`height`, a product's photo among them. The
 * props `src`, `width`, `height`, `thumb`, `alt` say the same by hand, or over it. The slot, when
 * given, is what the link shows in place of the thumbnail.
 *
 * A link written by hand with the same attributes needs no component: the page finds it (§8).
 */
final class Lightbox extends Component
{
    /** A link that asks for the lightbox, wherever on the page it was written. */
    private const string LINK = '~<a\b[^>]*\sdata-webx-lightbox(?:\s*=|[\s>])~i';

    public ?string $href;

    public ?int $pictureWidth;

    public ?int $pictureHeight;

    public ?string $preview;

    public string $text;

    public function __construct(
        mixed $image = null,
        ?string $src = null,
        int|string|null $width = null,
        int|string|null $height = null,
        ?string $thumb = null,
        ?string $alt = null,
        public string $group = '',
    ) {
        $this->href = self::filled($src) ?? self::filled(self::read($image, 'url')) ?? self::filled(self::read($image, 'src'));

        if ($this->href === null) {
            throw new InvalidArgumentException('<x-webx-lightbox>: no picture — an `image` with a url, or `src`.');
        }

        $this->pictureWidth = self::size($width ?? self::read($image, 'width'));
        $this->pictureHeight = self::size($height ?? self::read($image, 'height'));
        $this->preview = self::filled($thumb) ?? self::filled(self::read($image, 'thumb')) ?? $this->href;
        $this->text = $alt ?? (is_string($said = self::read($image, 'alt')) ? $said : '');
    }

    public function render(): View
    {
        Widgets::need('lightbox');

        return view('webx-widgets::components.lightbox');
    }

    /** Whether a link on the page asks for the lightbox — written by hand, in a block, in a module's view. */
    public static function wanted(string $html): bool
    {
        return preg_match(self::LINK, $html) === 1;
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
