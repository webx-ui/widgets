<?php

declare(strict_types=1);

return [
    /*
     * Cookie consent (spec §9). With `module-settings` installed the "Cookie" tab of the
     * settings screen writes the same keys (`consent.*`) and wins over what is here; without
     * it, this is where a site says what its banner does.
     */
    'consent' => [
        // Off only for a site that sets nothing a visitor has to agree to: everything is then
        // allowed, and the site audit says so where something third-party is on the page.
        'enabled' => true,

        // Raised when the policy or the list of what the site sets changes: everyone is asked again.
        'version' => 1,

        // The address of the cookie policy, shown as a link in the banner. Null: no link.
        'policy' => null,

        // The optional categories the dialog offers. Empty: those something on the site uses —
        // a `data-webx-consent="<category>"` on any page served so far, or one already granted.
        'categories' => [],

        // The banner's words, per language: ['title' => ['en' => '…'], 'text' => …, 'media' => …].
        // Anything missing is the package's dictionary.
        'texts' => [],

        // How long the answer is kept, in days.
        'lifetime' => 365,
    ],
];
