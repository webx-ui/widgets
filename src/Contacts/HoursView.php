<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Contacts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use WebxUi\Settings\Contacts\Hours;

/**
 * The opening hours in words (spec §12.3): "Open until 19:00", "Mon–Fri 9:00–19:00".
 *
 * Times and days are written on the server in the page's language — 19:00 or 7:00 PM as that
 * language writes them — and never by the browser's own locale, the way the panel's dates are
 * (`useDates()`). The script that brings the status up to date in a page cached for hours gets
 * the same words, ready: it picks one, it formats nothing.
 */
final class HoursView
{
    /** The kinds of `HoursStatus::kind()`, each a sentence of the dictionary. */
    public const array KINDS = ['open', 'always', 'today', 'tomorrow', 'later', 'closed', 'off', 'off-tomorrow', 'off-later'];

    /**
     * @param  array<string, string>  $words  sentences that replace the dictionary's, by kind
     */
    public function __construct(
        private readonly Hours $hours,
        private readonly string $locale,
        private readonly array $words = [],
    ) {}

    /**
     * @return array{kind: string, text: string, open: bool}
     */
    public function status(?DateTimeInterface $at = null): array
    {
        $status = $this->hours->status($at);
        $kind = $status->kind();
        $moment = $status->until ?? $status->next;

        return [
            'kind' => $kind,
            'open' => $status->open,
            'text' => strtr($this->sentence($kind), [
                ':time' => $moment === null ? '' : $this->time($moment->hour * 60 + $moment->minute),
                ':day' => $moment === null ? '' : $this->day($moment->isoWeekday(), true),
            ]),
        ];
    }

    /**
     * The week, folded: a row per run of days with the same hours.
     *
     * @return list<array{days: string, hours: string, weekdays: list<int>, today: bool}>
     */
    public function rows(?DateTimeInterface $at = null): array
    {
        $today = $this->today($at);
        $rows = [];

        foreach ($this->hours->rows() as $row) {
            $first = $row['days'][0];
            $last = $row['days'][array_key_last($row['days'])];

            $rows[] = [
                'days' => $first === $last ? $this->day($first) : $this->day($first).'–'.$this->day($last),
                'hours' => $this->intervals($row['intervals']),
                'weekdays' => $row['days'],
                'today' => in_array($today->isoWeekday(), $row['days'], true) && ! isset($this->hours->exceptions()[$today->format('Y-m-d')]),
            ];
        }

        return $rows;
    }

    /**
     * The special dates of the next month, so that nobody comes on a holiday.
     *
     * @return list<array{date: string, iso: string, hours: string, label: string|null, today: bool}>
     */
    public function special(?DateTimeInterface $at = null): array
    {
        $today = $this->today($at)->format('Y-m-d');
        $dates = [];

        foreach ($this->hours->upcoming($at) as $date => $exception) {
            $dates[] = [
                'date' => CarbonImmutable::parse($date)->locale($this->locale)->isoFormat('ddd, D MMM'),
                'iso' => $date,
                'hours' => $this->intervals($exception['intervals']),
                'label' => $exception['label'],
                'today' => $date === $today,
            ];
        }

        return $dates;
    }

    /**
     * What the script needs to say the same as the server at another moment: the hours, and every
     * word it could print — each time of a boundary and each day, already written.
     *
     * @return array<string, mixed>
     */
    public function script(): array
    {
        $minutes = [];

        foreach ([...$this->hours->week(), ...array_column($this->hours->exceptions(), 'intervals')] as $intervals) {
            foreach ($intervals as [$opens, $closes]) {
                $minutes[$opens % 1440] = true;
                $minutes[$closes % 1440] = true;
            }
        }

        $times = [];

        foreach (array_keys($minutes) as $minute) {
            $times[(string) $minute] = $this->time($minute);
        }

        $days = [];

        foreach (range(1, 7) as $day) {
            $days[(string) $day] = $this->day($day, true);
        }

        $words = [];

        foreach (self::KINDS as $kind) {
            $words[$kind] = $this->sentence($kind);
        }

        return [
            'zone' => $this->hours->timezone()->getName(),
            'week' => (object) $this->hours->week(),
            'special' => (object) array_map(static fn (array $exception): array => $exception['intervals'], $this->hours->exceptions()),
            'times' => (object) $times,
            'days' => (object) $days,
            'words' => $words,
        ];
    }

    public function time(int $minutes): string
    {
        $minutes %= 1440;

        return CarbonImmutable::create(2000, 1, 1, intdiv($minutes, 60), $minutes % 60, 0, 'UTC')->locale($this->locale)->isoFormat('LT');
    }

    /** A weekday's name, short (Mon) for the table, whole (Monday) for a sentence. 2024-01-01 was a Monday. */
    public function day(int $weekday, bool $whole = false): string
    {
        return CarbonImmutable::create(2024, 1, $weekday, 12, 0, 0, 'UTC')->locale($this->locale)->isoFormat($whole ? 'dddd' : 'ddd');
    }

    /** @param  list<array{0: int, 1: int}>  $intervals */
    private function intervals(array $intervals): string
    {
        if ($intervals === []) {
            return $this->word('day-off');
        }

        return implode(', ', array_map(
            fn (array $interval): string => $interval[1] - $interval[0] >= 1440 ? $this->word('all-day') : $this->time($interval[0]).'–'.$this->time($interval[1]),
            $intervals,
        ));
    }

    private function sentence(string $kind): string
    {
        return $this->words[$kind] ?? $this->word($kind);
    }

    private function word(string $key): string
    {
        return (string) __("webx-widgets::widgets.hours.{$key}", [], $this->locale);
    }

    private function today(?DateTimeInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at ?? CarbonImmutable::now())->setTimezone($this->hours->timezone());
    }
}
