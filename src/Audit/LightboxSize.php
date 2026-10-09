<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.lightbox_size`: a lightbox link without the picture's size (§8: `data-width` and
 * `data-height` are required). It still opens — the script loads the picture first to measure
 * it (W2.2) — but that is a download before the lightbox and a jump when it opens. Pictures of
 * the library carry their size: `<x-webx-lightbox :image>` prints it; a link written by hand
 * does not.
 */
final class LightboxSize extends WidgetsCheck
{
    public const string CHECK = 'widgets.lightbox_size';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            $found = (array) ($facts['lightbox_unsized'] ?? []);

            if ((int) ($found['count'] ?? 0) > 0) {
                yield $this->onPage($page, 'lightbox-size', ['count' => (int) $found['count']], array_map('strval', (array) ($found['markup'] ?? [])));
            }
        }
    }
}
