<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Widgets\Consent;

/**
 * `widgets.before_consent`: a video, a map, a counter or a pixel the page asks for before the
 * visitor has answered the banner (§2.4, §9.3) — an iframe with its `src`, a script that runs.
 * A finding per address and page; the key is the address, which is what the fix looks for.
 *
 * A map of `<x-webx-map>` the owner let load without consent (`Consent::mapsWait()`) is no
 * error: it was decided. One notice for the site says so, with the pages that have such a map —
 * the decision stays visible. Its tiles are asked for by the script, so no page lists them in `loads`.
 *
 * Quiet while the banner is off: then nothing waits for consent by the site's own choice, and
 * `widgets.banner_off` says so once instead of here on every page.
 */
final class BeforeConsent extends WidgetsCheck
{
    public const string CHECK = 'widgets.before_consent';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::ERROR;

    /** Pages listed in the notice of maps without consent: enough to see which. */
    private const ROWS = 20;

    public function run(AuditContext $context): iterable
    {
        if (! app(Consent::class)->enabled()) {
            return;
        }

        $maps = [];
        $mapPages = 0;

        foreach ($this->pages($context) as [$page, $facts]) {
            $seen = [];

            if ((int) ($facts['maps_at_once'] ?? 0) > 0 && ++$mapPages <= self::ROWS) {
                $maps[] = ['url' => $page->url, 'value' => (int) $facts['maps_at_once']];
            }

            foreach ((array) ($facts['loads'] ?? []) as $load) {
                $url = (string) ($load['url'] ?? '');

                if ($url === '' || isset($seen[$url])) {
                    continue;
                }

                $seen[$url] = true;

                yield $this->onPage($page, 'before-consent', [
                    'kind' => (string) ($load['kind'] ?? ''),
                    'host' => (string) parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST),
                    'category' => (string) ($load['category'] ?? ''),
                ], [(string) ($load['markup'] ?? '')], $url);
            }
        }

        if ($mapPages > 0) {
            yield $this->found('maps-at-once', ['count' => $mapPages], severity: Severity::NOTICE, table: [
                'columns' => [Finding::column('url', 'url'), Finding::column('value')],
                'rows' => $maps,
            ], key: 'maps-at-once');
        }
    }
}
