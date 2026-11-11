<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-photos :images="$pictures" ratio="3/2" />` — the photos of a card (spec §14): one frame,
 * the photos changing in it on their own, a bar under each that fills while it is shown and takes
 * a click, a swipe for a finger and a pause button. A flat on a showcase, a room of a hotel, a car.
 *
 * `images` is a list of what a template has in hand, as for `<x-webx-compare>`: values of a media
 * field (`url`, `alt`, `width`, `height`), objects with `url()`, or addresses; the empty ones are
 * left out. With one photo it is a plain `<img>` — the attributes on it, no script claimed; with
 * none, nothing at all. The frame takes `ratio`, or the shape of the first photo with sizes.
 *
 * `autoplay` is milliseconds between photos — 5000 by default, `false` for none, and then no pause
 * button either. It does not start with `prefers-reduced-motion`; the button offers to.
 *
 * Without JavaScript the first photo stands alone. A card that is a link, its link stretched over
 * it, says so with `data-webx-photos-surface` on the card: the swipe is read off the card where it
 * lies over the frame, and a tap still follows the link.
 */
final class Photos extends Component
{
    public const int DELAY = 5000;

    /** @var list<array{url: string, alt: string, width: ?int, height: ?int}> */
    public array $photos = [];

    /** Milliseconds between photos; 0 — they change only when asked. */
    public int $delay;

    /** The value of `aspect-ratio`: `W / H`. */
    public string $shape;

    public string $name;

    /** @var array<string, string> */
    public array $words;

    /**
     * @param  iterable<mixed>|null  $images
     */
    public function __construct(
        ?iterable $images = null,
        bool|int|string|null $autoplay = null,
        float|int|string|null $ratio = null,
        ?string $label = null,
        public bool $eager = false,
    ) {
        foreach ($images ?? [] as $image) {
            $url = self::filled(self::read($image, 'url')) ?? (is_string($image) ? self::filled($image) : null);

            if ($url === null) {
                continue;
            }

            $alt = self::read($image, 'alt');
            $this->photos[] = [
                'url' => $url,
                'alt' => is_string($alt) ? $alt : '',
                'width' => self::size(self::read($image, 'width')),
                'height' => self::size(self::read($image, 'height')),
            ];
        }

        $this->delay = self::delay($autoplay);
        $this->shape = self::shape($ratio, $this->photos);
        $this->name = self::filled($label) ?? (string) __('webx-widgets::widgets.photos.label');
        $this->words = [
            'show' => (string) __('webx-widgets::widgets.photos.show'),
            'pause' => (string) __('webx-widgets::widgets.slider.pause'),
            'play' => (string) __('webx-widgets::widgets.slider.play'),
        ];
    }

    public function shouldRender(): bool
    {
        return $this->photos !== [];
    }

    public function render(): View
    {
        // One photo has nothing to change: a picture, and no script for it.
        if (count($this->photos) > 1) {
            Widgets::need('photos');
        }

        return view('webx-widgets::components.photos');
    }

    /** What the bar of photo `$index` (from 1) is called: "Photo 2 of 5". */
    public function bar(int $index): string
    {
        return strtr($this->words['show'], [':index' => (string) $index, ':count' => (string) count($this->photos)]);
    }

    private static function delay(bool|int|string|null $autoplay): int
    {
        return match (true) {
            $autoplay === null, $autoplay === true, $autoplay === 'true', $autoplay === '' => self::DELAY,
            $autoplay === false, $autoplay === 'false' => 0,
            is_int($autoplay) && $autoplay >= 0, is_string($autoplay) && ctype_digit($autoplay) => (int) $autoplay,
            default => throw new InvalidArgumentException("<x-webx-photos autoplay>: milliseconds, true or false, not \"{$autoplay}\"."),
        };
    }

    /**
     * `3/2`, `4:3`, a number — or the first photo that knows its sizes; 4 / 3 when none does.
     *
     * @param  list<array{url: string, alt: string, width: ?int, height: ?int}>  $photos
     */
    private static function shape(float|int|string|null $ratio, array $photos): string
    {
        if ($ratio !== null && $ratio !== '') {
            if (is_string($ratio) && preg_match('~^\s*(\d+(?:\.\d+)?)\s*[/:]\s*(\d+(?:\.\d+)?)\s*$~', $ratio, $parts) === 1 && (float) $parts[1] > 0 && (float) $parts[2] > 0) {
                return ((string) (float) $parts[1]).' / '.((string) (float) $parts[2]);
            }

            if (is_numeric($ratio) && (float) $ratio > 0) {
                return ((string) (float) $ratio).' / 1';
            }

            throw new InvalidArgumentException("<x-webx-photos>: a ratio is \"3/2\", \"4:3\" or a number, not \"{$ratio}\".");
        }

        foreach ($photos as $photo) {
            if ($photo['width'] !== null && $photo['height'] !== null) {
                return "{$photo['width']} / {$photo['height']}";
            }
        }

        return '4 / 3';
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
