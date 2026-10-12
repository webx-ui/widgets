<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.form_script`: a form of `<x-webx-form>` — an inbox form among them — on a page that
 * loads no `form.js`. The scripts reach a page through `@webxTheme`, or `@webxWidgets` on a site
 * without a theme, from the files `webx:theme:sync` published; a layout with neither, or a site
 * that never synced, gets forms that still send — with a reload, checked by the server only —
 * and nothing says so. A site that bundles the scripts itself under another name ignores it.
 */
final class FormScript extends WidgetsCheck
{
    public const string CHECK = 'widgets.form_script';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            $found = (array) ($facts['form_scriptless'] ?? []);

            if ((int) ($found['count'] ?? 0) > 0) {
                yield $this->onPage($page, 'form-script', ['count' => (int) $found['count']], array_map('strval', (array) ($found['markup'] ?? [])));
            }
        }
    }
}
