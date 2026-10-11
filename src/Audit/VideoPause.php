<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.video_pause`: a background video with no pause button in it (§10, WCAG 2.2.2). The
 * package's view always prints one; a theme's override of `webx-widgets::components.video` that
 * lost `.webx-video__pause` is what this finds.
 */
final class VideoPause extends WidgetsCheck
{
    public const string CHECK = 'widgets.video_pause';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            $found = (array) ($facts['video_unpaused'] ?? []);

            if ((int) ($found['count'] ?? 0) > 0) {
                yield $this->onPage($page, 'video-pause', ['count' => (int) $found['count']], array_map('strval', (array) ($found['markup'] ?? [])));
            }
        }
    }
}
