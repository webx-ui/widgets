<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use NumberFormatter;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-compare :before="$old" :after="$new" />` — before and after (spec §14): one picture
 * over the other and a divider between them, moved by the mouse, a finger and the arrow keys.
 * Clinics, renovation, cosmetics.
 *
 * `before` and `after` are what a template has in hand, as for `<x-webx-lightbox :image>`: a value
 * of a media field of the library (`url`, `alt`, `width`, `height`), an object with `url()`, or an
 * address. The frame takes the shape of the first picture with sizes — `ratio` says it by hand —
 * so nothing below it moves while the pictures load. `start` is where the divider stands, in
 * percent from the before side; the labels default to the words of the page's language.
 *
 * The divider is an `<input type="range">`: a screen reader hears a slider and its value, and the
 * keyboard has the arrows, Home, End and Page Up/Down without a line of script. Without
 * JavaScript at all the two pictures stand side by side, each under its label.
 */
final class Compare extends Component
{
    public string $beforeUrl;

    public string $afterUrl;

    public string $beforeAlt;

    public string $afterAlt;

    public ?int $beforeWidth;

    public ?int $beforeHeight;

    public ?int $afterWidth;

    public ?int $afterHeight;

    /** Where the divider stands, in percent from the before side. */
    public float $position;

    /** The position as the page's language writes a percentage: what a screen reader says. */
    public string $valueText;

    /** The value of `aspect-ratio`: `W / H`. */
    public string $shape;

    public string $beforeText;

    public string $afterText;

    public string $sliderLabel;

    public function __construct(
        mixed $before = null,
        mixed $after = null,
        int|float|string $start = 50,
        float|int|string|null $ratio = null,
        ?string $beforeLabel = null,
        ?string $afterLabel = null,
        ?string $label = null,
    ) {
        $this->beforeUrl = self::filled(self::read($before, 'url')) ?? (is_string($before) ? self::filled($before) : null)
            ?? throw new InvalidArgumentException('<x-webx-compare>: no `before` picture — a media value, an object with url() or an address.');
        $this->afterUrl = self::filled(self::read($after, 'url')) ?? (is_string($after) ? self::filled($after) : null)
            ?? throw new InvalidArgumentException('<x-webx-compare>: no `after` picture — a media value, an object with url() or an address.');

        if (! is_numeric($start) || (float) $start < 0 || (float) $start > 100) {
            throw new InvalidArgumentException("<x-webx-compare start=\"{$start}\">: percent from the before side, 0 to 100.");
        }

        $this->position = round((float) $start, 1);
        $this->valueText = self::percent($this->position, app()->getLocale());
        $this->beforeWidth = self::size(self::read($before, 'width'));
        $this->beforeHeight = self::size(self::read($before, 'height'));
        $this->afterWidth = self::size(self::read($after, 'width'));
        $this->afterHeight = self::size(self::read($after, 'height'));
        $this->beforeAlt = is_string($alt = self::read($before, 'alt')) ? $alt : '';
        $this->afterAlt = is_string($alt = self::read($after, 'alt')) ? $alt : '';
        $this->shape = self::shape($ratio, [[$this->beforeWidth, $this->beforeHeight], [$this->afterWidth, $this->afterHeight]]);
        $this->beforeText = self::filled($beforeLabel) ?? (string) __('webx-widgets::widgets.compare.before');
        $this->afterText = self::filled($afterLabel) ?? (string) __('webx-widgets::widgets.compare.after');
        $this->sliderLabel = self::filled($label) ?? (string) __('webx-widgets::widgets.compare.label', ['before' => $this->beforeText, 'after' => $this->afterText]);
    }

    public function render(): View
    {
        Widgets::need('compare');

        return view('webx-widgets::components.compare');
    }

    /**
     * `16/9`, `4:3`, a number — or the first picture that knows its sizes; 4 / 3 when neither
     * does, a frame that holds its height all the same.
     *
     * @param  list<array{0: ?int, 1: ?int}>  $sizes
     */
    private static function shape(float|int|string|null $ratio, array $sizes): string
    {
        if ($ratio !== null && $ratio !== '') {
            if (is_string($ratio) && preg_match('~^\s*(\d+(?:\.\d+)?)\s*[/:]\s*(\d+(?:\.\d+)?)\s*$~', $ratio, $parts) === 1 && (float) $parts[1] > 0 && (float) $parts[2] > 0) {
                return ((string) (float) $parts[1]).' / '.((string) (float) $parts[2]);
            }

            if (is_numeric($ratio) && (float) $ratio > 0) {
                return ((string) (float) $ratio).' / 1';
            }

            throw new InvalidArgumentException("<x-webx-compare>: a ratio is \"16/9\", \"4:3\" or a number, not \"{$ratio}\".");
        }

        foreach ($sizes as [$width, $height]) {
            if ($width !== null && $height !== null) {
                return "{$width} / {$height}";
            }
        }

        return '4 / 3';
    }

    /** `50 %` in German, `%50` in Turkish; without `intl`, the English way. */
    public static function percent(float $value, string $locale): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::PERCENT);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);
            $formatted = $formatter->format(round($value) / 100);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return round($value).'%';
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
