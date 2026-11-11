<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\MigratesOnce;
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
 * ROUTING §18.6: somebody sent once already, back on a page of another language, is offered the
 * first visit's language beside the switcher — the same page in it, where the redirect would have
 * led; never to a robot, never once declined, never where the language has only a home page.
 */
final class LanguageOfferTest extends TestCase
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

        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'de'],
            ['code' => 'pl'],
        ]);
        $app['config']->set('webx-localization.first_visit.locale', 'de');
        $app['config']->set('webx-localization.first_visit.offer.enabled', true);
    }

    #[Test]
    public function it_offers_the_same_page_in_the_language(): void
    {
        $this->serve('/about-us', 'en', ['webx_lang' => 'de']);

        $html = Blade::render('<x-webx-language-switcher layout="list" />');

        $this->assertStringContainsString('<div class="webx-language-offer" role="dialog" aria-modal="false" aria-labelledby="webx-language-offer-1" lang="de" data-webx-language-offer="webx_lang_offer" data-webx-language-offer-lifetime="31536000" hidden>', $html);
        // Asked in the language offered, which names itself.
        $this->assertStringContainsString('<p class="webx-language-offer__question" id="webx-language-offer-1">Zu Deutsch wechseln?</p>', $html);
        $this->assertStringContainsString('<a class="webx-language-offer__accept" href="https://example.test/de/ueber-uns" hreflang="de">Ja</a>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-language-offer__stay">Bleiben</button>', $html);
        $this->assertStringContainsString('aria-label="Schließen"', $html);
        // Inside the switcher, after its list: beside it, over the page.
        $this->assertMatchesRegularExpression('#</ul>\s*<div class="webx-language-offer"#', $html);
        $this->assertContains('language-offer', app(Widgets::class)->claimed());
    }

    #[Test]
    public function the_words_of_the_ukrainian_offer_are_the_ones_asked_for(): void
    {
        $this->assertSame('Перейти на українську?', __('webx-widgets::widgets.language.offer.question', [], 'uk'));
        $this->assertSame('Так', __('webx-widgets::widgets.language.offer.accept', [], 'uk'));
        $this->assertSame('Залишитись', __('webx-widgets::widgets.language.offer.stay', [], 'uk'));
    }

    #[Test]
    public function nobody_sent_yet_is_offered_nothing(): void
    {
        $this->assertNoOffer('/about-us', 'en', []);
    }

    #[Test]
    public function a_robot_is_offered_nothing(): void
    {
        $this->assertNoOffer('/about-us', 'en', ['webx_lang' => 'de'], 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
    }

    #[Test]
    public function switched_off_there_is_no_offer(): void
    {
        config()->set('webx-localization.first_visit.offer.enabled', false);

        $this->assertNoOffer('/about-us', 'en', ['webx_lang' => 'de']);
    }

    #[Test]
    public function without_the_first_visit_there_is_no_offer(): void
    {
        config()->set('webx-localization.first_visit.locale', null);

        $this->assertNoOffer('/about-us', 'en', ['webx_lang' => 'de']);
    }

    #[Test]
    public function once_declined_it_does_not_come_back(): void
    {
        $this->assertNoOffer('/about-us', 'en', ['webx_lang' => 'de', 'webx_lang_offer' => '1']);
    }

    #[Test]
    public function on_a_page_of_the_language_there_is_nothing_to_offer(): void
    {
        $this->assertNoOffer('/de/ueber-uns', 'de', ['webx_lang' => 'de']);
    }

    #[Test]
    public function a_page_with_only_a_home_page_in_the_language_is_offered_nothing(): void
    {
        // The page has no Polish: the switcher's link to Polish is that language's home page.
        config()->set('webx-localization.first_visit.locale', 'pl');

        $this->assertNoOffer('/about-us', 'en', ['webx_lang' => 'pl']);
    }

    #[Test]
    public function one_offer_a_page_and_a_switcher_may_go_without(): void
    {
        $this->serve('/about-us', 'en', ['webx_lang' => 'de']);

        $html = Blade::render('<x-webx-language-switcher /><x-webx-language-switcher layout="list" />');
        $this->assertSame(1, substr_count($html, 'class="webx-language-offer"'));

        $this->serve('/about-us', 'en', ['webx_lang' => 'de']);
        $this->app->forgetInstance(Widgets::class);
        $html = Blade::render('<x-webx-language-switcher :offer="false" />');
        $this->assertStringNotContainsString('webx-language-offer', $html);
    }

    /** @param  array<string, string>  $cookies */
    private function assertNoOffer(string $path, string $locale, array $cookies, string $agent = self::BROWSER): void
    {
        $this->serve($path, $locale, $cookies, $agent);

        $html = Blade::render('<x-webx-language-switcher layout="list" />');

        $this->assertStringContainsString('webx-language-switcher__list', $html);
        $this->assertStringNotContainsString('webx-language-offer', $html);
        $this->assertNotContains('language-offer', app(Widgets::class)->claimed());
    }

    /**
     * The page of a doc in English and German, as the registry's resolver leaves the request.
     *
     * @param  array<string, string>  $cookies
     */
    private function serve(string $path, string $locale, array $cookies, string $agent = self::BROWSER): void
    {
        $doc = new Doc;
        $doc->setAttribute('id', 7);

        if (Route::query()->count() === 0) {
            foreach (['en' => 'about-us', 'de' => 'ueber-uns'] as $code => $slug) {
                Route::query()->create(['locale' => $code, 'path' => $slug, 'kind' => Route::CANONICAL, 'entity_type' => $doc->getMorphClass(), 'entity_id' => 7]);
            }
        }

        $request = Request::create('https://example.test'.$path, 'GET', [], $cookies, [], ['HTTP_USER_AGENT' => $agent]);
        $route = new LaravelRoute('GET', '{fallbackPlaceholder}', static fn (): string => '');
        $route->isFallback = true;
        $request->setRouteResolver(static fn (): LaravelRoute => $route);

        $row = Route::query()->forEntity($doc)->canonical()->where('locale', $locale)->firstOrFail();
        $request->attributes->set(Resolution::ATTRIBUTE, new Resolution($row, '', null, $doc, $locale));

        $this->app->instance('request', $request);
        $this->app->setLocale($locale);
    }
}
