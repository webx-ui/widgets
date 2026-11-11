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
 * `ratio="4:3"` opens the picture in a frame of that shape, whole inside it (nothing — the picture
 * as it is — by default); `opacity` is the backdrop's, over `--webx-lightbox-backdrop-opacity`.
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

    /** The frame's shape as `W:H`, or none: the picture as it is. */
    public ?string $frame;

    public ?string $backdrop;

    public function __construct(
        mixed $image = null,
        ?string $src = null,
        int|string|null $width = null,
        int|string|null $height = null,
        ?string $thumb = null,
        ?string $alt = null,
        public string $group = '',
        ?string $ratio = null,
        int|float|string|null $opacity = null,
    ) {
        $this->href = self::filled($src) ?? self::filled(self::read($image, 'url')) ?? self::filled(self::read($image, 'src'));

        if ($this->href === null) {
            throw new InvalidArgumentException('<x-webx-lightbox>: no picture — an `image` with a url, or `src`.');
        }

        $this->pictureWidth = self::size($width ?? self::read($image, 'width'));
        $this->pictureHeight = self::size($height ?? self::read($image, 'height'));
        $this->preview = self::filled($thumb) ?? self::filled(self::read($image, 'thumb')) ?? $this->href;
        $this->text = $alt ?? (is_string($said = self::read($image, 'alt')) ? $said : '');
        $this->frame = self::ratio($ratio);
        $this->backdrop = is_numeric($opacity) ? (string) max(0, min(1, (float) $opacity)) : null;
    }

    /**
     * `4:3`, `4/3`, `16 : 9` as `W:H`. Anything else — empty, `auto`, a word, a zero — is no frame:
     * the block's select says `auto` for "as is", and a typo shows the picture as it always was.
     */
    public static function ratio(?string $value): ?string
    {
        if ($value === null || preg_match('~^\s*(\d+(?:\.\d+)?)\s*[:/]\s*(\d+(?:\.\d+)?)\s*$~', $value, $match) !== 1) {
            return null;
        }

        return (float) $match[1] > 0 && (float) $match[2] > 0 ? $match[1].':'.$match[2] : null;
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
