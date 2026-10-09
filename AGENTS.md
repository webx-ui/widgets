# webx-ui/widgets

Interactive pieces for the public site: behaviour, keyboard, ARIA and the no-JavaScript fallback
are done here; the look is a skeleton on `--site-*` tokens that the theme repaints. It is the
bottom layer of the theme chain, like a module, and needs `webx-ui/themes`.

## What it owns

- **Runtime** — `dist/runtime.js` + `dist/runtime.css`, on every page of a site with a theme. Joins
  `window.webx`: `webx.mount(root)` starts what is new inside `root` (idempotent),
  `webx.unmount(root)` lets it go, `webx.widget(name, selector, setup)` registers a widget —
  `setup(el)` may return its cleanup.
- **Behaviours by attribute** (no component needed):
  - `data-webx-disclosure="<id>"` on a button — shows/hides `#<id>`, Esc and a click outside close.
  - `data-webx-dialog="<id>"` on a link or button — opens `<dialog id="<id>">` as a modal;
    `[data-webx-dialog-close]` inside closes it. A URL ending in `#<id>` opens it on load.
  - `data-webx-tabs` on a container of `[data-webx-tab]` panels — label from the attribute's
    value or the panel's first heading; `data-webx-tab-selected` picks the first one shown.
  - `data-webx-accordion="single"` on a container of `<details>` — one open at a time.
- **Components** — `<x-webx-dialog id title close>`, `<x-webx-tabs label>` with
  `<x-webx-tabs.panel title selected level>`. Views: `webx-widgets::components.<name>`.
- **Header** — `<x-webx-header>` with slots `topbar`, `brand`, the default (the navigation),
  `actions`, `mobile-bottom`, `mobile`, `trigger`; props `collapse` (`auto` | a width in px |
  `never`), `breakpoint` (where `auto` folds without JavaScript, 960), `sticky` (`none` |
  `sticky` | `hide-on-scroll`), `overlay`, `skip` (`#content`, or `false` when the layout has
  its own skip link), `mode` and `side` of the mobile menu it builds. Inside it
  `<x-webx-header.nav :items="menu('header')" :mega="['services' => 'components.mega']">` —
  dropdowns; a `mega` view gets `$item`. `:items` takes `menu()` trees or arrays
  `['label', 'url', 'children', 'current', 'variant']`.
- **Mobile menu** — `<x-webx-mobile-menu side breakpoint close-on-navigate history>` with slots
  `top`, the default (the body), `bottom`, `trigger`; `<x-webx-mobile-menu.nav :items
mode="accordion|drill">` in its body. The header builds one by itself out of its brand,
  navigation and actions unless given `mobile` or `collapse="never"`.
- **Dropdown panel** — `<x-webx-dropdown placement="bottom-start|bottom-end|bottom|top-start|top-end|top"
open-on="click|hover" label open>` with slot `trigger` (its attributes go on the `<summary>`).
  A `<details>`: without JavaScript a click opens it; with it the panel is a popover placed from
  the trigger and turned over at the window's edge (`is-flipped`), Esc / Tab out / a click
  elsewhere close it, one is open on the page. Phones, hours, languages, "share" are built on it.
- **Form in a dialog** — any `<a>` or `<button>` with `data-webx-form="<slug>"`, or a link to
  `#webx-form-<slug>` (typed into a menu item or a block's button in the panel), opens that
  `module-inbox` form in `<dialog id="webx-form-<slug>">`, printed once per slug before
  `</body>`, the form inside `placement="modal"`. `data-webx-form-value-<field>="…"` fills the
  form's field `fields[<field>]` (a hidden field of the form). An opener the server does not
  print: `Widgets::form('<slug>')`. Without `module-inbox` or with no enabled form by that slug
  there is no dialog. View `webx-widgets::form-dialog`.
- **Cookie consent** — `dist/consent.js|css`, on every page with a theme. The banner and its
  dialog (`#webx-consent`) are printed before `</body>` by the package itself — no component to
  place; the theme's footer has `<x-webx-consent-link />` ("Cookie settings"). Categories
  `necessary`, `preferences`, `statistics`, `marketing`, `media`. Third-party code waits for its
  category: `<script type="text/plain" data-webx-consent="statistics" src|inline>` (real type in
  `data-webx-type`), `<iframe data-webx-consent="media" data-src="…">`, or any code inside
  `<x-webx-consent category="marketing">…</x-webx-consent>`. Server: `Consent::has('media')`
  (facade `WebxUi\Widgets\Facades\Consent`) reads the `webx_consent` cookie. Browser:
  `webx.consent.has('media')`, `webx.consent.set([...])`, `webx.consent.open()`, event
  `webx:consent` on `document`. Google Consent Mode v2 default is in the head. Settings: the
  "Cookie" tab of `module-settings` (`consent.*`, also over MCP `settings_set`), otherwise
  `config/webx-widgets.php` (`php artisan vendor:publish --tag=webx-widgets-config`).
- **Contacts** — `dist/contacts.js|css`, only where one stands; they read the Contacts tab of
  `module-settings` (`contacts()`) and print nothing while it is empty.
  `<x-webx-phones layout="dropdown|list" callback="<form>" compact placement>` — the main number a
  `tel:` link in E.164 (shown as typed), the others in a dropdown, messengers as icons;
  `<x-webx-hours layout="status|table">` — "Open until 19:00" in the site's time zone, the week
  folded, worked out again in the browser every minute from words the server wrote;
  `<x-webx-contact-button corner form :items>` — a round button in a corner, above the cookie
  banner while it shows; `<x-webx-contact-bar form :breakpoint>` — "Call / Write / Request" on a
  phone, no script; `<x-webx-socials>`; `<x-webx-icon name>` — networks, messengers (Simple Icons,
  CC0) and `phone`, `chat`, `mail`, `clock`, `link`, `form`, `close`, `globe`.
- **Language switcher** — `<x-webx-language-switcher layout="dropdown|list" codes placement
label fallback-label>`: each language named in itself, a link to the same page in it from the
  `webx-ui/routing` registry (as `hreflang`), or to its home page with `is-fallback` where there
  is no translation; nothing with one language or without the language in the path.
  `dist/language-switcher.css` only. Links for a markup of your own: `LanguageLinks::current()`.
- **Loading** — `Widgets::need('<name>')` (facade `WebxUi\Widgets\Facades\Widgets`) claims a
  widget's own `dist/<name>.js|css` for the page. `@webxTheme` prints a marker; the response gets
  the `<link>`s there and the `<script type="module">`s before `</body>`.
- **Classes** — public contract: `webx-<name>`, `webx-<name>__<element>`, states `is-*`
  (`webx-dialog__header|title|close|body`, `webx-tabs__list|tab|panel|title`, `is-enhanced`;
  `webx-header`, `--sticky|--hide-on-scroll|--overlay`, `__skip|topbar|bar|brand|nav|actions|trigger`,
  `is-collapsed|is-scrolled|is-hidden`; `webx-header-nav__item|link|toggle|dropdown|mega|sublink`,
  `is-open|is-current|is-flipped`; `webx-mobile-menu__trigger|panel|panel--<side>|top|close|body|bottom`;
  `webx-mobile-nav__list|item|link|toggle|level|back|title`, `is-drilled`;
  `webx-consent__inner|text|title|body|policy|actions|button|customize`,
  `webx-consent-dialog__intro|gpc|list|item|label|switch|name|always|description|actions`,
  `webx-consent-link`; `webx-dropdown`, `--<placement>`, `__trigger|panel`, `is-open|is-flipped`;
  `webx-form-dialog__header|title|close|body|done`, `is-sent`;
  `webx-phones__*`, `webx-hours__*` (`is-open|is-closed|is-today`), `webx-contact-button__*`,
  `webx-contact-bar__*`, `webx-socials__*`, `webx-icon`; `webx-language-switcher__current|list|
link`, `is-current|is-fallback` — listed in each view's header).
  `html.webx-js` once the runtime runs; `html.webx-scroll-locked` while a modal is open;
  `html.webx-header-sticky` while a header sticks (the page's scroll padding is its height).
- **Local tokens** — `--webx-dialog-*`, `--webx-tabs-*`, `--webx-header-*`,
  `--webx-mobile-menu-*`, `--webx-consent-*`, `--webx-dropdown-offset|min-width`,
  `--webx-icon-size`, `--webx-hours-*`, `--webx-contact-*`, `--webx-contact-bar-*`,
  `--webx-socials-size`, `--webx-language-switcher-*`; each listed in its stylesheet, declared on
  `:root` from `--site-*`. `--webx-header-height` and
  `--webx-header-topbar-height` are kept live by the runtime — use them for `top` of anything
  else sticky.
- **Words** — `webx-widgets::widgets.*` in en, ru, uk, de, pl, fr, es, it, pt, tr; every word
  is also a prop (`close="…"`).

## Change it without forking

| You want                                 | Do this                                                                                            |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------- |
| Other colours, radius, spacing           | site tokens in `theme/tokens.json` — the widgets follow                                            |
| A widget's own measure (dialog width…)   | `--webx-<name>-*` on `:root` or on `.webx-<name>` in `theme/src/css`                               |
| Another look of a widget                 | the same `.webx-<name>*` class in `theme/src/css` — it comes later in the cascade, no `!important` |
| Logo in the middle, two rows in a header | `grid-template-areas` on `.webx-header__bar` in `theme/src/css`                                    |
| Other markup of a component              | `theme/views/vendor/webx-widgets/components/<name>.blade.php`                                      |
| A network's or a messenger's icon        | `theme/views/vendor/webx-widgets/icons/<name>.blade.php` — what goes inside a 24×24 `<svg>`        |
| A word                                   | the component's prop, or `lang/vendor/webx-widgets/<locale>/widgets.php`                           |
| A behaviour of the site's own            | `webx.widget('name', '[data-…]', (el) => cleanup)` in `theme/src/js/theme.js`                      |
| Start widgets in HTML added later        | `webx.mount(container)`; before removing it, `webx.unmount(container)`                             |

## Do not

- Do not edit `vendor/webx-ui/widgets` or `public/themes/webx-ui/widgets`: the next update or
  `webx:theme:sync` replaces them.
- Do not write a colour literally or with a fallback (`var(--site-x, #fff)`): presets stop
  repainting it.
- Do not add `hidden` to a disclosure's panel in markup: without JavaScript it would never show.
- Do not style a widget through a chain (`.site-main .webx-dialog__title`): one class matches the
  package and wins by order; a longer chain starts a specificity race with the next theme.
- Do not paste a counter, a pixel, a chat or an embed as it is: wrap it in
  `<x-webx-consent category="…">` or write it as `type="text/plain"` / `data-src` — nothing
  third-party may load before the visitor agrees.
- Do not print a form of the inbox inside a dialog of your own for a button: link the button to
  `#webx-form-<slug>` — the page gets the form once, however many buttons lead to it.
- Do not make "Accept all" bigger or brighter than "Reject all": they share one class on purpose.
- Do not type a phone number, an address or a network into a template: it goes on the Contacts
  tab, and the widgets and the SEO markup read it from there.
- Do not format the hours or a time with `toLocaleString()` in a script of your own: it writes the
  browser's way, not the page's language — `<x-webx-hours>` already carries the words.
- Do not copy the runtime into the theme to change one behaviour: register your own widget.
- Do not call `Widgets::need()` for the behaviours above to get them — the runtime is already on
  every page; `need()` matters for widgets with files of their own.

## Check your work

- `php artisan webx:theme:sync` lists `webx-ui/widgets` as published.
- The page source has `/themes/webx-ui/widgets/<hash>/runtime.css` in `<head>` and
  `runtime.js` right before `</body>`; no `<!--webx-widgets-->` left.
- In the browser console: `typeof webx.widget === 'function'`; Tab through the widget with the
  keyboard; turn JavaScript off and the content is still there.

## Read more

- [README.md](README.md) in this directory.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_WIDGETS.md
