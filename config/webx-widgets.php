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
     * The site's captcha (`WebxUi\Widgets\Captcha`): one provider and one pair of keys for every
     * form that wants one — the inbox's forms, the checkout, the visitors' sign-in. Each of them
     * only says "on" or "off"; which provider is the site's. With `module-settings` installed the
     * "Captcha" tab writes the provider, the kind of reCAPTCHA key, Turnstile's mode and the site
     * key (`captcha.*`) and wins over what is here. The secret is the environment's only: it is
     * never stored in the database and never shown to an agent.
     *
     * The names a site configured for the inbox before the captcha was the site's are read when
     * the new ones are not set — `WEBX_INBOX_RECAPTCHA_KEY` and the rest — and so is a published
     * `config/webx-inbox.php` (`webx-inbox.captcha.*`), so nothing has to be set up again.
     *
     * reCAPTCHA keys come in three kinds, and a key of one kind drawn as another is the widget's own
     * "Invalid key type": `type` is `checkbox` (v2, "I'm not a robot"), `invisible` (v2 Invisible,
     * run when the form is sent) or `v3` (a score, no widget; `min_score` is the line below which a
     * submission is a robot). Turnstile has one kind of key: `mode` `managed` draws the widget when
     * the page opens, `invisible` runs it when the form is sent. `script` is the provider's own,
     * printed once per page beside the first widget; empty — the site loads it itself.
     */
    'captcha' => [
        // `recaptcha`, `turnstile`, or null: no form on the site can draw one.
        'provider' => env('WEBX_CAPTCHA_PROVIDER'),

        'recaptcha' => [
            'key' => env('WEBX_CAPTCHA_RECAPTCHA_KEY', env('WEBX_INBOX_RECAPTCHA_KEY')),
            'secret' => env('WEBX_CAPTCHA_RECAPTCHA_SECRET', env('WEBX_INBOX_RECAPTCHA_SECRET')),
            // Null: what a published `webx-inbox` config says, else `checkbox`.
            'type' => env('WEBX_CAPTCHA_RECAPTCHA_TYPE', env('WEBX_INBOX_RECAPTCHA_TYPE')),
            'min_score' => env('WEBX_CAPTCHA_RECAPTCHA_MIN_SCORE', env('WEBX_INBOX_RECAPTCHA_MIN_SCORE')),
            'verify' => 'https://www.google.com/recaptcha/api/siteverify',
            'script' => 'https://www.google.com/recaptcha/api.js',
        ],

        'turnstile' => [
            'key' => env('WEBX_CAPTCHA_TURNSTILE_KEY', env('WEBX_INBOX_TURNSTILE_KEY')),
            'secret' => env('WEBX_CAPTCHA_TURNSTILE_SECRET', env('WEBX_INBOX_TURNSTILE_SECRET')),
            // Null: what a published `webx-inbox` config says, else `managed`.
            'mode' => env('WEBX_CAPTCHA_TURNSTILE_MODE', env('WEBX_INBOX_TURNSTILE_MODE')),
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        ],

        // Seconds to wait for the provider's answer; past it the submission is let through.
        'timeout' => 5,
    ],

    /*
     * The quick-contact button in a corner of the window (spec §12.4), `<x-webx-contact-button>`,
     * which `theme-default` prints on every page that has contacts. False for a site that wants
     * none: the button is not printed wherever it is placed, and the contacts script is not
     * loaded for it — other contact widgets on the page still load it for themselves.
     */
    'contact_button' => true,

    /*
     * The forms in a dialog (spec §6.4). `sheet` — every one of them is a sheet from the bottom on a
     * narrow screen, as `data-webx-sheet` on an opener asks for one; a card in the middle elsewhere.
     */
    'form_dialog' => [
        'sheet' => false,
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

        // Whether a map waits for consent to `media` (§9.4): tiles send the visitor's address to the
        // provider. False only when the owner decided the site needs no consent for that: maps load
        // at once, the banner and the other categories are as they were, and the audit notes it.
        // The Cookie tab's "Maps without asking" does the same; either of the two lifts the wait.
        'consent' => true,

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

    /*
     * Where the package's own routes live (spec §17.3): `GET <path>/csrf` hands a form whose
     * page was cached with a stale token a fresh one, and while the demo is on the site
     * `POST <path>/showcase` answers the forms of its kitchen sink.
     */
    'path' => 'webx/widgets',
];
