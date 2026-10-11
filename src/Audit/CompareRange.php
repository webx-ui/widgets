<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.compare_range`: before and after with no range input in it (§14). The package's view
 * makes the divider an `<input type="range">` — the keyboard's arrows and a slider a screen
 * reader names; a theme's override of `webx-widgets::components.compare` that lost it leaves a
 * divider only a mouse and a finger can move.
 */
final class CompareRange extends WidgetsCheck
{
    public const string CHECK = 'widgets.compare_range';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            $found = (array) ($facts['compare_rangeless'] ?? []);

            if ((int) ($found['count'] ?? 0) > 0) {
                yield $this->onPage($page, 'compare-range', ['count' => (int) $found['count']], array_map('strval', (array) ($found['markup'] ?? [])));
            }
        }
    }
}
