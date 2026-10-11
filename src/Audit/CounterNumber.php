<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.counter_number`: a counter whose markup does not hold the number it counts to (§14).
 * The package's view prints the final number and lets the script count up to it; an override
 * that prints `0` and leaves the number to the script shows search engines, screen readers and
 * a page without JavaScript a zero.
 */
final class CounterNumber extends WidgetsCheck
{
    public const string CHECK = 'widgets.counter_number';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            $found = (array) ($facts['counter_empty'] ?? []);

            if ((int) ($found['count'] ?? 0) > 0) {
                yield $this->onPage($page, 'counter-number', ['count' => (int) $found['count']], array_map('strval', (array) ($found['markup'] ?? [])));
            }
        }
    }
}
