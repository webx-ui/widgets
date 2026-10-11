<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.load_more_link`: "Show more" with a next page and no link to it (§14). The package
 * prints the links of the pages under the list and the button only covers them; a theme's
 * override of `webx-widgets::components.load-more`, or an empty `links` slot, that lost them
 * leaves the items past the first page to a button — no search engine presses it, and a page
 * without JavaScript cannot either.
 */
final class LoadMoreLink extends WidgetsCheck
{
    public const string CHECK = 'widgets.load_more_link';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            $found = (array) ($facts['load_more_linkless'] ?? []);

            if ((int) ($found['count'] ?? 0) > 0) {
                yield $this->onPage($page, 'load-more-link', ['count' => (int) $found['count']], array_map('strval', (array) ($found['markup'] ?? [])));
            }
        }
    }
}
