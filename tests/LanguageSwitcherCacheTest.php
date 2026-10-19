<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cache\CacheGroups;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\RouteCache;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Tests\Fixtures\Doc;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The switcher reads the page's addresses where `url()` and `hreflang` do — `RouteCache` — so a
 * warm cache asks the registry nothing.
 *
 * Its own class, without `RefreshDatabase`: inside that transaction `RouteCache` keeps to the
 * request's memory and never writes the site's cache, which is what this is about.
 */
final class LanguageSwitcherCacheTest extends TestCase
{
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
        $app['config']->set('cache.default', 'array');
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
    public function a_warm_cache_asks_the_registry_nothing_and_links_the_same(): void
    {
        $doc = new Doc;
        $doc->setAttribute('id', 7);

        foreach (['en' => 'about-us', 'de' => 'ueber-uns'] as $locale => $path) {
            Route::query()->create(['locale' => $locale, 'path' => $path, 'kind' => Route::CANONICAL, 'entity_type' => $doc->getMorphClass(), 'entity_id' => 7]);
        }

        Route::query()->create(['locale' => 'pl', 'path' => 'stara', 'kind' => Route::ALIAS, 'entity_type' => $doc->getMorphClass(), 'entity_id' => 7]);

        $request = Request::create('https://example.test/about-us');
        $route = new LaravelRoute('GET', '{fallbackPlaceholder}', static fn (): string => '');
        $route->isFallback = true;
        $request->setRouteResolver(static fn (): LaravelRoute => $route);
        $row = Route::query()->forEntity($doc)->canonical()->where('locale', 'en')->firstOrFail();
        $request->attributes->set(Resolution::ATTRIBUTE, new Resolution($row, '', null, $doc, 'en'));
        $this->app->instance('request', $request);
        $this->app->setLocale('en');

        [$cold, $queries] = $this->rendered();

        $this->assertSame(1, $queries, 'cold: one query for the rows');
        $this->assertStringContainsString('href="https://example.test/de/ueber-uns" hreflang="de"', $cold);
        // The alias is an old address, not a translation: Polish leads home.
        $this->assertStringContainsString('<a class="webx-language-switcher__link is-fallback" href="https://example.test/pl"', $cold);

        // The next request, as a worker starts it.
        $this->app->forgetInstance(RouteCache::class);
        $this->app->make(CacheGroups::class)->forgetMemo();

        [$warm, $queries] = $this->rendered();

        $this->assertSame(0, $queries);
        $this->assertSame($cold, $warm);
    }

    /**
     * The switcher, and the queries it asked of `routes`.
     *
     * @return array{string, int}
     */
    private function rendered(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $html = Blade::render('<x-webx-language-switcher layout="list" />');
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return [$html, count(array_filter($log, static fn (array $query): bool => str_contains($query['query'], 'from "routes"')))];
    }
}
