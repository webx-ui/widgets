<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Admin\AdminServiceProvider;
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
use WebxUi\Widgets\Widgets as WidgetsService;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * Spec §11 and §9.4: the map is the place — the address and "Open in maps" — in a frame of its
 * height, and before consent to `media` the notice with "Load" and "Always load maps"; the tiles
 * are the script's, after consent. The attribution is printed always; `from="settings"` reads the
 * primary address of the Contacts tab.
 */
final class MapTest extends TestCase
{
    use RefreshDatabase;

    private const string LONDON = '<x-webx-map :lat="51.5074" :lng="-0.1278" marker="1 Example Street, London" />';

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

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/map', static fn (): string => Blade::render('<x-layout>'.self::LONDON.'</x-layout>'));
    }

    #[Test]
    public function before_consent_the_server_prints_the_place_and_the_notice_and_asks_for_no_tile(): void
    {
        $html = Blade::render(self::LONDON);

        $this->assertStringContainsString('style="--webx-map-height: 24rem" class="webx-map is-blocked" role="region" aria-label="Map: 1 Example Street, London"', $html);
        $this->assertStringContainsString('data-webx-consent="media"', $html, 'the banner offers media wherever a map waits for it');
        $this->assertStringContainsString('data-webx-map="{&quot;lat&quot;:51.5074,&quot;lng&quot;:-0.1278,&quot;zoom&quot;:15,&quot;tiles&quot;:&quot;https://tile.openstreetmap.org/{z}/{x}/{y}.png&quot;,&quot;maxZoom&quot;:19,&quot;marker&quot;:&quot;1 Example Street, London&quot;', $html);
        // Without JavaScript: the address and a link to the map.
        $this->assertStringContainsString('<p class="webx-map__address">1 Example Street, London</p>', $html);
        $this->assertStringContainsString('<a class="webx-map__open" href="https://www.openstreetmap.org/?mlat=51.5074&amp;mlon=-0.1278#map=15/51.5074/-0.1278" target="_blank" rel="noopener">Open in maps</a>', $html);
        $this->assertStringContainsString('<p class="webx-map__notice">The map loads from OpenStreetMap, which receives your IP address.</p>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-map__button" data-webx-map-load>Load</button>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-map__button webx-map__button--always" data-webx-map-always>Always load maps</button>', $html);
        // The licence of the data: printed always, before the map too.
        $this->assertStringContainsString('<p class="webx-map__attribution">© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors</p>', $html);
        $this->assertStringNotContainsString('<img', $html, 'not one tile from the server');
        $this->assertContains('map', Widgets::claimed());
    }

    #[Test]
    public function with_consent_to_media_it_is_the_place_alone_for_the_script_to_cover(): void
    {
        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => 1, 'd' => '2026-10-09', 'c' => ['media']], JSON_THROW_ON_ERROR));

        $html = (string) $this->get('/map')->assertOk()->getContent();

        $this->assertStringContainsString('class="webx-map" role="region"', $html);
        $this->assertStringContainsString('webx-map__address', $html);
        $this->assertStringContainsString('webx-map__attribution', $html);
        $this->assertStringNotContainsString('webx-map__notice', $html);
        $this->assertStringNotContainsString('data-webx-map-load', $html);
    }

    #[Test]
    public function an_answer_without_media_and_a_banner_switched_off_decide_it_as_well(): void
    {
        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => 1, 'd' => '2026-10-09', 'c' => ['statistics']], JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('webx-map__notice', (string) $this->get('/map')->getContent());

        // With module-settings the Cookie tab is where the banner is switched off.
        app(Settings::class)->save(['consent.enabled' => false]);
        $this->assertStringNotContainsString('webx-map__notice', (string) $this->get('/map')->getContent());
    }

    #[Test]
    public function the_page_of_a_map_loads_its_files_and_the_banner_offers_media(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();
        $layer = app(BottomLayers::class)->get(WidgetsService::NAME);
        $this->assertNotNull($layer);
        $base = 'http://localhost/themes/webx-ui/widgets/'.app(ThemeAssets::class)->current($layer);

        $html = (string) $this->get('/map')->assertOk()->getContent();

        $this->assertStringContainsString("<link rel=\"stylesheet\" href=\"{$base}/map.css\">", $html);
        $this->assertStringContainsString("<script type=\"module\" src=\"{$base}/map.js\"></script>", $html);
        $this->assertMatchesRegularExpression('/data-webx-consent-row="media"(?![^>]*hidden)/', $html);
    }

    #[Test]
    public function from_settings_it_is_the_primary_address_of_the_contacts_tab(): void
    {
        app(Settings::class)->save([
            'contacts.addresses' => [
                ['address' => ['en' => 'Warehouse, Leeds'], 'latitude' => 53.8, 'longitude' => -1.55, 'primary' => false],
                ['address' => ['en' => '1 Example Street, London', 'de' => '1 Example Street, London (Büro)'], 'latitude' => 51.5074, 'longitude' => -0.1278, 'primary' => true],
            ],
        ]);

        $html = Blade::render('<x-webx-map from="settings" :zoom="17" height="320" />');

        $this->assertStringContainsString('--webx-map-height: 320px', $html);
        $this->assertStringContainsString('<p class="webx-map__address">1 Example Street, London</p>', $html);
        $this->assertStringContainsString('&quot;lat&quot;:51.5074,&quot;lng&quot;:-0.1278,&quot;zoom&quot;:17', $html);
        $this->assertStringContainsString('&quot;marker&quot;:&quot;1 Example Street, London&quot;', $html, 'the pin is labelled by the address');
        $this->assertStringContainsString('href="https://www.openstreetmap.org/?mlat=51.5074&amp;mlon=-0.1278#map=17/51.5074/-0.1278"', $html);

        // On the page's language, and the link typed into the tab wins over the provider's.
        app()->setLocale('de');
        app(Settings::class)->save(['contacts.addresses' => [
            ['address' => ['en' => 'London', 'de' => 'London (Büro)'], 'latitude' => 51.5074, 'longitude' => -0.1278, 'map' => 'https://maps.example.com/office', 'primary' => true],
        ]]);
        $this->app->forgetScopedInstances();

        $german = Blade::render('<x-webx-map from="settings" :marker="false" />');
        $this->assertStringContainsString('<p class="webx-map__address">London (Büro)</p>', $german);
        $this->assertStringContainsString('href="https://maps.example.com/office"', $german);
        $this->assertStringContainsString('&quot;marker&quot;:null', $german, 'no pin when told');
    }

    #[Test]
    public function an_address_without_coordinates_is_the_place_alone_and_no_address_is_nothing(): void
    {
        $this->assertSame('', trim(Blade::render('<x-webx-map from="settings" />')), 'an empty tab: nothing');

        app(Settings::class)->save(['contacts.addresses' => [
            ['address' => ['en' => '1 Example Street, London'], 'map' => 'https://maps.example.com/office', 'primary' => true],
        ]]);
        $this->app->forgetScopedInstances();

        $html = Blade::render('<x-webx-map from="settings" />');

        $this->assertStringContainsString('class="webx-map webx-map--place"', $html);
        $this->assertStringContainsString('<p class="webx-map__address">1 Example Street, London</p>', $html);
        $this->assertStringContainsString('href="https://maps.example.com/office"', $html);
        $this->assertStringNotContainsString('data-webx-map', $html, 'nothing for the script, nothing to consent to');
        $this->assertStringNotContainsString('data-webx-consent', $html);
    }

    #[Test]
    public function another_provider_is_config_its_key_in_the_address_and_its_attribution_under_the_map(): void
    {
        config()->set('webx-widgets.map.provider', 'maptiler');
        config()->set('webx-widgets.map.providers.maptiler.key', 'k3y/+');
        config()->set('webx-widgets.map.open', 'https://www.google.com/maps/search/?api=1&query={lat},{lng}');

        $html = Blade::render('<x-webx-map :lat="48.8584" :lng="2.2945" :zoom="21" />');

        $this->assertStringContainsString('https://api.maptiler.com/maps/streets-v2/256/{z}/{x}/{y}.png?key=k3y%2F%2B', html_entity_decode($html));
        $this->assertStringContainsString('&quot;zoom&quot;:20', $html, 'no deeper than the provider draws');
        $this->assertStringContainsString('The map loads from MapTiler', $html);
        $this->assertStringContainsString('© MapTiler', $html);
        $this->assertStringContainsString('href="https://www.google.com/maps/search/?api=1&amp;query=48.8584,2.2945"', $html);
        $this->assertStringContainsString('aria-label="Map: Map"', $html, 'no address: the map is named a map');
    }

    #[Test]
    public function a_provider_without_its_key_or_unknown_is_a_mistake_of_the_config(): void
    {
        config()->set('webx-widgets.map.provider', 'maptiler');
        $this->assertRefused(self::LONDON, 'asks for a key');

        config()->set('webx-widgets.map.provider', 'nowhere');
        $this->assertRefused(self::LONDON, 'is not described');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function typos(): iterable
    {
        yield 'neither coordinates nor settings' => ['<x-webx-map />', 'either `lat` and `lng`'];
        yield 'a latitude only' => ['<x-webx-map :lat="51.5" />', '`lng` is a number'];
        yield 'off the earth' => ['<x-webx-map :lat="91" :lng="0" />', '`lat` is a number from -90 to 90'];
        yield 'not a number' => ['<x-webx-map lat="north" lng="0" />', '`lat` is a number'];
        yield 'a source of no kind' => ['<x-webx-map from="contacts" />', '`from` is "settings"'];
        yield 'a zoom in fractions' => ['<x-webx-map :lat="0" :lng="0" zoom="2.5" />', 'a zoom is a whole number'];
        yield 'a zoom too deep' => ['<x-webx-map :lat="0" :lng="0" :zoom="30" />', 'a zoom is a whole number'];
        yield 'a height of nothing' => ['<x-webx-map :lat="0" :lng="0" height="tall" />', 'a height is a CSS length'];
    }

    #[Test]
    #[DataProvider('typos')]
    public function a_typo_in_the_template_says_what_it_wants(string $template, string $says): void
    {
        $this->assertRefused($template, $says);
    }

    #[Test]
    public function the_words_are_the_pages_language(): void
    {
        app()->setLocale('de');

        $html = Blade::render(self::LONDON);

        $this->assertStringContainsString('aria-label="Karte: 1 Example Street, London"', $html);
        $this->assertStringContainsString('Die Karte wird von OpenStreetMap geladen', $html);
        $this->assertStringContainsString('>Karten immer laden</button>', $html);
        $this->assertStringContainsString('>In Karten öffnen</a>', $html);
    }

    private function assertRefused(string $template, string $says): void
    {
        try {
            Blade::render($template);
        } catch (Throwable $error) {
            // Blade wraps what a component throws.
            $cause = $error instanceof ViewException && $error->getPrevious() !== null ? $error->getPrevious() : $error;
            $this->assertInstanceOf(InvalidArgumentException::class, $cause);
            $this->assertStringContainsString($says, $cause->getMessage());

            return;
        }

        $this->fail("{$template} rendered: it should have said {$says}.");
    }
}
