<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.toc_target`: a link of a table of contents to a section the page does not have (§14).
 * The server makes the list from the page's own headings and gives them their ids, so every link
 * of its list lands; a list a theme's override or a template wrote by hand can point at an id
 * that is gone — a click that goes nowhere.
 */
final class TocTarget extends WidgetsCheck
{
    public const string CHECK = 'widgets.toc_target';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            $found = (array) ($facts['toc_dangling'] ?? []);

            if ((int) ($found['count'] ?? 0) > 0) {
                yield $this->onPage($page, 'toc-target', ['count' => (int) $found['count']], array_map('strval', (array) ($found['markup'] ?? [])));
            }
        }
    }
}
