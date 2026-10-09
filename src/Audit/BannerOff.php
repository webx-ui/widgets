<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Widgets\Consent;

/**
 * `widgets.banner_off`: the consent banner is switched off — the Cookie tab of the settings, or
 * `webx-widgets.consent.enabled` without it — while the site has something third-party: a video,
 * a map, a counter, anything marked to wait for consent or asked for at once (§9.5: "the audit
 * will notice"). One finding for the site, with the pages that have it.
 *
 * A warning, not an error: off is allowed for a site that needs no banner (§2.4), and a site
 * that decided so hides the finding with a reason.
 */
final class BannerOff extends WidgetsCheck
{
    public const string CHECK = 'widgets.banner_off';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::WARNING;

    /** Pages listed in the finding: enough to see which, not the whole site. */
    private const ROWS = 20;

    public function run(AuditContext $context): iterable
    {
        if (app(Consent::class)->enabled()) {
            return;
        }

        $rows = [];
        $pages = 0;

        foreach ($this->pages($context) as [$page, $facts]) {
            $what = array_unique(array_merge(
                array_map(static fn (mixed $load): string => (string) parse_url((string) ($load['url'] ?? ''), PHP_URL_HOST), (array) ($facts['loads'] ?? [])),
                array_keys((array) ($facts['waits'] ?? [])),
            ));

            if ($what === []) {
                continue;
            }

            $pages++;

            if (count($rows) < self::ROWS) {
                $rows[] = ['url' => $page->url, 'value' => implode(', ', array_filter($what))];
            }
        }

        if ($pages === 0) {
            return;
        }

        yield $this->found('banner-off', ['count' => $pages], table: [
            'columns' => [Finding::column('url', 'url'), Finding::column('value')],
            'rows' => $rows,
        ]);
    }
}
