<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Widgets\Facades\Widgets as WidgetsFacade;
use WebxUi\Widgets\Widgets;

/**
 * Spec §4: the runtime on every page with a theme, a widget's own files only where it is
 * claimed — and claimed late is not too late, since the header renders after the head.
 */
class LoadingTest extends TestCase
{
    /** A widget with files of its own, written for the test: the real ones come with W2. */
    private const string HEAVY = 'zz-test-widget';

    protected function tearDown(): void
    {
        foreach (['js', 'css'] as $extension) {
            @unlink(Widgets::path().'/dist/'.self::HEAVY.'.'.$extension);
        }

        parent::tearDown();
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/plain', static fn (): string => Blade::render('<x-layout><p>Hello</p></x-layout>'));

        // The claim comes after @webxTheme has printed, the way the header's does.
        $router->get('/late', static fn (): string => Blade::render(
            "<html><head>@webxTheme</head><body><p>Hello</p>@php(WebxUi\\Widgets\\Facades\\Widgets::need('".self::HEAVY."'))</body></html>",
        ));

        $router->get('/json', static fn () => response()->json(['html' => Widgets::MARKER.'</body>']));
    }

    #[Test]
    public function every_page_with_a_theme_gets_the_runtime_the_consent_banner_and_no_more(): void
    {
        $this->sync();
        $base = $this->published();

        $html = (string) $this->get('/plain')->assertOk()->getContent();

        $this->assertStringNotContainsString(Widgets::MARKER, $html);
        $this->assertStringContainsString("<link rel=\"stylesheet\" href=\"{$base}/runtime.css\">", $html);
        $this->assertStringContainsString("<link rel=\"stylesheet\" href=\"{$base}/consent.css\">", $html);
        $this->assertStringContainsString("<script type=\"module\" src=\"{$base}/runtime.js\"></script>\n<script type=\"module\" src=\"{$base}/consent.js\"></script>\n</body>", $html);
        $this->assertSame(2, substr_count($html, '<script type="module"'));
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'runtime.css'));

        // The banner before the scripts, and the Consent Mode default in the head (§9.3).
        $this->assertLessThan(strpos($html, 'runtime.js'), (int) strpos($html, 'data-webx-consent-root'));
        $this->assertLessThan(strpos($html, '</head>'), (int) strpos($html, '"consent","default"'));
    }

    #[Test]
    public function a_widget_claimed_after_the_head_still_gets_its_files_in_the_head(): void
    {
        $this->heavyWidget();
        $this->sync();
        $base = $this->published();

        $html = (string) $this->get('/late')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, '</head>'), (int) strpos($html, "{$base}/".self::HEAVY.'.css'));
        $this->assertStringContainsString("<script type=\"module\" src=\"{$base}/".self::HEAVY.'.js"></script>', $html);

        // The next request of the same worker claimed nothing.
        $this->assertStringNotContainsString(self::HEAVY, (string) $this->get('/plain')->getContent());
    }

    #[Test]
    public function an_unpublished_package_costs_the_page_nothing_but_a_hint(): void
    {
        $html = (string) $this->get('/plain')->assertOk()->getContent();

        $this->assertStringContainsString('<!-- webx-widgets: not published: php artisan webx:theme:sync -->', $html);
        $this->assertStringNotContainsString('<script', $html);

        config()->set('app.debug', false);
        $this->assertStringNotContainsString('<!--', (string) $this->get('/plain')->getContent());
    }

    #[Test]
    public function json_that_carries_the_marker_stays_json(): void
    {
        $this->sync();

        $this->get('/json')->assertExactJson(['html' => Widgets::MARKER.'</body>']);
    }

    #[Test]
    public function the_runtime_behaviours_can_be_claimed_and_a_typo_cannot(): void
    {
        foreach (Widgets::RUNTIME as $behaviour) {
            WidgetsFacade::need($behaviour);
        }

        $this->assertSame(Widgets::RUNTIME, WidgetsFacade::claimed());

        $this->expectException(InvalidArgumentException::class);
        WidgetsFacade::need('slidr');
    }

    #[Test]
    public function a_second_head_prints_nothing_twice(): void
    {
        $this->sync();

        $html = app(Widgets::class)->finish('<head>'.Widgets::MARKER.Widgets::MARKER.'</head><body></body>');

        $this->assertSame(1, substr_count($html, 'runtime.css'));
        $this->assertSame(1, substr_count($html, 'runtime.js'));
        $this->assertStringNotContainsString(Widgets::MARKER, $html);
    }

    private function sync(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();
    }

    private function published(): string
    {
        $layer = app(BottomLayers::class)->get(Widgets::NAME);
        $this->assertNotNull($layer);

        return 'http://localhost/themes/webx-ui/widgets/'.app(ThemeAssets::class)->current($layer);
    }

    private function heavyWidget(): void
    {
        file_put_contents(Widgets::path().'/dist/'.self::HEAVY.'.js', "console.log('heavy')\n");
        file_put_contents(Widgets::path().'/dist/'.self::HEAVY.'.css', ":where(.heavy) {}\n");
    }
}
