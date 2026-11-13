# webx-ui/widgets

Interactive pieces for the public site: behaviour, keyboard, ARIA and the no-JavaScript fallback
are done here; the look is a skeleton on `--site-*` tokens that the theme repaints. It is the
bottom layer of the theme chain, like a module, and needs `webx-ui/themes`.

## What it owns

- **Loading** — `dist/runtime.js|css` (behaviours, header, mobile menu) on every page with a theme;
  a widget's own `dist/<name>.js|css` only where a component claims it (`Widgets::need('<name>')`,
  facade `WebxUi\Widgets\Facades\Widgets`): CSS at `@webxTheme` — claimed only inside `later()` / `@webxLater(<when>)…@endwebxLater` (a closed dialog's form, a hidden banner) it does not block the first paint — JS before `</body>`. A site
  without a theme writes `@webxWidgets` before `</body>` (scripts only, no styles, no consent
  banner); `webx:setup --no-theme` adds it, `webx:theme:sync` publishes the files either way.
  `webx.mount(root)` starts what is new in `root`, `webx.unmount(root)` lets it go,
  `webx.widget(name, selector, setup)` registers one — `setup(el)` may return its cleanup.
- **Behaviours by attribute** — `data-webx-disclosure="<id>"`, `data-webx-dialog="<id>"` (a modal
  `<dialog>`; `[data-webx-dialog-close]`, a URL ending in `#<id>` opens it),
  `data-webx-tabs` around `[data-webx-tab]` panels, `data-webx-accordion="single"` on `<details>`.
  Components: `<x-webx-dialog id title description close sheet drawer>` (`sheet` — from the bottom below 40rem; `drawer` — at the inline end, the window's whole height), `<x-webx-tabs label transition>` + `<x-webx-tabs.panel title>` (`transition="fade"` — cross-fade, panels in one cell, the tallest sets the height; unselected ones get `is-hidden`, not `hidden`).
- **Header** — `<x-webx-header>`: slots `topbar`, `brand`, default (navigation), `actions`,
  `mobile-bottom`, `mobile`, `trigger`; props `collapse` (`auto` measures, a width, `never`),
  `breakpoint` (960, without JavaScript), `sticky` (`none|sticky|hide-on-scroll`), `overlay`,
  `skip`, `mode`/`side` of the mobile menu it builds from its own parts. Inside:
  `<x-webx-header.nav :items="menu('header')" :mega>` — items are `menu()` trees or arrays.
- **Mobile menu** — `<x-webx-mobile-menu side breakpoint history>` with slots `top`, default,
  `bottom`, `trigger`; `<x-webx-mobile-menu.nav :items mode="accordion|drill" parent="repeat|split">` inside (`split`: the section's name is the link, an arrow opens it, a child at the section's own address is dropped; also `<x-webx-header parent>`).
- **Dropdown** — `<x-webx-dropdown placement open-on="click|hover">` with slot `trigger`: a
  `<details>` without JavaScript, a popover turned over at the window's edge with it; one open.
- **Form in a dialog** — a link to `#webx-form-<slug>` or `data-webx-form="<slug>"` opens that
  `module-inbox` form in a dialog printed once per slug; `data-webx-form-value-<field>` fills a
  hidden field; `Widgets::form('<slug>')` for an opener the server does not print. `data-webx-sheet` on an opener, `Widgets::form($slug, sheet: true)` or `form_dialog.sheet` — a sheet on a phone.
- **Cookie consent** — the package prints the banner on every themed page; the footer has
  `<x-webx-consent-link />`. Categories: necessary, preferences, statistics, marketing, media.
  Third-party code waits: `<script type="text/plain" data-webx-consent="statistics">`,
  `<iframe data-webx-consent="media" data-src>`, or `<x-webx-consent category="…">`. Server:
  `Consent::has('media')`; browser: `webx.consent.has|set|open`, event `webx:consent`. Settings:
  the "Cookie" tab (`consent.*`), else `config/webx-widgets.php`. Button words (`lang/vendor/webx-widgets`): banner `consent.reject|accept|customize`, dialog `consent.dialog_reject|dialog_accept|save` — apart.
- **Contacts** — read the Contacts tab of `module-settings` (`contacts()`), print nothing while it
  is empty: `<x-webx-phones layout callback compact>`, `<x-webx-hours layout="status|table">`
  (site's time zone, recomputed each minute), `<x-webx-contact-button corner form>` (off —
  `contact_button => false`), `<x-webx-contact-bar form breakpoint>`, `<x-webx-socials>`, `<x-webx-icon name>`.
- **Language switcher** — `<x-webx-language-switcher layout="dropdown|list" codes>`: the same page
  in each language (`routing`), else its home page with `is-fallback`; `LanguageLinks::current()`.
- **Slider** — `<x-webx-slider variant="cards|hero|gallery|logos" :per-view="['sm' => 1.2, 'lg' => 3]">` with `<x-webx-slide thumb>`; per view by its own width (`sm` 0, `md` 640, `lg` 960, `xl` 1280);
  `pagination` true|false|`fraction` (counter "1/6", aria-hidden); `options` — Swiper. No JS: a snapping strip. `<x-slot:overlay>` — a still pane over the slides (hidden until ready): its `.webx-slider__caption`s follow the slide (`is-current`, the rest `inert`), `pagination-in=".sel"` puts the dots in it ("Slide 2 of 4");
  `:pause="false"` — no pause button (hover/focus still hold; the audit leaves it). Event `webx:slide` on the root, `detail: { index, count, auto }` — index from 0, a loop's copies not counted.
- **Lightbox** — any `<a data-webx-lightbox="<group>" data-width data-height>` on the page, or
  `<x-webx-lightbox :image="$picture" group ratio="4:3" opacity>`: `ratio` a frame the picture fits into whole (`data-webx-lightbox-ratio`),
  the backdrop opaque unless `--webx-lightbox-backdrop-opacity` says less; closing keeps the page's scroll. **Zoom** — `<x-webx-zoom :image mode frame>` (`inner|side`,
  `picture|container`) or `data-webx-zoom` on an element with a picture and a link (its way in).
- **Video** — `<x-webx-video src="<YouTube|Vimeo>" :poster title ratio>`: a poster, the player only
  on a click and after consent to `media`; no poster — its preview fetched once into the library.
  `:file="$media"` — `<video preload="none">`; `variant="background"` — a muted first-screen loop.
- **Map** — `<x-webx-map :lat :lng :zoom marker height consent>` or `from="settings"`: address and
  "Open in maps" always, tiles after consent — unless the owner lifts the wait (Cookie tab "Maps
  without asking", `webx-widgets.map.consent` false; the audit notes it); tiles `map.provider`, or Google
  Maps on the settings tab "Map" (`map.provider` google, `map.google-key`; no key — the place alone, `dist/map-google.js`). `embed` — Google's "Embed a map" code or address, no key (`Map\GoogleMapsEmbed`; the block's `wx-embed` field refuses any other), its `<iframe>` printed by the server when the map does not wait (block switch `at_once`, `:consent="false"`); `height-narrow` under 36em.
- **Page tools** — a `<table>` without a class gets a scrolling `.webx-table` frame (server-side);
  `data-webx-reveal="up|fade|scale|stagger"`; `data-webx-parallax="soft|medium|strong"` on a picture that fills its frame (`--webx-parallax-shift`); `data-webx-overlap` on a section (`@webxOverlap($on)` on the root, `@webxOverlapEdge($on, $edge, $narrow)` first inside: the section above is pinned and its `data-webx-overlap-fade` fades, §14.1; root positioned, not cropping; the edge peeks `--webx-overlap-peek` of the screen at rest, never taller than `--webx-overlap-edge-max`, and climbs faster than the page, running ahead of its section — furthest halfway, `--webx-overlap-edge-peak` (0.33 of the screen), then back to `--webx-overlap-edge-rest` (0.03) ahead of it; 0 and 0 keep them together); `<x-webx-back-to-top>`; `<x-webx-share>`;
  `<x-webx-counter :value>`; `<x-webx-countdown to ended>`; `<x-webx-compare :before :after>`; `<x-webx-photos :images autoplay ratio>` (a card's photos turning every 5 s, bars, swipe, pause; one photo — a plain `<img>`; `data-webx-photos-surface` on a linked card);
  `<x-webx-toc for depth side>`; `<x-webx-pagination :paginator>`; `<x-webx-load-more :paginator pages="covered|shown">`;
  `<x-webx-notice-bar place="notice">` (a `module-banners` place, closed versions remembered).
- **Smooth scrolling** — `data-webx-smooth-scroll` on `<html>`: `{"attrs": {"html": {"data-webx-smooth-scroll": true}}}` in `theme/compositions/layout/default.json`, no copy of the layout (Lenis; `-duration`,
  `-easing`, `-wheel` or `--webx-smooth-scroll-*`); native under reduced motion, touch, no JS, a modal, `[data-webx-scroll-native]`. An anchor link shows at once the `data-webx-reveal` holding its target (else it ends under the header by the reveal's distance).
- **Fields** — `<x-webx-field type name label hint required>` for every form of the site; `type`
  `text` `email` `tel` `url` `password` `number` `textarea` `select` `radio` `checkbox`
  (`:choices` → a group, without → one tick) `consent` `date` `file` `hidden`. Value `old()`, error
  `$errors` by the dotted key (`fields.email` is written `fields[email]`); `autocomplete` and
  `inputmode` default by type; `class` on the field, other attributes on the control.
- **The form** — `<x-webx-form :action method id bag :csrf again>` around the fields and
  `<x-webx-form.submit>`: `@csrf`, `@method`, a hidden `_webx_form`. Without JavaScript a plain
  POST, each error back under its field. With it: sent in place, `X-XSRF-TOKEN`, one retry on 419,
  a 422 by field, no second submission, files with progress. `:csrf="false"` only for a door
  guarded otherwise (inbox's intake). `again` (off by default) — «Send another» (`webx-form__again`)
  in place of the fields after the thank-you: them back empty, focus on the first; no JS — a link. `steps` + `<x-webx-form.step name title :when>` — one step at a time (`steps.js`): an indicator, «Next» checks the step (`:check` — the site's door, `webx_step`; 204 or 422), «Back» nothing; an error behind brings its step back; event `webx:form:step`; no JS — fieldsets on one page. `<x-webx-form.summary :fields>` — the answers so far with «Edit» (`summary.js`); hidden without JS. `<x-webx-field type="datetime" :step from to :weekdays timezone>` — a day and a time in the site's zone, sends `[date]` and `[time]`: `datetime.js` draws a calendar (APG grid) and the times, no JS — `date` + `select`; `data-webx-answer` for the summary, `data-webx-focus` for an error.
- **The answer** — `WebxUi\Widgets\Forms\FormResponse`: `FormResponse::message($heading, $html)`,
  `FormResponse::redirect($url)`, `FormResponse::html($view)` (a next step in place of the fields),
  `FormResponse::ok()` (only the event); JSON to the script, a redirect with flash to a plain POST;
  an `HtmlString` heading is HTML (`heading_html`, `FormResponse::plain()` — its text).
  Events on the `<form>`: `webx:form:submit` (cancelable, `detail.data`, `detail.wait(promise)`),
  `webx:form:success`, `webx:form:error`. `data-webx-attribution` on the form (inbox's has it) claims `attribution.js`: on submit it adds `utm_*`, `webx_referrer`, `webx_landing`, `webx_utm_first[…]`; without consent to `statistics` only this page, nothing stored. `<x-webx-field :when="['fields.purpose' => ['agency']]" :unless condition="If chosen: …">` — shown only for those answers above (form.js: `hidden` + `disabled`, one pass; no `required` attribute until shown); the server must drop what is hidden. `view="cards" :columns="3"` on a radio/checkbox group — the same inputs inside labels, choices with `description`, `image` (FORM_STEPS §5; views `field/radio-cards`, `checkbox-cards`).
- **Checks in the browser** — `<x-webx-form :rules="FormRules::for($rules, $attributes, $messages)">`:
  what the browser can check, in the validator's words and the page's language; the rest stays the
  server's. `type="tel" mask` — a phone mask; file fields get drop, a list and Remove.
- **Captcha** — one per site, `WebxUi\Widgets\Captcha`: provider and site key on the settings tab
  "Captcha" (`captcha.*`), else `webx-widgets.captcha` (`WEBX_CAPTCHA_*`, then `WEBX_INBOX_*`); the
  secret — the environment only. `<x-webx-captcha action="…">` inside the form; the server
  `Captcha::check($request, $action)` — null or the refusal, `message()` — the words under `captcha`.
- **Suggestions while typing** — `<x-webx-suggest :action :source name :recent>` (a GET search;
  without `action` a field to stand inside another form). `source` answers HTML of the site's own
  view: `data-webx-option` on what can be chosen, `data-webx-section` + `data-webx-heading` on a
  group. Events `webx:suggest:select` (cancelable), `webx:suggest:results`; the catalogue's `/catalog/suggest`.
- **A list with a search** — `<x-webx-select name :options :value searchable :source>`: a `<select>` without
  JavaScript, with it the loaded options filtered and `source` asked (JSON `{options, more}`); `change` on the select. `:strict="false"` — text with suggestions.
- **Blocks** — with `module-media`: `gallery` (grid; slider: `per_view` 1–3, `effect`, `arrows`, `dots`, `thumbs`, `endless`, `autoplay` + `interval` s; mosaic 1 large + 2: `mosaic` still / slider in threes, the rest hidden lightbox links, `narrow` pair|column, `arrows_at` below|head; head `eyebrow` `intro` `align`; `gap` narrow or a site space step; `captions` under|over — alt on glass; ribbon: `frames` picture, link in a new tab, `badge` none|pin|video, column to the screen edge) `logos` `video` `map` `counters` `countdown` `compare` `toc` `tabs` (`eyebrow`; `wrap` — rows under 40em, `--b-tabs-per-row`; `media_bleed` — the section `site-bleed`, `__inner` keeps the column and is the container, the picture to the page's sides under 40em, a direct child of `.site-main` only; no scrollbar on the row; `transition` instant / fade; `overlap` + `overlap_edge`/`overlap_edge_narrow` as in theme-default, `__inner` is `data-webx-overlap-fade`) (`webx:blocks:offered --install --module=widgets`).
- **Audit** (with `module-audit`) — `widgets.before_consent` (fix `widgets.wait-for-consent`),
  `widgets.banner_off` (fix `widgets.banner-on`), `lightbox_size`, `slider_pause`, `video_pause`,
  `counter_number`, `compare_range`, `toc_target`, `load_more_link`, `zoom_link`, `form_script`,
  `contact_both`. A site's own third parties: `webx-widgets.audit.third-party`.
- **Classes** — the public contract: `webx-<name>`, `webx-<name>__<element>`, modifiers
  `--<variant>`, states `is-*` (`is-open`, `is-current`, `is-flipped`, `is-invalid`, `is-sending`…);
  each component's view lists its own. `html.webx-js` once the runtime runs,
  `html.webx-scroll-locked` under a modal, `html.webx-header-sticky` while the header sticks.
- **Tokens and words** — `--webx-<name>-*` per widget on `:root` from `--site-*`; `--webx-header-height`
  is kept live — the `top` of anything sticky. Words `webx-widgets::widgets.*` in en ru uk de pl fr es it pt tr; each also a prop.

## Change it without forking

- Colours, radius, spacing — the site tokens in `theme/tokens.json`; the widgets follow.
- A widget's own measure — `--webx-<name>-*` on `:root` or `.webx-<name>` in `theme/src/css`.
- Another look — the same `.webx-<name>*` class in `theme/src/css`: it comes later, no `!important`.
- A header's layout (logo in the middle, two rows) — `grid-template-areas` on `.webx-header__bar`; its pieces and slots in theme-default — the composition `header` (an `x-slot:<name>` node is a slot).
- Other markup — `theme/views/vendor/webx-widgets/components/<name>.blade.php`.
- Another control of one field type — `theme/views/vendor/webx-widgets/components/field/<type>.blade.php`; print `{{ $control($attributes) }}` on it.
- An icon — `theme/views/vendor/webx-widgets/icons/<name>.blade.php` (24×24 `<svg>`); a word — the prop or `lang/vendor/webx-widgets/<locale>/widgets.php`.
- Its pieces of the composition `layout` — `widgets.contact-button` (after `footer`): moved or gone by id, `{"remove": ["widgets.contact-button"]}` in `theme/compositions/layout/default.json`.
- A behaviour of the site's own — `webx.widget('name', '[data-…]', (el) => cleanup)` in `theme/src/js/theme.js`.
- HTML added later — `webx.mount(container)`; `webx.unmount(container)` before removing it.
- A map provider — an entry in `webx-widgets.map.providers` with `{key}` in its address; Google — the "Map" tab.

## Do not

- Do not edit `vendor/webx-ui/widgets` or `public/themes/webx-ui/widgets`: an update or a sync
  replaces them; nor import `dist/*.js` into the site's bundle — `@webxWidgets` links them.
- Do not write a colour literally or with a fallback (`var(--site-x, #fff)`), nor style a widget
  through a chain (`.site-main .webx-dialog__title`): one class wins by order.
- Do not add `hidden` to a disclosure's panel in markup: without JavaScript it would never show.
- Do not paste a counter, a pixel, a chat or an embed as it is: wrap it in
  `<x-webx-consent category="…">` or write it `type="text/plain"` / `data-src`. A video is
  `<x-webx-video>`, a map `<x-webx-map>`.
- Do not make "Accept all" bigger or brighter than "Reject all" (one class on purpose), nor put an inbox form in a dialog of your own: link to `#webx-form-<slug>`.
- Do not send a form with a `fetch` of your own, nor print a `<form>` without `@csrf` to a route of
  the app: use `<x-webx-form>` and hook its events.
- Do not write a field's markup of your own, nor validate only in the browser, nor keep a second
  set of rules or messages in JavaScript: `<x-webx-field>` and `FormRules` carry the server's.
- Do not write a search box's or a list's own `fetch`, nor put `<x-webx-suggest action>` in a form (they do not nest).
- Do not type a phone, an address or a network into a template: they live on the Contacts tab.
- Do not format a time with `toLocaleString()`: `<x-webx-hours>` has the words.
- Do not copy the runtime into the theme, nor `Widgets::need()` its behaviours.

## Check your work

- `webx:theme:sync` lists `webx-ui/widgets`; `runtime.css` is in `<head>`, `runtime.js` before `</body>`. Walk it with Tab; turn JavaScript off.
- `php artisan webx:audit:run`, then `audit_issues` with `check: "widgets.before_consent"` — nothing. The "Kitchen sink" pages of `theme-default` show each widget.

## Read more

- [README.md](README.md)
- The guides: https://webx-ui.github.io/webx-ui/guide/widgets and https://webx-ui.github.io/webx-ui/guide/forms (every rule, prop, class and event of the forms)
- The spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_WIDGETS.md
