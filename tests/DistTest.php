<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Themes\Checks\CssCheck;
use WebxUi\Themes\Checks\CssFinding;
use WebxUi\Themes\Vocabulary;
use WebxUi\Widgets\Widgets;

/**
 * What ships in dist/ and what it may cost: it is committed and installed from Composer with no
 * Node on the site, so it has to match the sources, stay inside its weight budget (spec §4) and
 * keep the rule every stylesheet of the chain keeps (THEMES §8).
 */
final class DistTest extends PlainTestCase
{
    /** Runtime with the behaviours, the mobile menu and the header: 16 KB gzip (§4). */
    private const int RUNTIME_BUDGET = 16 * 1024;

    /** The consent banner, on every page too: 6 KB gzip (§4). */
    private const int CONSENT_BUDGET = 6 * 1024;

    /** The contacts widgets — phones, hours, quick contact, the bar, networks: 6 KB gzip, only where they stand. */
    private const int CONTACTS_BUDGET = 6 * 1024;

    /** The language switcher: a stylesheet only — its dropdown is the runtime's. */
    private const int LANGUAGE_SWITCHER_BUDGET = 2 * 1024;

    /** The slider, Swiper built in: 45 KB gzip (§4), only where a slider stands. */
    private const int SLIDER_BUDGET = 45 * 1024;

    /** The lightbox, PhotoSwipe built in: 25 KB gzip (§4), only where a picture opens over the page. */
    private const int LIGHTBOX_BUDGET = 25 * 1024;

    /** The video's facade and the notice before consent: 4 KB gzip — the player is the provider's, in its iframe. */
    private const int VIDEO_BUDGET = 4 * 1024;

    /** The map, Leaflet built in: 50 KB gzip without the tiles (§4), only where a map stands. */
    private const int MAP_BUDGET = 50 * 1024;

    /** Each of the small widgets of §14 with a file of its own: a table's shadows, back to top, share, counter, countdown, the notice bar. */
    private const int PAGE_TOOL_BUDGET = 2 * 1024;

    /** Before and after, the table of contents, show more, the zoom and the fields of a form: 3 KB gzip each — a layout of their own as well as a few lines of script. */
    private const int CONTENT_WIDGET_BUDGET = 3 * 1024;

    /** The shell of a form and, from W6.3, its checks in the browser: 8 KB gzip (§17.6), only where a form stands. */
    private const int FORM_BUDGET = 8 * 1024;

    /** Suggestions while typing (W6.5): the combobox and the panel's frame, 4 KB gzip — the HTML inside is the site's. */
    private const int SUGGEST_BUDGET = 4 * 1024;

    private const array LOCALES = ['en', 'ru', 'uk', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    #[Test]
    public function dist_is_built_from_the_current_sources(): void
    {
        $built = json_decode((string) file_get_contents(Widgets::path().'/dist/sources.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($built);

        $this->assertSame(self::sources(), $built['files'] ?? null, 'resources/ changed since dist/ was built: run `npx vite build` in php/packages/widgets and commit dist/.');
    }

    #[Test]
    public function the_runtime_stays_inside_its_budget(): void
    {
        $size = 0;

        foreach (['runtime.js', 'runtime.css'] as $file) {
            $this->assertFileExists(Widgets::path().'/dist/'.$file);
            $size += strlen((string) gzencode((string) file_get_contents(Widgets::path().'/dist/'.$file), 9));
        }

        $this->assertLessThanOrEqual(self::RUNTIME_BUDGET, $size, sprintf('The runtime is %.1f KB gzip.', $size / 1024));
    }

    #[Test]
    public function the_consent_banner_is_its_own_file_inside_its_budget(): void
    {
        $size = self::gzipped('consent.js') + self::gzipped('consent.css');

        $this->assertLessThanOrEqual(self::CONSENT_BUDGET, $size, sprintf('The consent banner is %.1f KB gzip.', $size / 1024));

        // Nothing shared with the runtime: a chunk both import would be one more request on every page.
        $built = array_values(array_filter(
            array_map('basename', (array) glob(Widgets::path().'/dist/*')),
            static fn (string $file): bool => ! str_starts_with($file, 'zz-'),
        ));
        sort($built);

        $this->assertSame(['back-to-top.css', 'back-to-top.js', 'compare.css', 'compare.js', 'consent.css', 'consent.js', 'contacts.css', 'contacts.js', 'countdown.css', 'countdown.js', 'counter.css', 'counter.js', 'field.css', 'field.js', 'files.css', 'files.js', 'form.css', 'form.js', 'language-switcher.css', 'lightbox.css', 'lightbox.js', 'load-more.css', 'load-more.js', 'map.css', 'map.js', 'mask.js', 'notice-bar.css', 'notice-bar.js', 'pagination.css', 'runtime.css', 'runtime.js', 'share.css', 'share.js', 'slider.css', 'slider.js', 'smooth-scroll.css', 'smooth-scroll.js', 'sources.json', 'suggest.css', 'suggest.js', 'table.css', 'table.js', 'toc.css', 'toc.js', 'video.css', 'video.js', 'zoom.css', 'zoom.js'], $built);
    }

    /**
     * The small page tools of §14 — the shadows of a table of prose, back to top, share, the counter
     * the countdown and the notice bar: 2 KB gzip each. §4 names no budget for them; this one says they stay what they are, a few lines.
     */
    #[Test]
    public function the_page_tools_stay_inside_their_budgets(): void
    {
        foreach (['table', 'back-to-top', 'share', 'counter', 'countdown', 'notice-bar'] as $widget) {
            $size = self::gzipped("{$widget}.js") + self::gzipped("{$widget}.css");

            $this->assertLessThanOrEqual(self::PAGE_TOOL_BUDGET, $size, sprintf('%s is %.1f KB gzip.', $widget, $size / 1024));
        }
    }

    #[Test]
    public function the_content_widgets_stay_inside_their_budgets(): void
    {
        foreach (['compare', 'toc', 'load-more', 'zoom', 'field'] as $widget) {
            $size = self::gzipped("{$widget}.js") + self::gzipped("{$widget}.css");

            $this->assertLessThanOrEqual(self::CONTENT_WIDGET_BUDGET, $size, sprintf('%s is %.1f KB gzip.', $widget, $size / 1024));
        }
    }

    #[Test]
    public function the_form_stays_inside_its_budget(): void
    {
        $size = self::gzipped('form.js') + self::gzipped('form.css');

        $this->assertLessThanOrEqual(self::FORM_BUDGET, $size, sprintf('The form is %.1f KB gzip.', $size / 1024));

        // The mask and the files are pieces of their own, claimed by the fields that need them
        // (§17.6): a form with neither never loads them.
        $this->assertLessThanOrEqual(self::CONTENT_WIDGET_BUDGET, self::gzipped('mask.js'), 'The phone mask is over its budget.');
        $this->assertLessThanOrEqual(self::CONTENT_WIDGET_BUDGET, self::gzipped('files.js') + self::gzipped('files.css'), 'The files of a field are over their budget.');
        $this->assertStringNotContainsString('mask', (string) file_get_contents(Widgets::path().'/dist/form.js'));
    }

    /**
     * Suggestions while typing (W6.5): their own file, claimed by `<x-webx-suggest>` only — a page
     * with a search box and no form does not load the form, nor a form the combobox.
     */
    #[Test]
    public function the_suggestions_stay_inside_their_budget(): void
    {
        $size = self::gzipped('suggest.js') + self::gzipped('suggest.css');

        $this->assertLessThanOrEqual(self::SUGGEST_BUDGET, $size, sprintf('The suggestions are %.1f KB gzip.', $size / 1024));
        $this->assertStringNotContainsString('webx:suggest', (string) file_get_contents(Widgets::path().'/dist/form.js'));
        $this->assertStringNotContainsString('webx:form', (string) file_get_contents(Widgets::path().'/dist/suggest.js'));
    }

    #[Test]
    public function the_contacts_widgets_stay_inside_their_budget(): void
    {
        $size = self::gzipped('contacts.js') + self::gzipped('contacts.css');

        $this->assertLessThanOrEqual(self::CONTACTS_BUDGET, $size, sprintf('The contacts widgets are %.1f KB gzip.', $size / 1024));
    }

    #[Test]
    public function the_language_switcher_is_a_stylesheet_inside_its_budget(): void
    {
        $this->assertFileDoesNotExist(Widgets::path().'/dist/language-switcher.js');

        $size = self::gzipped('language-switcher.css');

        $this->assertLessThanOrEqual(self::LANGUAGE_SWITCHER_BUDGET, $size, sprintf('The language switcher is %.1f KB gzip.', $size / 1024));
    }

    /** The links of the pages: a stylesheet and no script, inside the language switcher's 2 KB. */
    #[Test]
    public function the_pagination_is_a_stylesheet_inside_its_budget(): void
    {
        $this->assertFileDoesNotExist(Widgets::path().'/dist/pagination.js');

        $size = self::gzipped('pagination.css');

        $this->assertLessThanOrEqual(self::LANGUAGE_SWITCHER_BUDGET, $size, sprintf('The pagination is %.1f KB gzip.', $size / 1024));
    }

    #[Test]
    public function the_slider_stays_inside_its_budget(): void
    {
        $size = self::gzipped('slider.js') + self::gzipped('slider.css');

        $this->assertLessThanOrEqual(self::SLIDER_BUDGET, $size, sprintf('The slider is %.1f KB gzip.', $size / 1024));

        // Swiper's own stylesheet stays out: its rules are not the package's classes (THEMES §8).
        $this->assertStringNotContainsString('.swiper', (string) file_get_contents(Widgets::path().'/dist/slider.css'));
    }

    #[Test]
    public function the_lightbox_stays_inside_its_budget_and_brings_no_rule_but_photoswipes_and_its_own(): void
    {
        $size = self::gzipped('lightbox.js') + self::gzipped('lightbox.css');

        $this->assertLessThanOrEqual(self::LIGHTBOX_BUDGET, $size, sprintf('The lightbox is %.1f KB gzip.', $size / 1024));

        // PhotoSwipe draws a DOM of its own under `.pswp*`, which it cannot rename: its stylesheet
        // comes as it is, and nothing else foreign does. Each rule is PhotoSwipe's or the package's.
        $css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(Widgets::path().'/dist/lightbox.css'));
        preg_match_all('/(?:^|[;{}])\s*([^;{}@]+?)\s*\{/', $css, $rules);
        $this->assertNotEmpty($rules[1]);

        foreach ($rules[1] as $selectors) {
            foreach (array_map('trim', explode(',', $selectors)) as $selector) {
                // A stop of PhotoSwipe's spinner's @keyframes.
                if (preg_match('/^(?:\d+%|from|to)$/', $selector) === 1) {
                    continue;
                }

                $this->assertMatchesRegularExpression('/^(?::where\(:root\)|(?:[a-z]+)?\.pswp|\.webx-lightbox)/', $selector, "lightbox.css: \"{$selector}\" is neither PhotoSwipe's nor the package's.");
            }
        }
    }

    #[Test]
    public function the_video_stays_inside_its_budget(): void
    {
        $size = self::gzipped('video.js') + self::gzipped('video.css');

        $this->assertLessThanOrEqual(self::VIDEO_BUDGET, $size, sprintf('The video is %.1f KB gzip.', $size / 1024));
    }

    #[Test]
    public function the_map_stays_inside_its_budget_and_brings_no_rule_but_leaflets_and_its_own(): void
    {
        $size = self::gzipped('map.js') + self::gzipped('map.css');

        $this->assertLessThanOrEqual(self::MAP_BUDGET, $size, sprintf('The map is %.1f KB gzip.', $size / 1024));

        // Leaflet draws its panes and controls under `.leaflet-*`, which it cannot rename: its
        // stylesheet comes as it is, without the pictures of a pin and a layers control the map
        // never shows. Nothing else foreign comes with it.
        $css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(Widgets::path().'/dist/map.css'));
        $this->assertStringNotContainsString('url(', $css, 'no picture of Leaflet is inlined or asked for');
        preg_match_all('/(?:^|[;{}])\s*([^;{}@]+?)\s*\{/', $css, $rules);
        $this->assertNotEmpty($rules[1]);

        foreach ($rules[1] as $selectors) {
            foreach (array_map('trim', explode(',', $selectors)) as $selector) {
                $this->assertMatchesRegularExpression('/^(?::where\(:root\)|(?:html:not\(\.webx-js\) )?\.webx-map|(?:[a-z]+(?:\.[a-z-]+)?\s*)?\.leaflet-)/', $selector, "map.css: \"{$selector}\" is neither Leaflet's nor the package's.");
            }
        }
    }

    private static function gzipped(string $file): int
    {
        return strlen((string) gzencode((string) file_get_contents(Widgets::path().'/dist/'.$file), 9));
    }

    /**
     * The rule of every stylesheet of the chain — the engine's check (THEMES §8): no literal
     * colour or font, no token outside the vocabulary, no fallback in var(), no width @media.
     * And the widgets' own on top: a variable is a site token or a `--webx-<name>-*` declared
     * here, and a rule is one flat class.
     */
    #[Test]
    public function the_stylesheets_know_only_tokens(): void
    {
        $stylesheets = [];

        foreach (array_keys(self::sources()) as $relative) {
            if (str_ends_with($relative, '.css')) {
                $stylesheets[$relative] = (string) file_get_contents(Widgets::path().'/'.$relative);
            }
        }

        foreach ($stylesheets as $relative => $css) {
            $findings = array_map(static fn (CssFinding $finding): string => "{$relative}:{$finding->line} {$finding->message()}", CssCheck::check($css, Vocabulary::base()));
            $this->assertSame([], $findings);

            $stylesheets[$relative] = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        }

        preg_match_all('/(--webx-[a-z0-9-]+)\s*:/', implode("\n", $stylesheets), $declared);

        foreach ($stylesheets as $relative => $css) {
            preg_match_all('/var\(\s*--([a-z0-9-]+)\s*([,)])/i', $css, $references, PREG_SET_ORDER);

            foreach ($references as [, $variable, $after]) {
                if (str_starts_with($variable, 'webx-')) {
                    $this->assertContains("--{$variable}", $declared[1], "{$relative}: --{$variable} is declared nowhere.");
                } else {
                    $this->assertStringStartsWith('site-', $variable, "{$relative}: --{$variable} is neither a site token nor a widget's own.");
                }

                // Stricter than the engine, which lets a block's own variable keep a default: a
                // widget declares every one of its own, so a fallback here has nothing to fall to.
                $this->assertSame(')', $after, "{$relative}: var(--{$variable}, …) has a fallback.");
            }

            preg_match_all('/(?:^|[;{}])\s*([^;{}@]+?)\s*\{/', $css, $rules);

            // One flat class (a state or a modifier on it at most): enough to win over the prose of the theme, which is
            // all :where(), and no more than a theme matches by writing the same class later.
            foreach ($rules[1] as $selectors) {
                foreach (array_map('trim', explode(',', $selectors)) as $selector) {
                    $this->assertMatchesRegularExpression(
                        '/^(?::where\(:root\)|(?:html:not\(\.webx-js\) )?\.webx-[a-z0-9_-]+(?:\.(?:is-[a-z-]+|webx-[a-z0-9_-]+--[a-z0-9-]+))?(?:\[[^\]]+\]|::?[a-z-]+)*)$/',
                        $selector,
                        "{$relative}: \"{$selector}\" — a widget's rule is one .webx-* class, with a state or a modifier at most.",
                    );
                }
            }
        }
    }

    #[Test]
    public function every_locale_has_every_word(): void
    {
        // The visitor's words and the panel's ("Cookie" tab of the settings, §9.5).
        // And the site audit's (§15.2): its checks, their summaries and their fixes.
        foreach (['widgets', 'panel', 'checks', 'audit', 'fixes'] as $group) {
            $english = self::keys(require Widgets::path()."/lang/en/{$group}.php");

            foreach (self::LOCALES as $locale) {
                $file = Widgets::path()."/lang/{$locale}/{$group}.php";
                $this->assertFileExists($file);
                $this->assertSame($english, self::keys(require $file), "lang/{$locale}/{$group}.php differs from lang/en.");
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $words
     * @return list<string>
     */
    private static function keys(array $words, string $prefix = ''): array
    {
        $keys = [];

        foreach ($words as $key => $value) {
            array_push($keys, ...(is_array($value) ? self::keys($value, "{$prefix}{$key}.") : ["{$prefix}{$key}"]));
        }

        sort($keys);

        return $keys;
    }

    /**
     * Every source file but the tests, by sha256, line endings normalised — the walk
     * vite.config.js does.
     *
     * @return array<string, string>
     */
    private static function sources(): array
    {
        $files = [];
        $root = Widgets::path();

        foreach (['resources/js', 'resources/css'] as $directory) {
            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (str_contains($file->getFilename(), '.test.')) {
                    continue;
                }

                $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
                $files[$relative] = hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($file->getPathname())));
            }
        }

        ksort($files, SORT_STRING);

        return $files;
    }
}
