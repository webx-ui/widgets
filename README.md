# webx-ui/widgets

The interactive pieces every site repeats, done once: logic, keyboard, ARIA and the behaviour
without JavaScript are ready; the look is a skeleton on the site's tokens that the theme repaints.
A page loads only the widgets that stand on it.

Part of [WebX UI](https://github.com/webx-ui/webx-ui). It sits at the bottom of the theme chain of
[`webx-ui/themes`](https://github.com/webx-ui/themes), the way a module does:

```
resources/views          ← the site itself
theme/                   ← the site's local theme
  → webx-ui/theme-default
    → modules, widgets   ← this package: views in views/vendor/webx-widgets, CSS first in the cascade
```

> Under way: the loading, the runtime with the light behaviours, the mobile menu, the header, cookie
> consent, the dropdown, contacts, the language switcher, the slider, the lightbox, the video and
> the map, and the gallery, logos, video and map blocks are here.

## What is in it

| Behaviour    | Switched on by                               | Does                                                                           |
| ------------ | -------------------------------------------- | ------------------------------------------------------------------------------ |
| `disclosure` | `data-webx-disclosure="<id>"`                | shows and hides `#<id>`; Esc and a click outside close it; `aria-expanded`     |
| `dialog`     | `data-webx-dialog="<id>"`, `<x-webx-dialog>` | opens `<dialog id>` as a modal; focus stays in it and comes back to the opener |
| `tabs`       | `data-webx-tabs`, `<x-webx-tabs>`            | the panels' headings become a tab list; arrows, Home, End                      |
| `accordion`  | `data-webx-accordion="single"`               | one `<details>` open at a time                                                 |

```blade
<button data-webx-disclosure="more">More</button>
<div id="more">…</div>

<a href="#callback" data-webx-dialog="callback">Call me back</a>
<x-webx-dialog id="callback" title="Call me back">…</x-webx-dialog>

<x-webx-tabs label="Product">
    <x-webx-tabs.panel title="Delivery">…</x-webx-tabs.panel>
    <x-webx-tabs.panel title="Returns">…</x-webx-tabs.panel>
</x-webx-tabs>

<div data-webx-accordion="single">
    <details><summary>Question</summary>Answer</details>
</div>
```

Without JavaScript: the disclosure's panel is shown, the dialog opens through `:target` from a link
to `#<id>`, the tabs are headings above their panels, the accordion is plain `<details>`.

## Video

```blade
<x-webx-video src="https://www.youtube.com/watch?v=…" :poster="$picture" title="Our workshop" />
<x-webx-video src="https://vimeo.com/…" ratio="4:3" />
<x-webx-video :file="$media" :poster="$picture" />
```

A YouTube or Vimeo video is a poster and a play button; the player (from `youtube-nocookie.com`,
Vimeo with `dnt=1`) goes in only on a click. Until the visitor agrees to `media` the server prints a
placeholder instead — the poster, a line saying where the video loads from, **Load** (this one) and
**Always load videos** (the consent, without a reload) — and the page asks the provider for
nothing. The poster is always the site's: the one given, or the video's preview, which the site
fetches once into its media library (folder "Video posters") after the first page that shows the
video. A file of the site is a `<video preload="none">` with no consent to ask for. The frame keeps
its ratio (16/9 unless told) before anything loads; without JavaScript the video is a link.

```blade
<x-webx-video variant="background" :file="$media" :poster="$picture" ratio="21/9">
    <h1>Made to move</h1>
</x-webx-video>
```

The background of a first screen is a file of the site only — YouTube behind a hero would be asked
on every showing — muted, looping, without controls, behind what the tag wraps; the ratio is the
least the frame takes, the text makes it taller. It plays only on screen and with the tab in front,
never by itself under reduced motion or on a connection saving data; the poster stays until the
first frame. A pause button is always in its corner (WCAG 2.2.2), and a pause is remembered for the
next page. Without JavaScript: the poster, nothing moving.

## Map

```blade
<x-webx-map from="settings" />
<x-webx-map :lat="12.34" :lng="-34.56" :zoom="15" marker="1 Example Street, Sample City" height="24rem" />
```

Leaflet over the tiles of OpenStreetMap, which need no key. `from="settings"` is the main address of
the Contacts tab of the settings — its text, its coordinates, its link to a map; an address without
coordinates is printed alone. The server prints the place — the address and **Open in maps** — in a
frame of the map's height; until the visitor agrees to `media` it carries a line saying where the
map loads from, **Load** (this one) and **Always load maps** (the consent, without a reload), and
the page asks for no tile. The attribution of the tiles is printed in the corner of every map. The
wheel and one finger move the page until the map is clicked or focused. Without JavaScript the map
is its address and a link. Other tiles — MapTiler or any address with `{z}/{x}/{y}` and a `{key}` —
are an entry of `webx-widgets.map.providers` and its name in `webx-widgets.map.provider`.

## Page tools

```blade
<x-webx-share title="Our new workshop" :networks="['facebook', 'x', 'linkedin', 'email', 'copy']" />
<x-webx-back-to-top corner="bottom-end" :after="2" />
<ul data-webx-reveal="stagger">…</ul>
```

Every `<table>` without a class on a page — what a rich text field stores — is put by the server in
a frame that scrolls sideways, its caption above the frame so that it does not scroll away, with a
shadow on the side there is more behind. `data-webx-reveal` (`up`, `fade`, `scale`, `stagger`) brings
an element in as it scrolls into view; only the script hides anything, so without JavaScript or with
reduced motion it is simply there. Share is plain links to each network, Copy link, and the system's
share sheet on a phone — no network's script. Back to top comes after two screens and stands above
the cookie banner and the quick contact of its corner.

## Counter and countdown

```blade
<x-webx-counter :value="3000" suffix="+" />
<x-webx-countdown to="2026-12-31 18:00" ended="The sale is over" />
```

A counter prints its final number, as the page's language writes it — what search engines and
screen readers read — and counts up to it when it comes into view; one already on the screen when
the page opens, or under reduced motion, keeps its number. A countdown runs to a wall time of the
site's time zone (the one of the opening hours on the Contacts tab, else the application's) or to a
moment with its offset, and ticks every second from the clock, so a page from a cache is right. At
the end it says `ended`, or goes — with the nearest `[data-webx-countdown-scope]` around it. Without
JavaScript it is the line "Ends on …" rather than digits standing still.

## Before and after, table of contents

```blade
<x-webx-compare :before="$old" :after="$new" :start="30" />
<x-webx-toc>…a long text with h2 and h3…</x-webx-toc>
```

Before and after lays the after picture over the before one, in the shape the media library
stored for them, and cuts it at a divider that is a range input: the arrows move it, a screen
reader hears a slider, a mouse drags it and so does a finger that moves sideways. Without
JavaScript the two stand side by side with their labels.

A table of contents is made by the server from the finished page — of its slot, of an element by
id (`for`) or of `<main>` — and the headings get their ids there, so every link works without
JavaScript. Wide, the list stands beside the text and sticks under the header; narrow, it folds
into a bar above the text that names the section being read.

## Zoom

```blade
<x-webx-zoom :image="$photo" :src="$photo->thumbUrl(1200)" mode="side" />
```

A picture whose details matter: a mouse magnifies it inside its frame, or in a panel beside it where
its container has room; two fingers pinch it on a phone, a double tap is twice the size and back,
and one finger still scrolls the page and swipes a slider. The picture is a link to the original
that opens the lightbox — the way in for a keyboard — and the original is fetched only when someone
looks closer. `data-webx-zoom` on a gallery's lightbox link does the same; the Gallery block's
slider and the catalog's product page use it.

## Lists: pages and "Show more"

```blade
<x-webx-load-more :paginator="$articles">
    @foreach ($articles as $article) <article>…</article> @endforeach
</x-webx-load-more>

<x-webx-pagination :paginator="$articles" />
```

Both take what `->paginate()` answered as it is. The pagination is the links of the pages —
previous, the numbers around this one with the first and the last, next — with a stylesheet and
no script; the first page is the list's own address, not `?page=1`. Where the pagination is
narrower than 30rem (a phone) it shows the first, this and the last page, and previous and next
are arrows with their word kept for a screen reader.

"Show more" prints the list and those links, and its script puts a button over them: it fetches the
next page as any visitor gets it, adds the items of the same list to this one, moves the focus to
the first new item and the address to that page (`replaceState`: Back leaves the list, a reload
lands on the page last loaded). No endpoint: an item is printed by the module's view, a theme's
override included. `pages="shown"` keeps the links under the button. Without JavaScript, and for a
search engine, the links are all there is. A list of the slot's own is marked
`data-webx-load-more-list`; a module's own links go in the `links` slot.

## Notice bar

```blade
<x-webx-notice-bar />                       {{-- in the layout, before the header --}}
<x-webx-notice-bar>Free delivery until Sunday</x-webx-notice-bar>
```

A bar above the header with a close button. It says the first banner of the place `notice` of
`webx-ui/module-banners` (a banner of words only, changed in the panel), else its slot, else
nothing. Closed, it stays closed by the version of its words — a new text comes back for
everybody — and a closed bar is hidden by a line in the head before the page is painted. It stands
in the flow, so the sticky header measures what it always did.

## Fields of a form

```blade
<x-webx-field type="email" name="fields.email" label="Email" required />
<x-webx-field type="radio" name="delivery" label="Delivery" :choices="$methods" inline />
<x-webx-field type="consent" name="terms" required>I agree to <a href="/terms">the terms</a></x-webx-field>
```

One markup for every form of the site: `text`, `email`, `tel`, `url`, `password` (with Show),
`number`, `textarea`, `select`, `radio`, `checkbox` (a group with `:choices`, one tick without),
`consent`, `date`, `file`, `hidden`. The value is `old($name)` and the error
`$errors->first($name)`; a dotted name is sent with brackets (`fields[email]`) and its error found
by the dots, so a form posted without JavaScript comes back with each error under its field.
`autocomplete`, `inputmode` and `enterkeyhint` default by type. The error is described
(`aria-describedby`) and `aria-invalid`; a group is a `<fieldset>` with a `<legend>`.

## The form

```blade
<x-webx-form :action="route('login')" class="sign-in">
    <x-webx-field type="email" name="email" :label="__('auth.email')" required />
    <x-webx-field type="password" name="password" :label="__('auth.password')" required />
    <x-webx-form.submit>{{ __('auth.sign_in') }}</x-webx-form.submit>
</x-webx-form>
```

```php
return FormResponse::redirect(route('account'));            // or ::message($heading, $html), ::html($view), ::ok()
```

`@csrf` and `@method` are printed by the form (`:csrf="false"` for a door that guards itself).
Without JavaScript it is a plain POST and the page comes back with the errors under the fields or
the answer in place. With it (`dist/form.js`, only where a form stands) the same form is sent in
place: the token from the XSRF-TOKEN cookie and one retry with a fresh one on a 419
(`GET webx/widgets/csrf`), the errors of a 422 into their fields and the rest into the form's
alert, no second submission while the first is on its way, the answer in a live region, files
with progress. `FormResponse` answers JSON to the script and a redirect to the plain POST, so a
controller writes one line. A module hooks `webx:form:submit` (cancelable), `webx:form:success`
and `webx:form:error` on the form; one with something to finish first — a captcha to run — holds
the form busy with `event.detail.wait(promise)`, and the form is sent when it resolves.

## Checks in the browser

```blade
<x-webx-form :action="route('checkout.contact')" :rules="FormRules::for($rules, $attributes)">
    <x-webx-field type="tel" name="contact.phone" :label="__('checkout.phone')" mask />
    <x-webx-field type="file" name="documents" :label="__('checkout.documents')" multiple />
</x-webx-form>
```

The rules are the ones the controller validates by, written once. `FormRules` hands each field its
part: the checks a browser can run — `required`, `email`, a length, a number, `regex`, `in`, a
file's type and size, `confirmed`, a literal date — each with the line Laravel's validator writes
when it fails, asked of the validator itself in the page's language. So the browser says exactly
what the server would; `unique`, `exists` and closures stay the server's. A touched field is
checked when it is left and every field on submit; the browser's own bubbles are off while the
script runs. `mask` on a phone field puts the number in international form (`+48 512 345 678`)
without refusing a paste or an autofill; a file field takes files dropped on it, lists them with
their size and a preview, and lets one go before sending. The guide:
https://webx-ui.github.io/webx-ui/guide/forms.

## Suggestions while typing

```blade
<x-webx-suggest :action="route('webx.catalog.search')" :source="route('webx.catalog.suggest')" :recent="5" />
```

A search box that asks the site as the visitor types. Without JavaScript it is a GET search to
the results page; with it, an ARIA combobox over the HTML the site answers — its own sections,
counts and cards — where `data-webx-option` marks what can be chosen and `data-webx-section` with a
`data-webx-heading` a group. Arrows, Home/End, Enter, Escape; the stale request is aborted;
recent searches in the browser with `:recent`. Without `action` it is a field to put inside another
form: a value chosen fills it. `module-catalog` answers at `/catalog/suggest`. The guide:
https://webx-ui.github.io/webx-ui/guide/forms#suggestions-while-typing.

## Blocks

On a site with `webx-ui/module-blocks` and `webx-ui/module-media` the package offers eight block
types — `webx:setup` installs them, or `php artisan webx:blocks:offered --install --module=widgets`:

- **Gallery** — pictures of the media library as a grid (as many columns as the column has room
  for, up to the number set) or as the slider's gallery with thumbnails; a click opens the picture
  in the lightbox and pages through that block's pictures; a picture's title is its caption.
- **Logos** — a name, a logo and a link each, in the slider's running strip with its pause button.
- **Video** — an address of YouTube or Vimeo or a video of the library (a switch says which), a
  poster (empty: the video's own preview), a caption under it and a shape — 16:9, 4:3, 1:1 or
  9:16, the last two held to a phone's width. A link waits for consent like any `<x-webx-video>`;
  an address of no known provider, a file gone from the library or nothing at all prints nothing.
- **Map** — the main address of the Contacts tab or coordinates with an address of their own (a
  switch says which), a zoom and a height: low, medium or tall. Nowhere to show prints nothing.
- **Counters** — a number (3000, 3,000 and 3 000 alike; 4,9 or 4.9), what stands before and after
  it, and what it counts, each; a row that wraps to two in a narrow column.
- **Countdown** — a heading, a line, a date and a time of the site's time zone, and at the end a
  text or nothing: a countdown over with nothing to say is not printed, and goes from the page the
  second it ends.
- **Before and after** — two pictures of the library, their labels (empty: "Before" and "After" in
  the page's language), where the divider starts and a caption; one picture alone prints nothing.
- **Table of contents** — of the whole page, where the block stands, or of a text of its own that
  the list stands beside (a switch says which); the side, and whether the third level is listed.

Installed once, they belong to the site: change them in the panel, an update never overwrites them.

## In the site audit

With `webx-ui/module-audit` every crawled page is also read for what the widgets promise:

- **Loads before consent** (error) — a YouTube, Vimeo, Google Maps or OpenStreetMap iframe, or a
  known counter or pixel, that the page asks for before the visitor answers the banner. When the
  code was pasted into content (a text block, a page's body), "Make it wait for consent" rewrites
  it there: an iframe's `src` becomes `data-src`, a script becomes `type="text/plain"`, both get
  `data-webx-consent`. Code a template prints is the template's to fix: `<x-webx-consent>` or the
  video and map blocks. A site's own third parties: `webx-widgets.audit.third-party`.
- **Banner off with third parties on the site** (warning) — "Turn the cookie banner on" writes the
  setting of the Cookie tab, which wins over the config.
- **Lightbox link without the picture size**, **moving slider without a pause button**, **background
  video without a pause button**, **before and after without its slider** (a theme's override that
  lost it), **a table of contents linking to a heading the page does not have**, **"Show more" without a link
  to its next page** (search engines do not press buttons), **a form without its script** (a
  form that sends in place on a page loading no `form.js` — a layout with neither `@webxTheme` nor
  `@webxWidgets`, or files never synced) — warnings; **a counter without
  its number** (an override that left it to the script) and **both quick-contact widgets on one
  page** — notices.

## How a page gets it

`@webxTheme` leaves a marker in `<head>`. When the response is ready — the header and the footer
rendered too — the marker becomes the stylesheets and the scripts go before `</body>`:

- the runtime (`dist/runtime.js`, `dist/runtime.css`) on every page of a site with a theme;
- a widget's own files only where a component claimed it, `Widgets::need('slider')`, or a link on
  the page asked for it — `data-webx-lightbox` claims the lightbox wherever it was written.

The files are published by `php artisan webx:theme:sync` next to the themes', under
`public/themes/webx-ui/widgets/<hash>/`.

### On a site without a theme

`@webxTheme` prints nothing there, so the marker never stands on the page — and every
`<x-webx-form>`, an inbox form among them, falls back to a plain POST with a reload. The layout
asks for the scripts with one line before `</body>`:

```blade
    @webxWidgets
</body>
```

The page is then finished the same way, with the scripts — the runtime, `form.js`, `field.js`,
and whatever else a component on it claimed — and without a stylesheet: those are made of a
theme's tokens, which the site does not have, so the forms wear the site's own CSS on the same
`webx-field-*` and `webx-form-*` classes. No cookie banner either: an unstyled one over the page
would be worse than none. Under a theme the directive adds nothing, so a layout that keeps it
after adopting one loses nothing.

`webx:theme:sync` publishes the widgets without a theme too (and nothing else);
`webx:setup` runs it, adds it to `post-update-cmd`, and writes `@webxWidgets` into the layout of
`--no-theme`. A site set up before: add the line and run the sync once. Do not import
`vendor/webx-ui/widgets/dist/*.js` into the site's own bundle — the package is not on npm, the
files change with every update, and a bundled copy outlives the one the page links.

In the browser `webx.mount(root)` starts whatever is new inside `root` — the same call the blocks
runtime answers — and `webx.unmount(root)` lets it go. `webx.widget(name, selector, setup)`
registers a widget of the site's own on the same terms.

## Install

It comes with `webx-ui/theme-default`. Alone:

```bash
composer require webx-ui/widgets
php artisan webx:theme:sync
```

## Working on it

`resources/js` and `resources/css` are built into `dist/` by Vite, and `dist/` is committed. After
an edit, in this directory:

```bash
npx vite build
```

`dist/sources.json` records what the build was made from; the package's test fails when the
sources and `dist/` disagree, and when the runtime grows past 16 KB gzip.

## License

MIT
