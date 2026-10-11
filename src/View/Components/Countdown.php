<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use Throwable;
use WebxUi\Widgets\Contacts\ContactsSource;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-countdown to="2026-12-31 18:00" ended="The sale is over" />` — a timer to a moment
 * (spec §14).
 *
 * A wall time is the site's: the time zone of its opening hours (the "Contacts" tab, WIDGETS
 * §12.1), else the application's — the sale ends at six where the shop is, wherever the visitor
 * reads it. A moment with its offset (`2026-12-31T18:00:00+02:00`, a `DateTimeInterface`) is that
 * moment. The server prints the time left at the response; the script counts it down every second,
 * because the page may lie in a cache for hours.
 *
 * At the end the `ended` text takes the timer's place; without it the timer goes — and so does the
 * nearest `[data-webx-countdown-scope]` around it, the block it is the point of. A countdown over
 * before the response with nothing to say prints nothing at all.
 *
 * Without JavaScript the frozen digits would lie: the line "Ends on <date>" stands instead. With it
 * that line is what a screen reader is told, and the ticking digits are hidden from it.
 */
final class Countdown extends Component
{
    public const array UNITS = ['days' => 86400, 'hours' => 3600, 'minutes' => 60, 'seconds' => 1];

    public CarbonImmutable $end;

    public bool $over;

    /** @var array<string, string> unit → the digits left at the response */
    public array $left = [];

    public string $date;

    public function __construct(
        DateTimeInterface|string $to,
        public ?string $ended = null,
    ) {
        $this->end = self::moment($to);
        $this->ended = $ended !== null && trim($ended) !== '' ? $ended : null;
        $seconds = max(0, $this->end->getTimestamp() - CarbonImmutable::now()->getTimestamp());
        $this->over = $seconds === 0;

        foreach (self::UNITS as $unit => $length) {
            $value = intdiv($seconds, $length);
            $seconds -= $value * $length;
            $this->left[$unit] = $unit === 'days' ? (string) $value : str_pad((string) $value, 2, '0', STR_PAD_LEFT);
        }

        $this->date = $this->end->locale(app()->getLocale())->isoFormat('LLL').' (UTC'.$this->end->format('P').')';
    }

    public function shouldRender(): bool
    {
        return ! $this->over || $this->ended !== null;
    }

    public function render(): View
    {
        Widgets::need('countdown');

        return view('webx-widgets::components.countdown');
    }

    /**
     * The moment a countdown runs to, in the site's time zone — what a block's template asks
     * before it prints a countdown that may already be over.
     */
    public static function moment(DateTimeInterface|string $to): CarbonImmutable
    {
        $zone = self::siteZone();

        if ($to instanceof DateTimeInterface) {
            return CarbonImmutable::instance($to)->setTimezone($zone);
        }

        try {
            if (trim($to) === '') {
                throw new InvalidArgumentException('empty');
            }

            return CarbonImmutable::parse($to, $zone)->setTimezone($zone);
        } catch (Throwable) {
            throw new InvalidArgumentException("<x-webx-countdown to=\"{$to}\">: a date and time — \"2026-12-31 18:00\" in the site's time zone, or with its offset.");
        }
    }

    /** The zone of the site's opening hours, else the application's. */
    public static function siteZone(): DateTimeZone
    {
        $contacts = ContactsSource::get();

        if ($contacts !== null) {
            return $contacts->hours()->timezone();
        }

        $zone = config('app.timezone');

        try {
            return new DateTimeZone(is_string($zone) && $zone !== '' ? $zone : 'UTC');
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
