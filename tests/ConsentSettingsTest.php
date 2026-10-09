<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * §9.5: with `module-settings` the banner is set up on the "Cookie" tab of the settings screen —
 * a patch from this package — and over MCP like any other setting.
 */
final class ConsentSettingsTest extends TestCase
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

    #[Test]
    public function the_cookie_tab_is_on_the_settings_screen(): void
    {
        $names = array_column(app(ScreenRegistry::class)->fields(Settings::SCREEN), 'name');

        foreach (['consent.enabled', 'consent.policy', 'consent.version', 'consent.ask-again', 'consent.categories', 'consent.text-title', 'consent.text-text', 'consent.text-media'] as $key) {
            $this->assertContains($key, $names);
        }

        $this->assertStringContainsString('"label":"trans::webx-widgets::panel.tab"', (string) json_encode(app(ScreenRegistry::class)->tree(Settings::SCREEN)));
        $this->assertSame('Cookie', __('webx-widgets::panel.tab'));
    }

    #[Test]
    public function nothing_saved_reads_as_the_defaults(): void
    {
        $consent = app(Consent::class);

        $this->assertTrue($consent->enabled());
        $this->assertSame('1', $consent->version());
        $this->assertNull($consent->policy());
        $this->assertSame('Cookies on this site', $consent->text('title'));
    }

    #[Test]
    public function the_settings_reach_the_banner_over_mcp_too(): void
    {
        $set = fn (string $key, mixed $value): mixed => (app(ToolRegistry::class)->tool('settings_set')->tool->handler)(['key' => $key, 'value' => $value]);

        $this->assertTrue($set('consent.enabled', false)['applied']);
        $this->assertTrue($set('consent.text-title', ['en' => 'Our cookies', 'de' => 'Unsere Cookies'])['applied']);
        $this->assertTrue($set('consent.categories', ['media'])['applied']);
        $this->assertTrue($set('consent.policy', ['target' => 'url', 'url' => '/cookies'])['applied']);

        $consent = app(Consent::class);

        $this->assertFalse($consent->enabled());
        $this->assertTrue($consent->has('marketing'));
        $this->assertSame('Our cookies', $consent->text('title'));
        $this->assertSame(['media'], $consent->categories());
        $this->assertSame('http://localhost/cookies', $consent->policy());

        app()->setLocale('de');
        $this->assertSame('Unsere Cookies', $consent->text('title'));
        // A word nobody wrote is the dictionary's, in the visitor's language.
        $this->assertSame('Alle akzeptieren', __('webx-widgets::widgets.consent.accept'));
    }

    #[Test]
    public function asking_everyone_again_raises_the_version_and_turns_itself_off(): void
    {
        $settings = app(Settings::class);

        $settings->save(['consent.version' => 3]);
        $settings->save(['consent.ask-again' => true]);

        $this->assertSame(4, (int) $settings->get('consent.version'));
        $this->assertFalse((bool) $settings->get('consent.ask-again'));
        $this->assertSame('4', app(Consent::class)->version());

        // Saved off, it is left alone.
        $settings->save(['consent.ask-again' => false]);
        $this->assertSame(4, (int) $settings->get('consent.version'));
    }
}
