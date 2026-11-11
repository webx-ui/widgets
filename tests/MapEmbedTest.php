<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Screens\Embeds;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Map\GoogleMapsEmbed;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * Spec §11.2: Google Maps' "Embed a map" in the frame of `<x-webx-map embed>` — no key. Only
 * Google's frame is taken, pasted as code or as its address, and kept as the address; before
 * consent to `media` it is the place, the notice and its buttons, and no <iframe> at all.
 */
final class MapEmbedTest extends TestCase
{
    use MigratesOnce;

    /** A public square, as Google's "Embed a map" writes it. */
    private const string SRC = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2483.0!2d-0.128!3d51.508!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sTrafalgar%20Square!5e0!3m2!1sen!2suk!4v1700000000000!5m2!1sen!2suk';

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
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
    }

    /** Google's code as "Copy HTML" gives it. */
    private static function code(string $src = self::SRC): string
    {
        return '<iframe src="'.htmlspecialchars($src).'" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
    }

    #[Test]
    public function googles_code_and_its_address_come_to_the_same_address(): void
    {
        $this->assertSame(self::SRC, GoogleMapsEmbed::address(self::code()), 'the whole <iframe>, its &amp; undone');
        $this->assertSame(self::SRC, GoogleMapsEmbed::address('  '.self::SRC."\n"), 'the address alone');
        $this->assertSame(self::SRC, GoogleMapsEmbed::address(str_replace('&', '&amp;', self::SRC)));
        $this->assertSame(self::SRC, GoogleMapsEmbed::address("<IFRAME src='".self::SRC."'></IFRAME>"));

        // The older shape, still what the code of an address search is.
        $old = 'https://maps.google.com/maps?q=Trafalgar%20Square%2C%20London&t=&z=15&ie=UTF8&iwloc=&output=embed';
        $this->assertSame($old, GoogleMapsEmbed::address(self::code($old)));
        $this->assertSame('https://www.google.com/maps?q=Big+Ben&output=embed', GoogleMapsEmbed::address('https://www.google.com/maps?q=Big+Ben&output=embed'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notGoogles(): iterable
    {
        yield 'another domain' => ['https://maps.example.com/maps/embed?pb=!1m18'];
        yield 'a domain that ends like Google' => ['https://www.google.com.example.com/maps/embed?pb=!1m18'];
        yield 'Google on another path' => ['https://www.google.com/search?q=maps'];
        yield 'the keyed Embed API' => ['https://www.google.com/maps/embed/v1/place?key=AIza&q=Big+Ben'];
        yield 'an embed with nothing to show' => ['https://www.google.com/maps/embed'];
        yield 'an old map that is not an embed' => ['https://maps.google.com/maps?q=Big+Ben'];
        yield 'http' => ['http://www.google.com/maps/embed?pb=!1m18'];
        yield 'a port' => ['https://www.google.com:8443/maps/embed?pb=!1m18'];
        yield 'a user in the address' => ['https://evil@www.google.com/maps/embed?pb=!1m18'];
        yield 'a quote out of the attribute' => ['https://www.google.com/maps/embed?pb=!1m18"onload="alert(1)'];
        yield 'javascript:' => ['javascript:alert(1)//www.google.com/maps/embed?pb=1'];
        yield 'a short link to share' => ['https://maps.app.goo.gl/AbCdEf123'];
        yield 'a frame of another site' => ['<iframe src="https://www.youtube.com/embed/aqz-KE-bpKQ"></iframe>'];
        yield 'a script beside the frame' => [self::code().'<script>alert(1)</script>'];
        yield 'a frame inside more HTML' => ['<div>'.self::code().'</div>'];
        yield 'two frames' => [self::code().self::code()];
        yield 'a frame without an address' => ['<iframe width="600"></iframe>'];
        yield 'a script alone' => ['<script src="https://www.google.com/maps/embed?pb=1"></script>'];
        yield 'words' => ['Trafalgar Square, London'];
    }

    #[Test]
    #[DataProvider('notGoogles')]
    public function anything_but_googles_frame_is_no_address(string $pasted): void
    {
        $this->assertNull(GoogleMapsEmbed::address($pasted));
    }

    #[Test]
    public function open_in_google_maps_is_read_from_the_address_when_it_says_where(): void
    {
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=51.508,-0.128', GoogleMapsEmbed::open(self::SRC), 'the centre of a pb');
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=Trafalgar%20Square%2C%20London', GoogleMapsEmbed::open('https://maps.google.com/maps?q=Trafalgar%20Square%2C%20London&output=embed'));
        $this->assertNull(GoogleMapsEmbed::open('https://www.google.com/maps/embed?pb=!1m3!1m2!1s0x0'), 'a pb without a centre says nothing');
    }

    #[Test]
    public function before_consent_it_is_the_place_and_the_notice_and_no_frame(): void
    {
        $html = Blade::render('<x-webx-map :embed="$code" address="Trafalgar Square, London" title="Map of the square" height="28rem" height-narrow="18rem" />', ['code' => self::code()]);

        $this->assertStringContainsString('style="--webx-map-height: 28rem; --webx-map-height-narrow: 18rem" class="webx-map webx-map--embed is-blocked"', $html, 'the frame reserved before anything loads');
        $this->assertStringContainsString('aria-label="Map: Map of the square"', $html);
        $this->assertStringContainsString('data-webx-consent="media"', $html);
        $this->assertStringContainsString('<p class="webx-map__address">Trafalgar Square, London</p>', $html);
        $this->assertStringContainsString('href="https://www.google.com/maps/search/?api=1&amp;query=51.508,-0.128"', $html, 'the link without JavaScript');
        $this->assertStringContainsString('rel="noopener">Open in Google Maps</a>', $html);
        $this->assertStringContainsString('The map loads from Google Maps, which receives your IP address.', $html);
        $this->assertStringContainsString('data-webx-map-load', $html);
        $this->assertStringContainsString('data-webx-map-always', $html);
        $this->assertStringNotContainsString('<iframe', $html, 'nothing of Google before consent');
        $this->assertStringContainsString('&quot;provider&quot;:&quot;embed&quot;', $html);
        $this->assertStringContainsString('&quot;title&quot;:&quot;Map of the square&quot;', $html);
        $this->assertStringContainsString(htmlspecialchars(json_encode(self::SRC, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), $html, 'the address alone goes to the script');
        $this->assertStringNotContainsString('webx-map__attribution', $html, 'Google draws its own');
        $this->assertContains('map-embed', Widgets::claimed());
        $this->assertNotContains('map', Widgets::claimed(), 'no Leaflet');
        $this->assertNotContains('map-google', Widgets::claimed(), 'no Maps JavaScript API');
    }

    #[Test]
    public function a_link_of_its_own_is_the_link_and_the_height_is_one_unless_told(): void
    {
        $html = Blade::render('<x-webx-map :embed="$src" link="https://maps.app.goo.gl/AbCdEf123" />', ['src' => self::SRC]);

        $this->assertStringContainsString('href="https://maps.app.goo.gl/AbCdEf123"', $html);
        $this->assertStringContainsString('--webx-map-height: 24rem; --webx-map-height-narrow: 24rem', $html);
        $this->assertStringContainsString('aria-label="Map: Map"', $html, 'no title and no address: a map');
    }

    #[Test]
    public function with_consent_or_without_the_wait_the_script_puts_it_in_at_once(): void
    {
        $this->app['request']->cookies->set(Consent::COOKIE, json_encode(['v' => 1, 'd' => '2026-11-11', 'c' => ['media']], JSON_THROW_ON_ERROR));

        $html = Blade::render('<x-webx-map :embed="$src" />', ['src' => self::SRC]);
        $this->assertStringContainsString('class="webx-map webx-map--embed"', $html);
        $this->assertStringNotContainsString('webx-map__notice', $html);

        $this->assertStringNotContainsString('<iframe', $html, 'a visitor\'s consent is the script\'s: the page may be cached');

        $this->app['request']->cookies->remove(Consent::COOKIE);
        $free = Blade::render('<x-webx-map :embed="$src" :consent="false" title="Map of the square" />', ['src' => self::SRC]);
        $this->assertStringNotContainsString('data-webx-consent', $free);
        $this->assertStringContainsString('&quot;consent&quot;:false', $free);
        $this->assertStringContainsString('class="webx-map webx-map--embed is-loaded"', $free);
        $this->assertStringContainsString('<div class="webx-map__canvas"><iframe class="webx-map__frame" src="'.e(self::SRC).'" title="Map of the square" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>', $free, 'without the wait the frame is in the HTML');
        $this->assertStringContainsString('<div class="webx-map__place" hidden>', $free);
        $this->assertStringNotContainsString('webx-map__notice', $free);
    }

    #[Test]
    public function the_sites_maps_without_asking_prints_the_frame_and_the_map_itself_can_still_wait(): void
    {
        $this->app['config']->set('webx-widgets.map.consent', false);

        $html = Blade::render('<x-webx-map :embed="$src" />', ['src' => self::SRC]);
        $this->assertStringContainsString('<iframe class="webx-map__frame"', $html);

        $waits = Blade::render('<x-webx-map :embed="$src" :consent="true" />', ['src' => self::SRC]);
        $this->assertStringNotContainsString('<iframe', $waits, 'the map\'s own word wins');
        $this->assertStringContainsString('data-webx-map-load', $waits);
    }

    #[Test]
    public function a_code_that_is_not_googles_map_is_refused(): void
    {
        try {
            Blade::render('<x-webx-map embed="https://www.youtube.com/embed/aqz-KE-bpKQ" />');
        } catch (Throwable $error) {
            $cause = $error instanceof ViewException && $error->getPrevious() !== null ? $error->getPrevious() : $error;
            $this->assertInstanceOf(InvalidArgumentException::class, $cause);
            $this->assertStringContainsString('"Embed a map"', $cause->getMessage());

            return;
        }

        $this->fail('A frame of another site was printed.');
    }

    #[Test]
    public function the_panels_field_keeps_the_address_and_refuses_the_rest(): void
    {
        $this->assertContains(GoogleMapsEmbed::SOURCE, $this->app->make(Embeds::class)->names(), 'given to the panel');

        $type = $this->app->make(FieldTypes::class)->get('wx-embed');
        $this->assertNotNull($type);
        $node = ['id' => 'embed', 'type' => 'wx-embed', 'props' => ['source' => GoogleMapsEmbed::SOURCE]];

        $this->assertSame(self::SRC, $type->store(self::code(), $node), 'the address, not the HTML');
        $this->assertNull($type->store('', $node));

        $check = fn (mixed $value) => validator(['value' => $value], ['value' => $type->rules($node)]);
        $this->assertFalse($check(self::code())->fails());
        $this->assertFalse($check(self::SRC)->fails());
        $this->assertFalse($check(null)->fails());

        $refused = $check('<iframe src="https://maps.example.com/embed"></iframe>');
        $this->assertTrue($refused->fails());
        $this->assertStringContainsString('Google Maps', (string) $refused->errors()->first('value'));
    }
}
