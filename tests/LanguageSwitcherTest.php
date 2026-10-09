<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Tests\Fixtures\Doc;
use WebxUi\Widgets\Widgets;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * Spec §13: the switcher leads to the same page in the other language — the address the
 * registry holds, as `hreflang` does — and to that language's home page, marked, where there
 * is no translation; on a site with one language it is not there at all.
 */
final class LanguageSwitcherTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'de'],
            ['code' => 'pl'],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    #[Test]
    public function it_leads_to_the_same_page_in_the_other_languages(): void
    {
        $doc = $this->doc(['en' => 'about-us', 'de' => 'ueber-uns']);
        $this->serve('/about-us', 'en', $doc);

        $html = Blade::render('<x-webx-language-switcher layout="list" />');

        $this->assertStringContainsString('<nav class="webx-language-switcher webx-language-switcher--list" aria-label="Language">', $html);
        $this->assertStringContainsString('<a class="webx-language-switcher__link is-current" href="https://example.test/about-us" hreflang="en" lang="en" aria-current="page">', $html);
        $this->assertStringContainsString('<a class="webx-language-switcher__link" href="https://example.test/de/ueber-uns" hreflang="de" lang="de">', $html);
        // Each language is named in itself.
        $this->assertStringContainsString('<span class="webx-language-switcher__name">English</span>', $html);
        $this->assertStringContainsString('<span class="webx-language-switcher__name">Deutsch</span>', $html);
        $this->assertStringContainsString('<span class="webx-language-switcher__name">Polski</span>', $html);
        $this->assertSame(1, substr_count($html, 'aria-current'));
        $this->assertContains('language-switcher', app(Widgets::class)->claimed());
    }

    #[Test]
    public function without_a_translation_it_leads_home_and_says_so(): void
    {
        $doc = $this->doc(['en' => 'about-us', 'de' => 'ueber-uns']);
        $this->serve('/de/ueber-uns', 'de', $doc);

        $html = Blade::render('<x-webx-language-switcher layout="list" />');

        $this->assertStringContainsString('<a class="webx-language-switcher__link" href="https://example.test/about-us" hreflang="en" lang="en">', $html);
        $this->assertStringContainsString('<a class="webx-language-switcher__link is-current" href="https://example.test/de/ueber-uns" hreflang="de" lang="de" aria-current="page">', $html);
        // The title is in the language the link is in — the one the reader of it chose.
        $this->assertStringContainsString('<a class="webx-language-switcher__link is-fallback" href="https://example.test/pl" hreflang="pl" lang="pl" title="Jeszcze nie przetłumaczono: strona główna w tym języku">', $html);
        $this->assertSame(1, substr_count($html, 'is-fallback'));
        // The navigation is labelled in the page's language.
        $this->assertStringContainsString('aria-label="Sprache"', $html);
    }

    #[Test]
    public function a_translation_hidden_in_a_language_is_no_translation(): void
    {
        $doc = $this->doc(['en' => 'about-us', 'de' => 'ueber-uns', 'pl' => 'o-nas']);
        $doc->hiddenIn = ['pl'];
        $this->serve('/about-us', 'en', $doc);

        $html = Blade::render('<x-webx-language-switcher layout="list" fallback-label="Home" />');

        $this->assertStringContainsString('href="https://example.test/de/ueber-uns"', $html);
        $this->assertStringContainsString('<a class="webx-language-switcher__link is-fallback" href="https://example.test/pl" hreflang="pl" lang="pl" title="Home">', $html);
        $this->assertStringNotContainsString('o-nas', $html);
    }

    #[Test]
    public function an_address_outside_the_registry_is_the_same_path_under_each_prefix(): void
    {
        $this->serve('/de/blog/feed', 'de');

        $html = Blade::render('<x-webx-language-switcher layout="list" />');

        $this->assertStringContainsString('href="https://example.test/blog/feed" hreflang="en"', $html);
        $this->assertStringContainsString('href="https://example.test/de/blog/feed" hreflang="de"', $html);
        $this->assertStringContainsString('href="https://example.test/pl/blog/feed" hreflang="pl"', $html);
        $this->assertStringNotContainsString('is-fallback', $html);
    }

    #[Test]
    public function a_page_the_registry_did_not_find_has_only_home_pages_to_offer(): void
    {
        $this->serve('/missing', 'en', fallback: true);

        $html = Blade::render('<x-webx-language-switcher layout="list" />');

        $this->assertStringContainsString('href="https://example.test/de" hreflang="de"', $html);
        $this->assertStringContainsString('href="https://example.test/pl" hreflang="pl"', $html);
        $this->assertSame(2, substr_count($html, 'is-fallback'));
    }

    #[Test]
    public function the_dropdown_shows_the_current_language_on_its_button(): void
    {
        $this->serve('/de/blog', 'de');

        $html = Blade::render('<x-webx-language-switcher codes />');

        $this->assertStringContainsString('class="webx-language-switcher webx-language-switcher--dropdown"', $html);
        $this->assertStringContainsString('<details data-webx-dropdown="click" class="webx-dropdown webx-dropdown--bottom-end webx-language-switcher__dropdown">', $html);
        $this->assertMatchesRegularExpression('~<summary class="webx-dropdown__trigger webx-language-switcher__current"><svg class="webx-icon"[^>]*>.*?</svg>\s*<span class="webx-language-switcher__name" lang="de">DE</span></summary>~s', $html);
        $this->assertStringContainsString('<span class="webx-language-switcher__code">PL</span>', $html);
        $this->assertSame(['dropdown', 'language-switcher'], array_values(array_intersect(['dropdown', 'language-switcher'], app(Widgets::class)->claimed())));
    }

    #[Test]
    public function a_site_with_one_language_has_no_switcher(): void
    {
        config()->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $this->serve('/about-us', 'en');

        $this->assertSame('', trim(Blade::render('<x-webx-language-switcher /><x-webx-language-switcher layout="list" />')));
        $this->assertNotContains('language-switcher', app(Widgets::class)->claimed());
    }

    #[Test]
    public function with_one_address_for_every_language_there_is_nothing_to_link(): void
    {
        config()->set('webx-localization.strategy', 'header');
        $this->serve('/about-us', 'en');

        $this->assertSame('', trim(Blade::render('<x-webx-language-switcher />')));
    }

    #[Test]
    public function a_typo_in_the_layout_is_an_error(): void
    {
        $this->serve('/', 'en');

        $this->expectExceptionMessage('<x-webx-language-switcher layout="row">: dropdown or list.');
        Blade::render('<x-webx-language-switcher layout="row" />');
    }

    /**
     * @param  array<string, string>  $paths  language → its canonical row
     */
    private function doc(array $paths): Doc
    {
        $doc = new Doc;
        $doc->setAttribute('id', 7);

        foreach ($paths as $locale => $path) {
            Route::query()->create(['locale' => $locale, 'path' => $path, 'kind' => Route::CANONICAL, 'entity_type' => $doc->getMorphClass(), 'entity_id' => 7]);
        }

        // An alias is the page's old address, not its translation.
        Route::query()->create(['locale' => 'pl', 'path' => 'stara', 'kind' => Route::ALIAS, 'entity_type' => $doc->getMorphClass(), 'entity_id' => 7]);

        return $doc;
    }

    /** The request being answered, as the router and the registry's resolver leave it. */
    private function serve(string $path, string $locale, ?Doc $doc = null, bool $fallback = false): void
    {
        $request = Request::create('https://example.test'.$path);
        $route = new LaravelRoute('GET', $doc !== null || $fallback ? '{fallbackPlaceholder}' : ltrim($path, '/'), static fn (): string => '');
        $route->isFallback = $doc !== null || $fallback;
        $request->setRouteResolver(static fn (): LaravelRoute => $route);

        if ($doc !== null) {
            $row = Route::query()->forEntity($doc)->canonical()->where('locale', $locale)->firstOrFail();
            $request->attributes->set(Resolution::ATTRIBUTE, new Resolution($row, '', null, $doc));
        }

        $this->app->instance('request', $request);
        $this->app->setLocale($locale);
    }
}
