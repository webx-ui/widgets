<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use InvalidArgumentException;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Contracts\HeadPart;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Widgets\Prose\Contents;
use WebxUi\Widgets\Prose\Tables;
use WebxUi\Widgets\View\Components\Lightbox;
use WebxUi\Widgets\View\Components\NoticeBar;
use WebxUi\Widgets\View\Components\Zoom;

/**
 * What the page asked for, and the tags that load it (spec §4).
 *
 * A component claims its widget while it renders — `Widgets::need('slider')` — and the page gets
 * that widget's stylesheet in `<head>` and its script before `</body>`, and nothing for a widget
 * it does not show. The shared runtime — the light behaviours of §6, which an attribute in any
 * template can switch on without a component to claim them — is on every page that has a theme.
 *
 * `@webxTheme` stands in the head, which with components prints after the content but before
 * the header and the footer: a widget claimed there would be too late for a head written on
 * the spot. So the head holds a marker, and the response is finished once the whole page has
 * rendered — every claim known — by `finish()`: the marker becomes the stylesheets, the scripts
 * go before `</body>`. A page rendered outside a request keeps the marker, an HTML comment.
 *
 * A site without a theme has no `@webxTheme` to print, and stylesheets made of a theme's tokens
 * would be of no use to it — but `<x-webx-form>` needs its scripts to send in place and to check
 * in the browser. Its layout writes `@webxWidgets`, which prints `SCRIPTS`: the page is finished
 * the same way, with the scripts and without a stylesheet or the consent banner.
 */
final class Widgets implements HeadPart
{
    public const string NAME = 'webx-ui/widgets';

    public const string MARKER = '<!--webx-widgets-->';

    /** What `@webxWidgets` prints: the scripts only, for a site without a theme. */
    public const string SCRIPTS = '<!--webx-widgets:scripts-->';

    /**
     * Where Consent Mode's default goes when something above `@webxTheme` may start a Google tag:
     * the head place of `webx-ui/module-snippets` prints it before its codes (spec §9.3). Without
     * it the default stays where it always was, with the stylesheets.
     */
    public const string EARLY = '<!--webx-widgets:early-->';

    /** The behaviours inside the runtime: claiming one is allowed and loads nothing more. */
    public const array RUNTIME = ['disclosure', 'dialog', 'tabs', 'accordion', 'mobile-menu', 'header', 'dropdown', 'form-dialog', 'reveal'];

    /** A form's slug, the shape `module-inbox` gives it. */
    private const string SLUG = '/^[a-z0-9][a-z0-9_-]*$/i';

    /** @var array<string, true> */
    private array $claimed = [];

    /** @var array<string, true> */
    private array $forms = [];

    private bool $dialogsDone = false;

    /** @var array<string, int> */
    private array $sequences = [];

    public function __construct(
        private readonly BottomLayers $layers,
        private readonly ThemeAssets $assets,
        private readonly UrlGenerator $urls,
        private readonly Config $config,
        private readonly Consent $consent,
        private readonly FormDialogs $dialogs,
        private readonly ThemeChain $chain,
    ) {}

    /** The package's own directory: `dist/`, `resources/`, `lang/` are under it. */
    public static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * Claim a widget for this page. A name the package does not have is a typo in a template,
     * and a typo would otherwise be a widget that silently never starts.
     */
    public function need(string $widget): void
    {
        if (! in_array($widget, self::RUNTIME, true) && ! $this->built($widget, 'js') && ! $this->built($widget, 'css')) {
            throw new InvalidArgumentException("There is no widget \"{$widget}\" in ".self::NAME.'.');
        }

        $this->claimed[$widget] = true;
    }

    /**
     * Claim a form of `module-inbox` for a dialog on this page (spec §6.4): anything with
     * `data-webx-form="<slug>"` opens it. Printed once at the end of `<body>` however many
     * buttons lead to it. A link written in a template or a block is found on the page without
     * a claim (`FormDialogs::openers()`); this is for an opener the server never prints.
     */
    public function form(string $slug): void
    {
        if (preg_match(self::SLUG, $slug) !== 1) {
            throw new InvalidArgumentException("\"{$slug}\" is not the slug of a form.");
        }

        $this->forms[$slug] = true;
    }

    /** @return list<string> The forms this page claimed for a dialog. */
    public function forms(): array
    {
        return array_keys($this->forms);
    }

    /**
     * 1, 2, 3… per name for this page: an id two widgets of a kind on one page do not share, and
     * the same on every rendering of it.
     */
    public function sequence(string $name): int
    {
        return $this->sequences[$name] = ($this->sequences[$name] ?? 0) + 1;
    }

    /**
     * Whether a page with a theme gets the consent script — built and published — which is what
     * releases any code waiting for an answer. `webx:doctor` of a module with such code asks it.
     */
    public function consentReady(): bool
    {
        return $this->url('consent', 'js') !== null;
    }

    /** @return list<string> What this page claimed, in the order it did. */
    public function claimed(): array
    {
        return array_keys($this->claimed);
    }

    public function head(): string
    {
        return self::MARKER;
    }

    /** Whether the page has either marker — `@webxTheme`'s or `@webxWidgets`'. */
    public static function marked(string $html): bool
    {
        return str_contains($html, self::MARKER) || str_contains($html, self::SCRIPTS);
    }

    /**
     * The page with the marker replaced by the stylesheets and the scripts placed before
     * `</body>`. A page with neither `@webxTheme` nor `@webxWidgets` is left as it is. With
     * `@webxWidgets` alone it gets the scripts and no stylesheet: those are made of a theme's
     * tokens, which a site without a theme does not have.
     */
    public function finish(string $html): string
    {
        if (! self::marked($html)) {
            return str_replace(self::EARLY, '', $html);
        }

        // A layout with both gets the theme's head; `@webxWidgets` then adds nothing.
        $styled = str_contains($html, self::MARKER);
        $html = str_replace(self::SCRIPTS, '', $html);

        // A response that never went through the router has not had its dialogs yet.
        $html = $this->withDialogs($html);
        $html = $this->prepare($html);

        $styles = [];
        $scripts = [];

        if ($this->assets->current($this->layer()) === null && $this->config->get('app.debug')) {
            // Without a theme there are no stylesheets to stand in for: the hint goes with the scripts.
            if ($styled) {
                $styles[] = '<!-- webx-widgets: not published: php artisan webx:theme:sync -->';
            } else {
                $scripts[] = '<!-- webx-widgets: not published: php artisan webx:theme:sync -->';
            }
        }

        $styles[] = $this->noticeHead($html);
        $early = '';

        // The consent banner is on every page (§9): it asks until answered, and its script is
        // what switches on whatever third-party code waits for an answer — with the banner off too.
        $bottom = '';

        // Unpublished, its script could not answer: no banner, as no stylesheet.
        // Not without a theme: an unstyled banner across the page would be worse than none.
        if ($styled && $this->url('consent', 'js') !== null) {
            $this->need('consent');
            $bottom = $this->consent->render($html)."\n";

            if ($this->consent->enabled()) {
                // Before every tag the page may start: on the early marker when the page has one.
                if (str_contains($html, self::EARLY)) {
                    $early = $this->consent->googleDefault();
                } else {
                    $styles[] = $this->consent->googleDefault();
                }
            }
        }

        $this->claimAttributes($html);

        if (($lightbox = $this->lightbox()) !== '') {
            $bottom .= $lightbox."\n";
        }

        // The first early marker takes the default; any other is only a comment to drop.
        if (str_contains($html, self::EARLY)) {
            $at = (int) strpos($html, self::EARLY);
            $html = substr($html, 0, $at).$early.str_replace(self::EARLY, '', substr($html, $at + strlen(self::EARLY)));
        }

        [$links, $sources] = $this->tags();
        $styles = [...$styles, ...$links];
        $scripts = [...$scripts, ...$sources];

        // The first marker takes the stylesheets; a second `@webxTheme` on the page prints nothing twice.
        if ($styled) {
            $at = (int) strpos($html, self::MARKER);
            $html = substr($html, 0, $at).implode("\n", array_filter($styles)).str_replace(self::MARKER, '', substr($html, $at + strlen(self::MARKER)));
        }

        if ($scripts === [] && $bottom === '') {
            return $html;
        }

        $body = strripos($html, '</body>');
        $scripts = $bottom.implode("\n", $scripts)."\n";

        return $body === false ? $html.$scripts : substr($html, 0, $body).$scripts.substr($html, $body);
    }

    /**
     * A piece of a page drawn on its own — a block the panel swaps into a page that is already
     * there — finished as `finish()` finishes a page, but handed back in parts: the panel puts
     * them into a document whose head and body were printed long before this piece existed
     * (WEBX_UI_WIDGETS.md §4). `head` are the stylesheets the claims of the piece load, `body`
     * what goes before `</body>`: the scripts, the lightbox's settings, the dialogs of the forms
     * it opens. The runtime is listed too; the page that has it already keeps one.
     *
     * No consent banner: the page the piece goes into printed it, or has no theme to show it
     * with. No stylesheet on a site without a theme, as `@webxWidgets` has none either.
     *
     * @return array{html: string, head: list<string>, body: list<string>}
     */
    public function piece(string $html): array
    {
        $html = $this->prepare($html);
        $this->claimAttributes($html);
        $body = [];

        $dialogs = $this->dialogs->render([...$this->forms(), ...FormDialogs::openers($html)]);

        if ($dialogs !== '') {
            $this->need('form-dialog');
            $body[] = $dialogs;
        }

        if (($lightbox = $this->lightbox()) !== '') {
            $body[] = $lightbox;
        }

        [$links, $scripts] = $this->tags();
        $styled = ! $this->chain->isEmpty();

        return [
            'html' => $html,
            'head' => $styled ? array_values(array_filter([$this->noticeHead($html), ...$links])) : [],
            'body' => [...$body, ...$scripts],
        ];
    }

    /**
     * The page with the dialogs of its forms before `</body>` (§6.4), once per response.
     *
     * Called as soon as the route's response is prepared, before `finish()`: by the time the
     * whole response is handled the session is saved and has dropped its flash — the
     * thank-you and the errors a form sent without JavaScript came back with, which the form
     * in the dialog prints, and which open the dialog.
     */
    public function withDialogs(string $html): string
    {
        if ($this->dialogsDone || ! self::marked($html)) {
            return $html;
        }

        $this->dialogsDone = true;
        $dialogs = $this->dialogs->render([...$this->forms(), ...FormDialogs::openers($html)]);

        if ($dialogs === '') {
            return $html;
        }

        $this->need('form-dialog');
        $body = strripos($html, '</body>');

        return $body === false ? $html.$dialogs : substr($html, 0, $body).$dialogs."\n".substr($html, $body);
    }

    /** Forget the claims: the next request of a long-lived worker starts with none. */
    public function flush(): void
    {
        $this->claimed = [];
        $this->forms = [];
        $this->dialogsDone = false;
        $this->sequences = [];
    }

    /**
     * What a page or a piece of one is changed into before its claims are counted: the tables of
     * prose in their scrollers, the lists of the contents filled.
     */
    private function prepare(string $html): string
    {
        // Every table of prose in a scroller of its own (§14), whichever module printed the
        // prose: the shadows at its edges are a file of their own, claimed when there is one.
        [$html, $tables] = Tables::wrap($html, (string) __('webx-widgets::widgets.table.label'));

        if ($tables > 0) {
            $this->need('table');
        }

        // The lists of `<x-webx-toc>`, made of headings that may be printed after them, by any
        // module: the headings get their ids here, so the links work without JavaScript (§14).
        if (str_contains($html, '<!--webx-toc:')) {
            [$html] = Contents::fill($html, static fn (array $list): string => view('webx-widgets::toc', $list)->render(), app()->getLocale());
        }

        return $html;
    }

    /** The widgets an attribute asks for, with no component there to claim them. */
    private function claimAttributes(string $html): void
    {
        // A link to a picture with `data-webx-lightbox` claims the lightbox wherever it was written —
        // a block, a module's view, a theme's partial — as a form's opener claims its dialog (§8).
        if (Lightbox::wanted($html) && $this->built('lightbox', 'js')) {
            $this->need('lightbox');
        }

        // A picture with `data-webx-zoom` claims the zoom the same way: a gallery's link, a slide (§14).
        if (Zoom::wanted($html) && $this->built('zoom', 'js')) {
            $this->need('zoom');
        }
    }

    /**
     * An announcement bar the visitor closed is hidden before the body is painted (§14): its
     * script comes at the end of the page, too late not to flash it.
     */
    private function noticeHead(string $html): string
    {
        if (isset($this->claimed['notice-bar']) && preg_match_all('/data-webx-notice-bar="([A-Za-z0-9-]+)"/', $html, $bars) > 0) {
            return NoticeBar::head(array_values(array_unique($bars[1])));
        }

        return '';
    }

    /** The lightbox's settings, once the lightbox is claimed and its script is there to read them. */
    private function lightbox(): string
    {
        return isset($this->claimed['lightbox']) && $this->url('lightbox', 'js') !== null ? view('webx-widgets::lightbox')->render() : '';
    }

    /**
     * The tags of the runtime and of every widget claimed so far: the stylesheets and the scripts.
     *
     * @return array{list<string>, list<string>}
     */
    private function tags(): array
    {
        $styles = [];
        $scripts = [];

        foreach (['runtime', ...array_diff($this->claimed(), self::RUNTIME)] as $widget) {
            if (($url = $this->url($widget, 'css')) !== null) {
                $styles[] = '<link rel="stylesheet" href="'.e($url).'">';
            }

            if (($url = $this->url($widget, 'js')) !== null) {
                $scripts[] = '<script type="module" src="'.e($url).'"></script>';
            }
        }

        return [$styles, $scripts];
    }

    private function built(string $widget, string $extension): bool
    {
        return preg_match('/^[a-z][a-z0-9-]*$/', $widget) === 1 && is_file(self::path()."/dist/{$widget}.{$extension}");
    }

    /** Null while the file is not built or not published: the page loses the widget, not its 200. */
    private function url(string $widget, string $extension): ?string
    {
        return $this->built($widget, $extension) ? $this->assets->url($this->layer(), "{$widget}.{$extension}", $this->urls) : null;
    }

    private function layer(): ThemeManifest
    {
        return $this->layers->get(self::NAME) ?? $this->layers->add(self::NAME, self::path());
    }
}
