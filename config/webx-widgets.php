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

    /*
     * The map (spec §11): Leaflet over the tiles of the provider named here. OpenStreetMap needs
     * neither a key nor an account; any other provider of raster tiles is an entry with its
     * address — `{key}` in it is replaced with the entry's `key`. The attribution is printed
     * under every map, with or without JavaScript: the licence of the data asks for it. A site
     * changes the provider in its published config (vendor:publish --tag=webx-widgets-config).
     */
    'map' => [
        'provider' => 'openstreetmap',

        'providers' => [
            'openstreetmap' => [
                'label' => 'OpenStreetMap',
                'tiles' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                'attribution' => '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                'max_zoom' => 19,
            ],
            'maptiler' => [
                'label' => 'MapTiler',
                'tiles' => 'https://api.maptiler.com/maps/streets-v2/256/{z}/{x}/{y}.png?key={key}',
                // The site's published config reads it from its environment: env('WEBX_MAP_KEY').
                'key' => null,
                'attribution' => '<a href="https://www.maptiler.com/copyright/">© MapTiler</a> <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>',
                'max_zoom' => 20,
            ],
        ],

        // "Open in maps": where the link under the address leads when the address has no link of its own.
        'open' => 'https://www.openstreetmap.org/?mlat={lat}&mlon={lng}#map={zoom}/{lat}/{lng}',
    ],

    /*
     * The site audit (spec §15.2), when `module-audit` is installed. Its check "loads before
     * consent" knows the usual players, maps, counters and pixels; a site adds the third parties
     * it uses by host, or host and path, with the category they wait for:
     * 'widget.example.com' => 'marketing', 'example.com/embed' => 'media'.
     */
    'audit' => [
        'third-party' => [],
    ],
];
