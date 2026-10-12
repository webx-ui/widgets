<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Widgets;

/**
 * A site that has not adopted themes keeps the head it had — the widgets add nothing to it —
 * unless its layout asks for their scripts with `@webxWidgets`: then its forms send in place.
 */
class NoThemeTest extends TestCase
{
    private const string FORM = '<x-webx-form action="/contact"><x-webx-field name="email" type="email" label="Email" required /><x-webx-form.submit>Send</x-webx-form.submit></x-webx-form>';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('webx-themes.theme', '');
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(static function (Router $router): void {
            $router->get('/asked', static fn (): string => Blade::render('<html><head><title>Asked</title></head><body>'.self::FORM.'@webxWidgets</body></html>'));
            $router->get('/plain', static fn (): string => Blade::render('<html><head>@webxTheme</head><body>'.self::FORM.'</body></html>'));
        });
    }

    #[Test]
    public function the_head_is_untouched(): void
    {
        $this->assertSame('', Blade::render('@webxTheme'));

        $this->artisan('webx:theme:sync')->assertSuccessful();
        $html = (string) $this->get('/plain')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<link', $html);
    }

    #[Test]
    public function sync_publishes_the_widgets_and_nothing_of_a_chain(): void
    {
        $this->artisan('webx:theme:sync')
            ->expectsOutputToContain('only the packages below the themes')
            ->assertSuccessful();

        $this->assertFileExists($this->public.'/themes/webx-ui/widgets/current');
        $this->assertDirectoryDoesNotExist($this->public.'/themes/modules');
        $this->assertDirectoryDoesNotExist($this->public.'/themes/icons');
    }

    #[Test]
    public function the_directive_brings_the_scripts_of_a_form_and_no_stylesheet(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();
        $html = (string) $this->get('/asked')->assertOk()->getContent();

        foreach (['runtime', 'form', 'field'] as $widget) {
            $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/themes/webx-ui/widgets/[0-9a-f]{12}/'.$widget.'\.js"></script>~', $html);
        }

        $this->assertLessThan(strpos($html, '</body>'), (int) strpos($html, 'field.js'));

        $this->assertStringNotContainsString(Widgets::SCRIPTS, $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
        // An unstyled banner over the page would be worse than none.
        $this->assertStringNotContainsString('webx-consent', $html);
        $this->assertStringContainsString('data-webx-send', $html);
    }

    #[Test]
    public function before_the_sync_debug_says_which_command_was_forgotten(): void
    {
        $html = (string) $this->get('/asked')->assertOk()->getContent();

        $this->assertStringContainsString('<!-- webx-widgets: not published: php artisan webx:theme:sync -->', $html);
        $this->assertStringNotContainsString('<script', $html);
    }
}
