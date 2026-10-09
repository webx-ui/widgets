<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $public;

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
}
