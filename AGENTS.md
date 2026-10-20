# webx-ui/widgets

Interactive pieces for the public site: behaviour, keyboard, ARIA and the no-JavaScript fallback
are done here; the look is a skeleton on `--site-*` tokens that the theme repaints. It is the
bottom layer of the theme chain, like a module, and needs `webx-ui/themes`.

## What it owns

- **Loading** — `dist/runtime.js|css` (behaviours, header, mobile menu) on every page with a theme;
  a widget's own `dist/<name>.js|css` only where a component claims it (`Widgets::need('<name>')`,
  facade `WebxUi\Widgets\Facades\Widgets`): CSS at `@webxTheme`, JS before `</body>`. A site
  without a theme writes `@webxWidgets` before `</body>` (scripts only, no styles, no consent
  banner); `webx:setup --no-theme` adds it, `webx:theme:sync` publishes the files either way.
  `webx.mount(root)` starts what is new in `root`, `webx.unmount(root)` lets it go,
  `webx.widget(name, selector, setup)` registers one — `setup(el)` may return its cleanup.
- **Behaviours by attribute** — `data-webx-disclosure="<id>"`, `data-webx-dialog="<id>"` (a modal
  `<dialog>`; `[data-webx-dialog-close]`, a URL ending in `#<id>` opens it),
  `data-webx-tabs` around `[data-webx-tab]` panels, `data-webx-accordion="single"` on `<details>`.
  Components: `<x-webx-dialog id title close>`, `<x-webx-tabs label>` + `<x-webx-tabs.panel title>`.
- **Header** — `<x-webx-header>`: slots `topbar`, `brand`, default (navigation), `actions`,
  `mobile-bottom`, `mobile`, `trigger`; props `collapse` (`auto` measures, a width, `never`),
  `breakpoint` (960, without JavaScript), `sticky` (`none|sticky|hide-on-scroll`), `overlay`,
  `skip`, `mode`/`side` of the mobile menu it builds from its own parts. Inside:
  `<x-webx-header.nav :items="menu('header')" :mega>` — items are `menu()` trees or arrays.
- **Mobile menu** — `<x-webx-mobile-menu side breakpoint history>` with slots `top`, default,
  `bottom`, `trigger`; `<x-webx-mobile-menu.nav :items mode="accordion|drill">` inside.
- **Dropdown** — `<x-webx-dropdown placement open-on="click|hover">` with slot `trigger`: a
  `<details>` without JavaScript, a popover turned over at the window's edge with it; one open.
- **Form in a dialog** — a link to `#webx-form-<slug>` or `data-webx-form="<slug>"` opens that
  `module-inbox` form in a dialog printed once per slug; `data-webx-form-value-<field>` fills a
  hidden field; `Widgets::form('<slug>')` for an opener the server does not print.
- **Cookie consent** — the package prints the banner on every themed page; the footer has
  `<x-webx-consent-link />`. Categories: necessary, preferences, statistics, marketing, media.
  Third-party code waits: `<script type="text/plain" data-webx-consent="statistics">`,
  `<iframe data-webx-consent="media" data-src>`, or `<x-webx-consent category="…">`. Server:
  `Consent::has('media')`; browser: `webx.consent.has|set|open`, event `webx:consent`. Settings:
  the "Cookie" tab (`consent.*`), else `config/webx-widgets.php`.
- **Contacts** — read the Contacts tab of `module-settings` (`contacts()`), print nothing while it
  is empty: `<x-webx-phones layout callback compact>`, `<x-webx-hours layout="status|table">`
  (site's time zone, recomputed each minute), `<x-webx-contact-button corner form>` (off —
  `contact_button => false`), `<x-webx-contact-bar form breakpoint>`, `<x-webx-socials>`, `<x-webx-icon name>`.
- **Language switcher** — `<x-webx-language-switcher layout="dropdown|list" codes>`: the same page
  in each language (`routing`), else its home page with `is-fallback`; `LanguageLinks::current()`.
- **Slider** — `<x-webx-slider variant="cards|hero|gallery|logos" :per-view="['sm' => 1.2, 'lg' => 3]">`
  with `<x-webx-slide thumb>`; per view by its own width (`sm` 0, `md` 640, `lg` 960, `xl` 1280);
  `options` for the rest of Swiper. Without JavaScript a strip that scrolls and snaps.
- **Lightbox** — any `<a data-webx-lightbox="<group>" data-width data-height>` on the page, or
  `<x-webx-lightbox :image="$picture" group>`. **Zoom** — `<x-webx-zoom :image mode="inner|side">`,
  or `data-webx-zoom` on an element holding a picture and a link; the link is the keyboard's way.
- **Video** — `<x-webx-video src="<YouTube|Vimeo>" :poster title ratio>`: a poster, the player only
  on a click and after consent to `media`; no poster — its preview fetched once into the library.
  `:file="$media"` — `<video preload="none">`; `variant="background"` — a muted first-screen loop.
- **Map** — `<x-webx-map :lat :lng :zoom marker height consent>` or `from="settings"`: address and
  "Open in maps" always, tiles after consent — unless the owner lifts the wait (Cookie tab "Maps
  without asking", `webx-widgets.map.consent` false; the audit notes it); provider `map.provider`.
- **Page tools** — a `<table>` without a class gets a scrolling `.webx-table` frame (server-side);
  `data-webx-reveal="up|fade|scale|stagger"`; `<x-webx-back-to-top>`; `<x-webx-share>`;
  `<x-webx-counter :value>`; `<x-webx-countdown to ended>`; `<x-webx-compare :before :after>`;
  `<x-webx-toc for depth side>`; `<x-webx-pagination :paginator>`;
  `<x-webx-load-more :paginator pages="covered|shown">` (fetches the next page's HTML);
  `<x-webx-notice-bar place="notice">` (a `module-banners` place, closed versions remembered).
- **Fields** — `<x-webx-field type name label hint required>` for every form of the site; `type`
  `text` `email` `tel` `url` `password` `number` `textarea` `select` `radio` `checkbox`
  (`:choices` → a group, without → one tick) `consent` `date` `file` `hidden`. Value `old()`, error
  `$errors` by the dotted key (`fields.email` is written `fields[email]`); `autocomplete` and
  `inputmode` default by type; `class` on the field, other attributes on the control.
- **The form** — `<x-webx-form :action method id bag :csrf>` around the fields and
  `<x-webx-form.submit>`: `@csrf`, `@method`, a hidden `_webx_form`. Without JavaScript a plain
  POST, each error back under its field. With it: sent in place, `X-XSRF-TOKEN`, one retry on 419,
  a 422 by field, no second submission, files with progress. `:csrf="false"` only for a door
  guarded otherwise (inbox's intake).
- **The answer** — `WebxUi\Widgets\Forms\FormResponse`: `FormResponse::message($heading, $html)`,
  `FormResponse::redirect($url)`, `FormResponse::html($view)` (a next step in place of the fields),
  `FormResponse::ok()` (only the event); JSON to the script, a redirect with flash to a plain POST.
  Events on the `<form>`: `webx:form:submit` (cancelable, `detail.data`, `detail.wait(promise)`),
  `webx:form:success`, `webx:form:error`.
- **Checks in the browser** — `<x-webx-form :rules="FormRules::for($rules, $attributes, $messages)">`
  (the arrays the controller validates by): the rules the browser can check, with the validator's
  own words in the page's language; the rest stays the server's. `type="tel" mask` — a phone mask;
  file fields get drop, a list and Remove.
- **Suggestions while typing** — `<x-webx-suggest :action :source name :recent>` (a GET search;
  without `action` a field to stand inside another form). `source` answers HTML of the site's own
  view: `data-webx-option` on what can be chosen, `data-webx-section` + `data-webx-heading` on a
  group. Events `webx:suggest:select` (cancelable), `webx:suggest:results`. The catalogue's:
  `GET /catalog/suggest`.
- **Blocks** — offered with `module-media`, then the site's: `gallery`, `logos`, `video`, `map`,
  `counters`, `countdown`, `compare`, `toc` (`webx:blocks:offered --install --module=widgets`).
- **Audit** (with `module-audit`) — `widgets.before_consent` (fix `widgets.wait-for-consent`),
  `widgets.banner_off` (fix `widgets.banner-on`), `lightbox_size`, `slider_pause`, `video_pause`,
  `counter_number`, `compare_range`, `toc_target`, `load_more_link`, `zoom_link`, `form_script`,
  `contact_both`. A site's own third parties: `webx-widgets.audit.third-party`.
- **Classes** — the public contract: `webx-<name>`, `webx-<name>__<element>`, modifiers
  `--<variant>`, states `is-*` (`is-open`, `is-current`, `is-flipped`, `is-invalid`, `is-sending`…);
  each component's view lists its own. `html.webx-js` once the runtime runs,
  `html.webx-scroll-locked` under a modal, `html.webx-header-sticky` while the header sticks.
- **Tokens and words** — `--webx-<name>-*` per widget on `:root` from `--site-*`;
  `--webx-header-height` is kept live — the `top` of anything sticky. Words
  `webx-widgets::widgets.*` in en ru uk de pl fr es it pt tr; each also a prop.

## Change it without forking

- Colours, radius, spacing — the site tokens in `theme/tokens.json`; the widgets follow.
- A widget's own measure — `--webx-<name>-*` on `:root` or `.webx-<name>` in `theme/src/css`.
- Another look — the same `.webx-<name>*` class in `theme/src/css`: it comes later, no `!important`.
- A header's layout (logo in the middle, two rows) — `grid-template-areas` on `.webx-header__bar`.
- Other markup — `theme/views/vendor/webx-widgets/components/<name>.blade.php`.
- Another control of one field type — `theme/views/vendor/webx-widgets/components/field/<type>.blade.php`;
  print `{{ $control($attributes) }}` on it.
- An icon — `theme/views/vendor/webx-widgets/icons/<name>.blade.php` (inside a 24×24 `<svg>`).
- A word — the component's prop, or `lang/vendor/webx-widgets/<locale>/widgets.php`.
- A behaviour of the site's own — `webx.widget('name', '[data-…]', (el) => cleanup)` in
  `theme/src/js/theme.js`.
- HTML added later — `webx.mount(container)`; `webx.unmount(container)` before removing it.
- A map provider — an entry in `webx-widgets.map.providers` with `{key}` in its address.

## Do not

- Do not edit `vendor/webx-ui/widgets` or `public/themes/webx-ui/widgets`: an update or a sync
  replaces them; nor import `dist/*.js` into the site's bundle — `@webxWidgets` links them.
- Do not write a colour literally or with a fallback (`var(--site-x, #fff)`), nor style a widget
  through a chain (`.site-main .webx-dialog__title`): one class wins by order.
- Do not add `hidden` to a disclosure's panel in markup: without JavaScript it would never show.
- Do not paste a counter, a pixel, a chat or an embed as it is: wrap it in
  `<x-webx-consent category="…">` or write it `type="text/plain"` / `data-src`. A video is
  `<x-webx-video>`, a map `<x-webx-map>`.
- Do not make "Accept all" bigger or brighter than "Reject all": they share one class on purpose.
- Do not put an inbox form in a dialog of your own: link to `#webx-form-<slug>`.
- Do not send a form with a `fetch` of your own, nor print a `<form>` without `@csrf` to a route of
  the app: use `<x-webx-form>` and hook its events.
- Do not write a field's markup of your own, nor validate only in the browser, nor keep a second
  set of rules or messages in JavaScript: `<x-webx-field>` and `FormRules` carry the server's.
- Do not write a search box's own `fetch` and list, nor put `<x-webx-suggest action>` inside
  another form (forms do not nest — drop `action` there).
- Do not type a phone, an address or a network into a template: they live on the Contacts tab.
- Do not format a time with `toLocaleString()`: `<x-webx-hours>` has the words.
- Do not copy the runtime into the theme, nor `Widgets::need()` its behaviours.

## Check your work

- `webx:theme:sync` lists `webx-ui/widgets`; `runtime.css` is in `<head>`, `runtime.js` before
  `</body>`. Walk it with Tab; turn JavaScript off.
- `php artisan webx:audit:run`, then `audit_issues` with `check: "widgets.before_consent"` —
  nothing. The "Kitchen sink" pages of `theme-default` show each widget.

## Read more

- [README.md](README.md)
- The guides: https://webx-ui.github.io/webx-ui/guide/widgets and
  https://webx-ui.github.io/webx-ui/guide/forms (every rule, prop, class and event of the forms)
- The spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_WIDGETS.md
