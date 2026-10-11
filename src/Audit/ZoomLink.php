<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.zoom_link`: a zoom with no link (§14). A keyboard and a screen reader have no hover:
 * the package's view makes the picture a link to the original that opens the lightbox, and the
 * gallery puts the attribute on its lightbox link. A theme's override of
 * `webx-widgets::components.zoom`, or `data-webx-zoom` written on a bare picture, leaves a zoom
 * only a mouse and a finger can use.
 */
final class ZoomLink extends WidgetsCheck
{
    public const string CHECK = 'widgets.zoom_link';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            $found = (array) ($facts['zoom_linkless'] ?? []);

            if ((int) ($found['count'] ?? 0) > 0) {
                yield $this->onPage($page, 'zoom-link', ['count' => (int) $found['count']], array_map('strval', (array) ($found['markup'] ?? [])));
            }
        }
    }
}
