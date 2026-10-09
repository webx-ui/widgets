# webx-ui/widgets

Interactive pieces for the public site: behaviour, keyboard, ARIA and the no-JavaScript fallback
are done here; the look is a skeleton on `--site-*` tokens that the theme repaints. It is the
bottom layer of the theme chain, like a module, and needs `webx-ui/themes`.

## What it owns

- **Runtime** — `dist/runtime.js|css`, on every page of a site with a theme. Joins `window.webx`:
  `webx.mount(root)` starts what is new inside `root` (idempotent), `webx.unmount(root)` lets it
  go, `webx.widget(name, selector, setup)` registers one — `setup(el)` may return its cleanup.
  `Widgets::need('<name>')` (facade) claims `dist/<name>.js|css`: CSS at `@webxTheme`, JS before `</body>`.
- **Behaviours by attribute** (no component needed): `data-webx-disclosure="<id>"` on a button
  shows/hides `#<id>` (Esc, a click outside close); `data-webx-dialog="<id>"` opens `<dialog id>` as
  a modal (`[data-webx-dialog-close]` inside closes it, a URL ending in `#<id>` opens it on load);
  `data-webx-tabs` on a container of `[data-webx-tab]` panels (label: the value or the first heading;
  `data-webx-tab-selected` first shown); `data-webx-accordion="single"` on `<details>` — one open.
- **Components** — `<x-webx-dialog id title close>`, `<x-webx-tabs label>` with `<x-webx-tabs.panel title selected level>`.
- **Header** — `<x-webx-header>` with slots `topbar`, `brand`, the default (the navigation),
  `actions`, `mobile-bottom`, `mobile`, `trigger`; props `collapse` (`auto` | a width in px |
  `never`), `breakpoint` (where `auto` folds without JavaScript, 960), `sticky` (`none` |
  `sticky` | `hide-on-scroll`), `overlay`, `skip` (`#content`, or `false` when the layout has
  its own skip link), `mode` and `side` of the mobile menu it builds. Inside it
  `<x-webx-header.nav :items="menu('header')" :mega="['services' => 'components.mega']">` — dropdowns; a `mega`
  view gets `$item`. `:items` takes `menu()` trees or arrays `['label', 'url', 'children', 'current', 'variant']`.
- **Mobile menu** — `<x-webx-mobile-menu side breakpoint close-on-navigate history>`, slots `top`, the default (the
  body), `bottom`, `trigger`; `<x-webx-mobile-menu.nav :items mode="accordion|drill">` in its body. The header builds
  one of its brand, navigation and actions unless given `mobile` or `collapse="never"`.
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
  `module-settings` (`contacts()`) and print nothing while it is empty. `<x-webx-phones
layout="dropdown|list" callback="<form>" compact placement>` — the main number a `tel:` link in
  E.164 (shown as typed), the others in a dropdown, messengers as icons; `<x-webx-hours
layout="status|table">` — "Open until 19:00" in the site's time zone, the week folded, worked
  out again in the browser every minute; `<x-webx-contact-button corner form :items>` — a round
  button in a corner, above the cookie banner; `<x-webx-contact-bar form :breakpoint>` — "Call /
  Write / Request" on a phone, no script; `<x-webx-socials>`; `<x-webx-icon name>` — networks,
  messengers (Simple Icons, CC0), `phone chat mail clock link form close globe zoom`.
- **Language switcher** — `<x-webx-language-switcher layout="dropdown|list" codes placement label fallback-label>`:
  each language named in itself, a link to the same page in it (`routing`, `hreflang`), or to its home page with
  `is-fallback`; nothing with one language or no language in the path. CSS only. `LanguageLinks::current()`.
- **Slider** — `dist/slider.js|css` (Swiper built in), only where one stands: `<x-webx-slider
variant="cards|hero|gallery|logos" :per-view="['sm' => 1.2, 'md' => 2, 'lg' => 3]">` with
  `<x-webx-slide thumb>` inside. Per view by its own width (`sm` 0, `md` 640, `lg` 960, `xl` 1280
  or px); `arrows pagination loop autoplay continuous effect thumbs speed label`, `options` for the
  rest of Swiper. No JavaScript: a strip that scrolls and snaps; what moves itself has a pause
  button; only the first slides in view load at once.
- **Lightbox** — `dist/lightbox.js|css` (PhotoSwipe), claimed by any `<a data-webx-lightbox>` on the
  page: `<x-webx-lightbox :image="$picture" group>` (a media field's value or an object with `url()`,
  `width`, `height`; or `src width height thumb alt`; the slot replaces the thumbnail), or by hand
  `<a href data-webx-lightbox="<group>" data-width data-height>`. A group pages together, an empty
  one is alone, a slider's slides too. No JavaScript: the link opens the file. Write the sizes.
- **Video** — `dist/video.js|css`, only where one stands: `<x-webx-video src="<YouTube or Vimeo>"
:poster="$picture" title ratio="16/9">` — a poster and a play button, the player (youtube-nocookie)
  only on a click; before consent to `media` the server prints the notice with "Load" / "Always load
  videos". No poster: the video's preview, fetched once into the library (folder "Video posters").
  `<x-webx-video :file="$media" :poster>` — a `<video preload="none">`, no consent. No JS: a link.
- **Map** — `dist/map.js|css` (Leaflet), only where one stands: `<x-webx-map :lat :lng :zoom marker height title link>`
  or `<x-webx-map from="settings">` (main address of the Contacts tab; no coordinates — the address alone). The server
  prints the address, "Open in maps" and, before consent to `media`, "Load" / "Always load maps"; tiles only after. The
  wheel scrolls the page until the map is clicked. Tiles: `webx-widgets.map.provider` (OpenStreetMap; `{key}` entries).
- **Blocks** — offered with `module-media` (`webx:setup`, or `webx:blocks:offered --install --module=widgets`),
  then the site's: `gallery` (library pictures, grid or slider, zoom = lightbox, a group per block, title = caption),
  `logos` (name, logo, link each, a strip), `video` (link or file by a switch, poster, caption, 16:9|4:3|1:1|9:16), `map` (contacts or coordinates, zoom, height); nothing to show prints nothing.
- **Audit** (with `module-audit`): `widgets.before_consent` (a known player, map, counter or pixel asked at once —
  fix `widgets.wait-for-consent` rewrites it where content stores it), `widgets.banner_off` (fix `widgets.banner-on`),
  `widgets.lightbox_size`, `widgets.slider_pause`, `widgets.contact_both`. Own third parties: `webx-widgets.audit.third-party`.
- **Classes** — public contract: `webx-<name>`, `webx-<name>__<element>`, states `is-*`
  (`webx-dialog__header|title|close|body`, `webx-tabs__list|tab|panel|title`, `is-enhanced`;
  `webx-header`, `--sticky|--hide-on-scroll|--overlay`, `__skip|topbar|bar|brand|nav|actions|trigger`,
  `is-collapsed|is-scrolled|is-hidden`; `webx-header-nav__item|link|toggle|dropdown|mega|sublink`,
  `is-open|is-current|is-flipped`; `webx-mobile-menu__trigger|panel|panel--<side>|top|close|body|bottom`;
  `webx-mobile-nav__list|item|link|toggle|level|back|title`, `is-drilled`;
  `webx-consent__inner|text|title|body|policy|actions|button|customize`, `webx-consent-dialog__intro|
gpc|list|item|label|switch|name|always|description|actions`, `webx-consent-link`; `webx-dropdown`,
  `--<placement>`, `__trigger|panel`, `is-open|is-flipped`; `webx-form-dialog__header|title|close|
body|done`, `is-sent`; `webx-phones__*`, `webx-hours__*` (`is-open|is-closed|is-today`),
  `webx-contact-button__*`, `webx-contact-bar__*`, `webx-socials__*`, `webx-icon`;
  `webx-language-switcher__current|list|link`, `is-current|is-fallback`; `webx-slider`, `--<variant>`,
  `__viewport|track|slide|controls|button|prev|next|pause|pagination|bullet|thumbs|thumb`,
  `is-ready|is-active`; `webx-lightbox-link`, `__image`, `is-ready`, the viewer PhotoSwipe's `.pswp`
  with `webx-lightbox`; `webx-video`, `--youtube|vimeo|file`, `__facade|poster|play|consent|notice|
button|frame|player`, `is-blocked|is-ready|is-playing`; `webx-map`, `--place`, `__place|address|open|notice|button|attribution|canvas|marker|hint`, `is-loaded|is-active` — in each view). `html.webx-js` once the runtime runs; `html.webx-scroll-locked`
  while a modal is open; `html.webx-header-sticky` while a header sticks (scroll padding = its height).
- **Local tokens** — `--webx-<name>-*` of each widget (`--webx-slider-per-view`, `--webx-lightbox-backdrop`…),
  on `:root` from `--site-*`; `--webx-header-height|-topbar-height` kept live by the runtime — `top` of anything sticky.
  **Words** — `webx-widgets::widgets.*` in en ru uk de pl fr es it pt tr; each also a prop.

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

- Do not edit `vendor/webx-ui/widgets` or `public/themes/webx-ui/widgets`: an update or a sync replaces them.
- Do not write a colour literally or with a fallback (`var(--site-x, #fff)`): presets skip it.
- Do not add `hidden` to a disclosure's panel in markup: without JavaScript it would never show.
- Do not style a widget through a chain (`.site-main .webx-dialog__title`): one class matches the
  package and wins by order; a longer chain starts a specificity race with the next theme.
- Do not paste a counter, a pixel, a chat or an embed as it is: wrap it in
  `<x-webx-consent category="…">` or write it as `type="text/plain"` / `data-src` — nothing
  third-party may load before the visitor agrees. A video is `<x-webx-video>`, a map `<x-webx-map>`.
- Do not put an inbox form in a dialog of your own: link the button to `#webx-form-<slug>` — printed once.
- Do not make "Accept all" bigger or brighter than "Reject all": they share one class on purpose.
- Do not type a phone number, an address or a network into a template: it goes on the Contacts
  tab, and the widgets and the SEO markup read it from there.
- Do not format a time with `toLocaleString()`: it writes the browser's way — `<x-webx-hours>` has the words.
- Do not copy the runtime into the theme (register your own widget), nor `Widgets::need()` its behaviours.

## Check your work

- `webx:theme:sync` lists `webx-ui/widgets`; `.../widgets/<hash>/runtime.css` in `<head>`, `runtime.js` before `</body>`. Tab; JS off.
- `php artisan webx:audit:run`, then `audit_issues` with `check: "widgets.before_consent"` — nothing; the "Kitchen sink" pages of `theme-default` show each widget.

## Read more

- [README.md](README.md); the guide: https://webx-ui.github.io/webx-ui/guide/widgets; the spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_WIDGETS.md
