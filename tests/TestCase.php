<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $public;

    protected function setUp(): void
    {
        parent::setUp();

        // A test that reaches the network waits on it; whatever it calls is faked.
        Http::preventStrayRequests();
    }

    /**
     * Widgets after the themes, the order Composer installs them in: the theme's view overrides
     * must still win over the package's own views.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $this->public = str_replace('\\', '/', sys_get_temp_dir()).'/webx-widgets-public-'.bin2hex(random_bytes(6));
        mkdir($this->public);
        $app->usePublicPath($this->public);

        $app['config']->set('app.debug', true);
        $app['config']->set('webx-themes.theme', $this->theme());

        $app->resolving(ThemeLocator::class, function (ThemeLocator $locator): void {
            $locator->register('fixture/widgets-theme', self::fixture('theme'));
            $locator->register('fixture/widgets-theme-override', self::fixture('theme-override'));
        });
    }

    protected function theme(): string
    {
        return 'fixture/widgets-theme';
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->public);

        parent::tearDown();
    }

    protected static function fixture(string $path): string
    {
        return str_replace('\\', '/', __DIR__).'/Fixtures/'.$path;
    }

    /**
     * The page loads the widget's script: a tag of its own, or an entry of the list of those that
     * wait until wanted (spec §4.1) — the runtime fetches it when its element comes near.
     */
    protected function assertLoadsScript(string $widget, string $html, string $message = ''): void
    {
        $tag = preg_match('~<script type="module" src="[^"]+/'.preg_quote($widget, '~').'\.js"></script>~', $html) === 1;

        $this->assertTrue($tag || in_array($widget, self::onDemand($html), true), $message !== '' ? $message : "The page does not load {$widget}.js.");
    }

    /**
     * The widgets of the page's list of scripts that wait until wanted (spec §4.1), by file name.
     *
     * @return list<string>
     */
    protected static function onDemand(string $html): array
    {
        $found = [];

        if (preg_match_all('~<script type="application/json" data-webx-on-demand>(.*?)</script>~s', $html, $lists) > 0) {
            foreach ($lists[1] as $json) {
                foreach ((array) json_decode($json, true) as $entry) {
                    foreach ((array) ($entry['src'] ?? []) as $src) {
                        $found[] = basename((string) parse_url((string) $src, PHP_URL_PATH), '.js');
                    }
                }
            }
        }

        return $found;
    }
}
