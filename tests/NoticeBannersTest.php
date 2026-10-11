<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Banners\BannersServiceProvider;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Places;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\View\Components\NoticeBar;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * Spec §14, W5.4: the announcement bar says what the place `notice` of module-banners holds — a
 * banner of words only, the place's own exception to "a banner needs a picture" — and the slot
 * when the place is empty.
 */
final class NoticeBannersTest extends TestCase
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
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
            MediaServiceProvider::class,
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            BannersServiceProvider::class,
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
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru']]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    #[Test]
    public function the_bar_says_the_first_banner_of_the_place_notice_and_the_slot_while_it_has_none(): void
    {
        $slot = '<x-webx-notice-bar>From the template</x-webx-notice-bar>';
        $this->assertStringContainsString('From the template', Blade::render($slot));

        $place = $this->app->make(Places::class)->row('notice');
        $this->assertNotNull($place);

        // Off: not on the site, so the slot still speaks.
        $this->banner($place->getKey(), ['title' => ['en' => 'Hidden'], 'enabled' => false]);
        $this->assertStringContainsString('From the template', Blade::render($slot));

        $this->banner($place->getKey(), [
            'title' => ['en' => 'Open on Saturdays', 'ru' => 'Работаем по субботам'],
            'text' => ['en' => "From November\non"],
            'buttons' => [['label' => ['en' => 'Hours'], 'link' => ['target' => 'url', 'url' => '/hours', 'new_tab' => true, 'rel' => []], 'variant' => 'link']],
        ]);
        $this->banner($place->getKey(), ['title' => ['en' => 'Second, never shown']]);

        $html = Blade::render($slot);
        $card = ['title' => 'Open on Saturdays', 'text' => "From November\non", 'buttons' => [['label' => 'Hours', 'url' => 'http://localhost/hours', 'new_tab' => true, 'rel' => 'noopener noreferrer', 'variant' => 'link']]];

        $this->assertStringNotContainsString('From the template', $html);
        $this->assertStringNotContainsString('Second', $html);
        $this->assertStringContainsString('<strong class="webx-notice-bar__title">Open on Saturdays</strong>', $html);
        $this->assertStringContainsString('<span class="webx-notice-bar__text">From November<br>'."\n".'on</span>', $html);
        $this->assertStringContainsString('<a class="webx-notice-bar__link webx-notice-bar__link--link" href="http://localhost/hours" target="_blank" rel="noopener noreferrer">Hours</a>', $html);
        $this->assertStringContainsString('data-webx-notice-bar="'.NoticeBar::stamp($card).'"', $html);

        // Its words in Russian: the title only, and another version.
        App::setLocale('ru');
        $russian = Blade::render($slot);
        $this->assertStringContainsString('Работаем по субботам', $russian);
        $this->assertStringNotContainsString('webx-notice-bar__link', $russian, 'the button has no Russian label');

        // Another place by name, or none: the slot.
        App::setLocale('en');
        $this->assertStringContainsString('From the template', Blade::render('<x-webx-notice-bar place="promo">From the template</x-webx-notice-bar>'));
        $this->assertStringContainsString('From the template', Blade::render('<x-webx-notice-bar :place="null">From the template</x-webx-notice-bar>'));
    }

    /** @param  array<string, mixed>  $attributes */
    private function banner(mixed $place, array $attributes): void
    {
        $banner = new Banner(['enabled' => true, ...$attributes]);
        $banner->moveToEndOf((int) $place);
        $banner->save();
    }
}
