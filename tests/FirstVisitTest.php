<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * ROUTING §18 end to end, the way a visitor goes through it: the first visit to a page of the
 * default language lands on the same page in the chosen one, once; the switcher's plain link leads
 * back, and from then on nobody is sent anywhere — at most offered the language (§18.6).
 */
final class FirstVisitTest extends TestCase
{
    use MigratesOnce;

    private const string BROWSER = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

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

        $app['config']->set('app.url', 'https://shop.test');
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.locales', [
            ['code' => 'ru', 'default' => true],
            ['code' => 'uk'],
        ]);
        $app['config']->set('webx-localization.first_visit.locale', 'uk');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // A module's own page — outside the registry, the same path under each prefix.
        $page = static fn (): string => Blade::render('<main><x-webx-language-switcher layout="list" /></main>');

        Route::middleware('webx.locale')->group(function () use ($page): void {
            Route::get('cameras', $page);
            Route::get('{webxLocale}/cameras', $page)->where('webxLocale', 'uk');
        });
    }

    #[Test]
    public function a_visitor_is_sent_once_and_the_switcher_s_plain_links_do_the_rest(): void
    {
        // The first visit: the same page in Ukrainian, and the cookie.
        $first = $this->visit('https://shop.test/cameras');
        $first->assertStatus(302)->assertRedirect('https://shop.test/uk/cameras');
        $first->assertCookie('webx_lang', 'uk', false);

        // There, the switcher's link to Russian is the plain address, as with the setting off.
        $ukrainian = $this->visit('https://shop.test/uk/cameras', 'uk');
        $ukrainian->assertOk();
        $ukrainian->assertSee('href="https://shop.test/cameras" hreflang="ru" lang="ru">', false);

        // Following it: Russian, and nobody sends the person anywhere again — whatever the cookie says.
        $russian = $this->visit('https://shop.test/cameras', 'uk');
        $russian->assertOk();
        $russian->assertSee('is-current" href="https://shop.test/cameras" hreflang="ru" lang="ru" aria-current="page">', false);
        $this->assertNull($this->cookieOf($russian));
        $this->assertDontSee($russian, 'webx-language-offer');
    }

    #[Test]
    public function with_the_offer_on_a_later_visit_is_offered_the_language_instead(): void
    {
        config()->set('webx-localization.first_visit.offer.enabled', true);

        $russian = $this->visit('https://shop.test/cameras', 'uk');
        $russian->assertOk();
        $russian->assertSee('<a class="webx-language-offer__accept" href="https://shop.test/uk/cameras" hreflang="uk">Так</a>', false);
        $russian->assertSee('Перейти на українську?');

        // Declined: never again.
        $this->withUnencryptedCookie('webx_lang_offer', '1');
        $this->assertDontSee($this->visit('https://shop.test/cameras', 'uk'), 'webx-language-offer');
    }

    #[Test]
    public function a_robot_reads_the_page_of_the_default_language_at_its_address(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Accept' => 'text/html',
        ])->get('https://shop.test/cameras');

        $response->assertOk()->assertSee('webx-language-switcher', false);
        $this->assertNull($this->cookieOf($response));
    }

    #[Test]
    public function switched_off_the_page_is_served_as_before(): void
    {
        config()->set('webx-localization.first_visit.locale', null);

        $response = $this->visit('https://shop.test/cameras');

        $response->assertOk();
        $response->assertSee('href="https://shop.test/uk/cameras" hreflang="uk" lang="uk">', false);
        $response->assertDontSee('webx_lang', false);
        $this->assertNull($this->cookieOf($response));
    }

    /** @return TestResponse<Response> */
    private function visit(string $url, ?string $cookie = null): TestResponse
    {
        if ($cookie !== null) {
            $this->withUnencryptedCookie('webx_lang', $cookie);
        }

        return $this->withHeaders([
            'User-Agent' => self::BROWSER,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ])->get($url);
    }

    /** @param TestResponse<Response> $response */
    private function assertDontSee(TestResponse $response, string $text): void
    {
        $this->assertStringNotContainsString($text, (string) $response->getContent());
    }

    /** @param TestResponse<Response> $response */
    private function cookieOf(TestResponse $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'webx_lang') {
                return (string) $cookie->getValue();
            }
        }

        return null;
    }
}
