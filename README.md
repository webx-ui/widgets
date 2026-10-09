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

> Early days: this release holds the loading and the shared runtime with the light behaviours —
> disclosure, dialog, tabs, accordion. The mobile menu, the header, cookie consent, the dropdown,
> contacts, the language switcher, sliders, the lightbox, video and maps arrive in the next ones.

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

## How a page gets it

`@webxTheme` leaves a marker in `<head>`. When the response is ready — the header and the footer
rendered too — the marker becomes the stylesheets and the scripts go before `</body>`:

- the runtime (`dist/runtime.js`, `dist/runtime.css`) on every page of a site with a theme;
- a widget's own files only where a component claimed it: `Widgets::need('slider')`.

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
