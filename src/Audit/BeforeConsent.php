<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Widgets\Consent;

/**
 * `widgets.before_consent`: a video, a map, a counter or a pixel the page asks for before the
 * visitor has answered the banner (§2.4, §9.3) — an iframe with its `src`, a script that runs.
 * A finding per address and page; the key is the address, which is what the fix looks for.
 *
 * Quiet while the banner is off: then nothing waits for consent by the site's own choice, and
 * `widgets.banner_off` says so once instead of here on every page.
 */
final class BeforeConsent extends WidgetsCheck
{
    public const string CHECK = 'widgets.before_consent';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::ERROR;

    public function run(AuditContext $context): iterable
    {
        if (! app(Consent::class)->enabled()) {
            return;
        }

        foreach ($this->pages($context) as [$page, $facts]) {
            $seen = [];

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
    }
}
