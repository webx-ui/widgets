<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A check of the widgets (spec §15.2), read off the crawl's snapshot: what {@see
 * WidgetsPageReader} took of each page lies in its facts under `widgets`. Words in
 * `webx-widgets::checks.widgets.<name>` and `webx-widgets::audit.<summary>`.
 */
abstract class WidgetsCheck extends ModuleCheck
{
    protected const NAMESPACE = 'webx-widgets';

    protected const GROUP = 'widgets';

    /** @var list<string> */
    protected const NEEDS = ['crawl'];

    /**
     * The HTML pages of the run that answered 200 and carry facts of the widgets, with them.
     *
     * @return iterable<array{AuditPage, array<string, mixed>}>
     */
    protected function pages(AuditContext $context): iterable
    {
        foreach (AuditPage::query()->where('run_id', $context->run->id)->html()->lazyById(200) as $page) {
            $facts = $page->fact('widgets');

            if (is_array($facts) && $facts !== []) {
                yield [$page, $facts];
            }
        }
    }

    /**
     * A finding on a page, so its card lists it.
     *
     * @param  array<string, scalar|null>  $params
     * @param  list<string>  $markup  excerpts, a row each
     */
    protected function onPage(AuditPage $page, string $summary, array $params = [], array $markup = [], string $key = ''): Finding
    {
        $details = ['summary' => ['key' => static::NAMESPACE.'::audit.'.$summary, 'params' => $params]];

        if ($markup !== []) {
            $details['table'] = [
                'columns' => [Finding::column('markup', 'code')],
                'rows' => array_values(array_map(static fn (string $html): array => ['markup' => $html], $markup)),
            ];
        }

        return new Finding(static::ID, static::SEVERITY, $page->url, $details, $key, $page->id);
    }
}
