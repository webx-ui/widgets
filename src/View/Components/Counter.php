<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use NumberFormatter;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-counter :value="3000" suffix="+" />` — "15 years, 3,000 clients" (spec §14): the
 * number counts up when it comes into view.
 *
 * The server prints the final number, written the way the page's language writes it — that is
 * what a search engine indexes and a screen reader says, and what a visitor sees without
 * JavaScript or with reduced motion. The script counts up only a counter below the screen when
 * the page opens: one already in view would flash its number and drop to zero.
 *
 * `value` is a number or a numeric string; its decimals are what it was written with (`4.9` counts
 * in tenths) unless `decimals` says. `prefix` and `suffix` stand beside the number and do not
 * count; `duration` is in milliseconds.
 */
final class Counter extends Component
{
    public float|int $number;

    public int $places;

    /** The final number as the page's language writes it. */
    public string $final;

    public function __construct(
        float|int|string $value,
        public ?string $prefix = null,
        public ?string $suffix = null,
        ?int $decimals = null,
        public int $duration = 2000,
    ) {
        $written = is_string($value) ? trim($value) : $value;

        if (! is_numeric($written)) {
            throw new InvalidArgumentException("<x-webx-counter value=\"{$value}\">: a number — 3000, 4.9.");
        }

        if ($decimals !== null && ($decimals < 0 || $decimals > 6)) {
            throw new InvalidArgumentException("<x-webx-counter :decimals=\"{$decimals}\">: 0 to 6.");
        }

        if ($duration < 0 || $duration > 10000) {
            throw new InvalidArgumentException("<x-webx-counter :duration=\"{$duration}\">: milliseconds, 0 to 10000.");
        }

        $text = is_string($written) ? $written : (string) $written;
        $this->places = $decimals ?? (str_contains($text, '.') && ! str_contains(strtolower($text), 'e') ? strlen(rtrim((string) substr($text, (int) strpos($text, '.') + 1))) : 0);
        $this->number = $this->places === 0 ? (int) round((float) $written) : round((float) $written, $this->places);
        $this->final = self::format($this->number, $this->places, app()->getLocale());
        $this->prefix = self::filled($this->prefix);
        $this->suffix = self::filled($this->suffix);
    }

    public function render(): View
    {
        Widgets::need('counter');

        return view('webx-widgets::components.counter');
    }

    /** Grouped and with the decimal sign of the language; without `intl`, the English way. */
    public static function format(float|int $number, int $places, string $locale): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $places);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $places);
            $formatted = $formatter->format($number);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return number_format((float) $number, $places);
    }

    private static function filled(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? $value : null;
    }
}
