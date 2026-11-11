<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Map\GoogleMaps;
use WebxUi\Widgets\Widgets as WidgetsService;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * Spec §11.1: Google Maps as the provider of the same `<x-webx-map>` — chosen on the "Map" tab or
 * in the config, its own script instead of Leaflet, the same notice before consent, and without a
 * key the place with "Open in Google Maps" rather than Google's grey window.
 */
final class GoogleMapTest extends TestCase
{
    use MigratesOnce;

    private const string MAP = '<x-webx-map :lat="12.34" :lng="-34.56" marker="1 Example Street, Sample City" />';

    private const string KEY = 'test-browser-key';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            AdminServiceProvider::class,
            McpServiceProvider::class,
            AuthServiceProvider::class,
            SettingsServiceProvider::class,
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

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'de']]);
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/map', static fn (): string => Blade::render('<x-layout>'.self::MAP.'</x-layout>'));
    }

    #[Test]
    public function a_site_that_chose_nothing_keeps_leaflet_over_openstreetmap(): void
    {
        $this->assertNull(GoogleMaps::configured());

        $html = Blade::render(self::MAP);

        $this->assertStringContainsString('&quot;tiles&quot;:&quot;https://tile.openstreetmap.org/{z}/{x}/{y}.png&quot;', $html);
        $this->assertStringNotContainsString('google', $html);
        $this->assertContains('map', Widgets::claimed());
        $this->assertNotContains('map-google', Widgets::claimed());
    }

    #[Test]
    public function google_chosen_on_the_map_tab_is_the_same_frame_and_notice_for_its_own_script(): void
    {
        app(Settings::class)->save(['map.provider' => 'google', 'map.google-key' => self::KEY]);

        $html = Blade::render(self::MAP);

        $this->assertStringContainsString('class="webx-map is-blocked" role="region" aria-label="Map: 1 Example Street, Sample City"', $html);
        $this->assertStringContainsString('data-webx-consent="media"', $html);
        $this->assertStringContainsString('data-webx-map="{&quot;provider&quot;:&quot;google&quot;,&quot;key&quot;:&quot;test-browser-key&quot;,&quot;mapId&quot;:null,&quot;language&quot;:&quot;en&quot;,&quot;lat&quot;:12.34,&quot;lng&quot;:-34.56,&quot;zoom&quot;:15,&quot;maxZoom&quot;:21,&quot;marker&quot;:&quot;1 Example Street, Sample City&quot;', $html);
        $this->assertStringContainsString('&quot;failed&quot;:&quot;The map could not be loaded.&quot;', $html);
        $this->assertStringContainsString('<p class="webx-map__notice">The map loads from Google Maps, which receives your IP address.</p>', $html);
        $this->assertStringContainsString('data-webx-map-load', $html);
        $this->assertStringContainsString('data-webx-map-always', $html);
        $this->assertStringContainsString('href="https://www.google.com/maps/search/?api=1&amp;query=12.34,-34.56"', $html, 'Open in Google Maps');
        $this->assertStringNotContainsString('tiles', $html);
        $this->assertStringNotContainsString('webx-map__attribution', $html, 'Google draws its own on the map');
        $this->assertStringNotContainsString('maps.googleapis.com', $html, 'the server asks Google for nothing');
        $this->assertContains('map-google', Widgets::claimed());
        $this->assertNotContains('map', Widgets::claimed(), 'no Leaflet on a page of Google');
    }

    #[Test]
    public function the_page_loads_googles_files_and_none_of_leaflets(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();
        app(Settings::class)->save(['map.provider' => 'google', 'map.google-key' => self::KEY]);
        $layer = app(BottomLayers::class)->get(WidgetsService::NAME);
        $this->assertNotNull($layer);
        $base = 'http://localhost/themes/webx-ui/widgets/'.app(ThemeAssets::class)->current($layer);

        $html = (string) $this->get('/map')->assertOk()->getContent();

        $this->assertStringContainsString("<link rel=\"stylesheet\" href=\"{$base}/map-google.css\">", $html);
        $this->assertStringContainsString("<script type=\"module\" src=\"{$base}/map-google.js\"></script>", $html);
        $this->assertStringNotContainsString("{$base}/map.js", $html);
        $this->assertStringNotContainsString('maps.googleapis.com', $html, 'nothing of Google before consent, not even a preconnect');
        $this->assertMatchesRegularExpression('/data-webx-consent-row="media"(?![^>]*hidden)/', $html);
    }

    #[Test]
    public function with_consent_or_maps_without_asking_it_waits_for_nothing(): void
    {
        app(Settings::class)->save(['map.provider' => 'google', 'map.google-key' => self::KEY]);
        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => 1, 'd' => '2026-11-11', 'c' => ['media']], JSON_THROW_ON_ERROR));

        $agreed = (string) $this->get('/map')->assertOk()->getContent();
        $this->assertStringContainsString('class="webx-map" role="region"', $agreed);
        $this->assertStringNotContainsString('webx-map__notice', $agreed);

        app(Settings::class)->save(['consent.maps-at-once' => true]);
        $this->app->forgetScopedInstances();

        $html = Blade::render(self::MAP);
        $this->assertStringContainsString('&quot;consent&quot;:false}', $html);
        $this->assertStringNotContainsString('data-webx-consent="media"', $html);
    }

    #[Test]
    public function without_a_key_it_is_the_place_and_a_link_to_google_maps(): void
    {
        app(Settings::class)->save(['map.provider' => 'google']);

        $html = Blade::render(self::MAP);

        $this->assertStringContainsString('class="webx-map webx-map--place"', $html);
        $this->assertStringContainsString('<p class="webx-map__address">1 Example Street, Sample City</p>', $html);
        $this->assertStringContainsString('href="https://www.google.com/maps/search/?api=1&amp;query=12.34,-34.56"', $html);
        $this->assertStringNotContainsString('data-webx-map', $html, 'nothing for a script: no grey window of Google');
        $this->assertStringNotContainsString('data-webx-consent', $html, 'nothing to consent to');
        $this->assertContains('map-google', Widgets::claimed(), 'its stylesheet, without Leaflet');
        $this->assertNotContains('map', Widgets::claimed());

        // No address either: the coordinates stand for it.
        $bare = Blade::render('<x-webx-map :lat="12.34" :lng="-34.56" />');
        $this->assertStringContainsString('<p class="webx-map__address">12.34, -34.56</p>', $bare);
    }

    #[Test]
    public function an_address_without_coordinates_opens_googles_search_for_it(): void
    {
        app(Settings::class)->save([
            'map.provider' => 'google',
            'map.google-key' => self::KEY,
            'contacts.addresses' => [['address' => ['en' => '1 Example Street, Sample City'], 'primary' => true]],
        ]);

        $html = Blade::render('<x-webx-map from="settings" />');

        $this->assertStringContainsString('class="webx-map webx-map--place"', $html);
        $this->assertStringContainsString('href="https://www.google.com/maps/search/?api=1&amp;query=1%20Example%20Street%2C%20Sample%20City"', $html);
    }

    #[Test]
    public function the_config_chooses_google_too_and_the_tab_wins_over_it(): void
    {
        config()->set('webx-widgets.map.provider', 'google');
        config()->set('webx-widgets.map.google.key', 'config-key');
        config()->set('webx-widgets.map.google.map_id', 'abc123');

        $google = GoogleMaps::configured();
        $this->assertNotNull($google);
        $this->assertSame('config-key', $google->key);
        $this->assertSame('abc123', $google->mapId);
        $this->assertStringContainsString('&quot;mapId&quot;:&quot;abc123&quot;', Blade::render(self::MAP));

        // The key of the tab wins over the config's.
        app(Settings::class)->save(['map.google-key' => 'tab-key']);
        $this->assertSame('tab-key', GoogleMaps::configured()?->key);

        // "OpenStreetMap" on the tab is the config's tiles, whatever the config says of Google.
        app(Settings::class)->save(['map.provider' => 'tiles']);
        $this->assertNull(GoogleMaps::configured());
        $this->assertStringContainsString('&quot;tiles&quot;:', Blade::render(self::MAP));
    }

    #[Test]
    public function the_zoom_is_no_deeper_than_googles_and_the_words_are_the_pages(): void
    {
        app(Settings::class)->save(['map.provider' => 'google', 'map.google-key' => self::KEY]);
        app()->setLocale('de');

        $html = Blade::render('<x-webx-map :lat="1" :lng="2" :zoom="22" />');

        $this->assertStringContainsString('&quot;zoom&quot;:21', $html);
        $this->assertStringContainsString('&quot;language&quot;:&quot;de&quot;', $html);
        $this->assertStringContainsString((string) __('webx-widgets::widgets.map.failed', [], 'de'), html_entity_decode($html));
    }

    #[Test]
    public function the_map_tab_is_on_the_settings_screen(): void
    {
        $json = (string) json_encode(app(ScreenRegistry::class)->tree('settings.index'));

        foreach (['"map.provider"', '"map.google-key"', '"map.google-map-id"', '"map-google-no-key"'] as $node) {
            $this->assertStringContainsString($node, $json);
        }
    }
}
