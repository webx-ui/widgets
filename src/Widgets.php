<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use InvalidArgumentException;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Contracts\HeadPart;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Widgets\View\Components\Lightbox;

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
 */
final class Widgets implements HeadPart
{
    public const string NAME = 'webx-ui/widgets';

    public const string MARKER = '<!--webx-widgets-->';

    /** The behaviours inside the runtime: claiming one is allowed and loads nothing more. */
    public const array RUNTIME = ['disclosure', 'dialog', 'tabs', 'accordion', 'mobile-menu', 'header', 'dropdown', 'form-dialog'];

    /** A form's slug, the shape `module-inbox` gives it. */
    private const string SLUG = '/^[a-z0-9][a-z0-9_-]*$/i';

    /** @var array<string, true> */
    private array $claimed = [];

    /** @var array<string, true> */
    private array $forms = [];

    private bool $dialogsDone = false;

    public function __construct(
        private readonly BottomLayers $layers,
        private readonly ThemeAssets $assets,
        private readonly UrlGenerator $urls,
        private readonly Config $config,
        private readonly Consent $consent,
        private readonly FormDialogs $dialogs,
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

    /** @return list<string> What this page claimed, in the order it did. */
    public function claimed(): array
    {
        return array_keys($this->claimed);
    }

    public function head(): string
    {
        return self::MARKER;
    }

    /**
     * The page with the marker replaced by the stylesheets and the scripts placed before
     * `</body>`. A page without the marker — no `@webxTheme` — is left as it is.
     */
    public function finish(string $html): string
    {
        if (! str_contains($html, self::MARKER)) {
            return $html;
        }

        // A response that never went through the router has not had its dialogs yet.
        $html = $this->withDialogs($html);
        $at = (int) strpos($html, self::MARKER);
        $styles = [];
        $scripts = [];

        if ($this->assets->current($this->layer()) === null) {
            $styles[] = $this->config->get('app.debug') ? '<!-- webx-widgets: not published: php artisan webx:theme:sync -->' : '';
        }

        // The consent banner is on every page (§9): it asks until answered, and its script is
        // what switches on whatever third-party code waits for an answer — with the banner off too.
        $bottom = '';

        // Unpublished, its script could not answer: no banner, as no stylesheet.
        if ($this->url('consent', 'js') !== null) {
            $this->need('consent');
            $bottom = $this->consent->render($html)."\n";

            if ($this->consent->enabled()) {
                $styles[] = $this->consent->googleDefault();
            }
        }

        // A link to a picture with `data-webx-lightbox` claims the lightbox wherever it was written —
        // a block, a module's view, a theme's partial — as a form's opener claims its dialog (§8).
        if (Lightbox::wanted($html) && $this->built('lightbox', 'js')) {
            $this->need('lightbox');
        }

        if (isset($this->claimed['lightbox']) && $this->url('lightbox', 'js') !== null) {
            $bottom .= view('webx-widgets::lightbox')->render()."\n";
        }

        foreach (['runtime', ...array_diff($this->claimed(), self::RUNTIME)] as $widget) {
            if (($url = $this->url($widget, 'css')) !== null) {
                $styles[] = '<link rel="stylesheet" href="'.e($url).'">';
            }

            if (($url = $this->url($widget, 'js')) !== null) {
                $scripts[] = '<script type="module" src="'.e($url).'"></script>';
            }
        }

        // The first marker takes the stylesheets; a second `@webxTheme` on the page prints nothing twice.
        $html = substr($html, 0, $at).implode("\n", array_filter($styles)).str_replace(self::MARKER, '', substr($html, $at + strlen(self::MARKER)));

        if ($scripts === [] && $bottom === '') {
            return $html;
        }

        $body = strripos($html, '</body>');
        $scripts = $bottom.implode("\n", $scripts)."\n";

        return $body === false ? $html.$scripts : substr($html, 0, $body).$scripts.substr($html, $body);
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
        if ($this->dialogsDone || ! str_contains($html, self::MARKER)) {
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
