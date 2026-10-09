<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * `widgets.contact_both`: `<x-webx-contact-button>` and `<x-webx-contact-bar>` on one page
 * (§12.4: a site has one of the two; both are allowed, and warned about). The two say the same
 * thing twice on a phone, one over the other's corner.
 */
final class ContactBoth extends WidgetsCheck
{
    public const string CHECK = 'widgets.contact_both';

    protected const ID = self::CHECK;

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context) as [$page, $facts]) {
            if (($facts['contact_both'] ?? false) === true) {
                yield $this->onPage($page, 'contact-both');
            }
        }
    }
}
