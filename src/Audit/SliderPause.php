<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.slider_pause`: a slider that moves by itself — autoplay or a running strip — with no
 * pause button in it (§7, WCAG 2.2.2). The package's view always prints one; a theme's override
 * of `webx-widgets::components.slider` that lost `.webx-slider__pause` is what this finds.
 */
final class SliderPause extends WidgetsCheck
{
    public const string CHECK = 'widgets.slider_pause';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            $found = (array) ($facts['slider_unpaused'] ?? []);

            if ((int) ($found['count'] ?? 0) > 0) {
                yield $this->onPage($page, 'slider-pause', ['count' => (int) $found['count']], array_map('strval', (array) ($found['markup'] ?? [])));
            }
        }
    }
}
