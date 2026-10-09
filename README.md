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

## Map

```blade
<x-webx-map from="settings" />
<x-webx-map :lat="51.5074" :lng="-0.1278" :zoom="15" marker="1 Example Street, London" height="24rem" />
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

## Blocks

On a site with `webx-ui/module-blocks` and `webx-ui/module-media` the package offers four block
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
- **Lightbox link without the picture size**, **moving slider without a pause button** (a theme's
  override that lost it) — warnings; **both quick-contact widgets on one page** — a notice.

## How a page gets it

`@webxTheme` leaves a marker in `<head>`. When the response is ready — the header and the footer
rendered too — the marker becomes the stylesheets and the scripts go before `</body>`:

- the runtime (`dist/runtime.js`, `dist/runtime.css`) on every page of a site with a theme;
- a widget's own files only where a component claimed it, `Widgets::need('slider')`, or a link on
  the page asked for it — `data-webx-lightbox` claims the lightbox wherever it was written.

The files are published by `php artisan webx:theme:sync` next to the themes', under
`public/themes/webx-ui/widgets/<hash>/`.

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
