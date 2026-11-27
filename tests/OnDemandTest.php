<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Widgets\Widgets;

/**
 * Spec §4.1: the scripts of the widgets that wait until wanted are a list the runtime reads, not
 * tags — fetched when their element comes near — and a page can still want one at once.
 */
final class OnDemandTest extends TestCase
{
    private const string LIST = '~<script type="application/json" data-webx-on-demand>(.*?)</script>~s';

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $page = static fn (string $body): string => Blade::render('<html><head>@webxTheme</head><body>'.$body.'</body></html>');

        $router->get('/slider', static fn (): string => $page("<div data-webx-slider></div>@php(WebxUi\\Widgets\\Facades\\Widgets::need('slider'))"));
        $router->get('/eager', static fn (): string => $page("<div data-webx-slider data-webx-eager=\"slider\"></div>@php(WebxUi\\Widgets\\Facades\\Widgets::need('slider'))"));
        $router->get('/eager-call', static fn (): string => $page("@php(WebxUi\\Widgets\\Facades\\Widgets::need('slider'))@php(WebxUi\\Widgets\\Facades\\Widgets::eager('slider'))"));
        // The steps claimed before the form: the list runs the form's script first all the same.
        $router->get('/form', static fn (): string => $page("@php(WebxUi\\Widgets\\Facades\\Widgets::need('steps'))@php(WebxUi\\Widgets\\Facades\\Widgets::need('field'))@php(WebxUi\\Widgets\\Facades\\Widgets::need('form'))"));
        $router->get('/form-eager', static fn (): string => $page("@php(WebxUi\\Widgets\\Facades\\Widgets::need('form'))@php(WebxUi\\Widgets\\Facades\\Widgets::need('steps'))@php(WebxUi\\Widgets\\Facades\\Widgets::eager('steps'))"));
        $router->get('/at-once', static fn (): string => $page("@php(WebxUi\\Widgets\\Facades\\Widgets::need('contacts'))@php(WebxUi\\Widgets\\Facades\\Widgets::need('attribution'))"));
    }

    #[Test]
    public function every_selector_of_the_list_is_the_one_its_script_registers(): void
    {
        foreach (Widgets::ON_DEMAND as $widget => $selector) {
            $source = (string) file_get_contents(Widgets::path()."/resources/js/{$widget}.js");
            $registered = self::registered($source);

            // The captcha registers no widget: it finds its element inside the form it sends.
            if ($widget === 'captcha') {
                $this->assertMatchesRegularExpression("~var CAPTCHA = '".preg_quote($selector, '~')."'~", $source);

                continue;
            }

            $this->assertNotSame([], $registered, "{$widget}.js registers no widget.");
            $this->assertEqualsCanonicalizing($registered, array_map('trim', explode(',', $selector)), "The selector of {$widget} is not the one {$widget}.js registers.");
        }

        foreach (Widgets::GROUPS as $name => $members) {
            $this->assertSame($name, $members[0], 'a group is named after its first member');

            foreach ($members as $member) {
                $this->assertArrayHasKey($member, Widgets::ON_DEMAND);
            }
        }
    }

    #[Test]
    public function a_page_lists_the_script_of_its_slider_for_the_runtime_and_prints_no_tag(): void
    {
        $base = $this->sync();
        $html = (string) $this->get('/slider')->assertOk()->getContent();

        $this->assertStringNotContainsString("<script type=\"module\" src=\"{$base}/slider.js\"></script>", $html);
        $this->assertSame(['slider' => ['src' => ["{$base}/slider.js"], 'on' => '[data-webx-slider]']], self::list($html));
        // Its stylesheet as before: the page looks the same before the script comes.
        $this->assertLessThan(strpos($html, '</head>'), (int) strpos($html, "{$base}/slider.css"));
        // The list before the runtime, which reads it.
        $this->assertLessThan(strpos($html, "{$base}/runtime.js"), (int) strpos($html, 'data-webx-on-demand'));
    }

    #[Test]
    public function a_page_can_want_the_script_at_once(): void
    {
        $base = $this->sync();

        foreach (['/eager', '/eager-call'] as $page) {
            $html = (string) $this->get($page)->assertOk()->getContent();

            $this->assertStringContainsString("<script type=\"module\" src=\"{$base}/slider.js\"></script>", $html, $page);
            $this->assertStringNotContainsString('data-webx-on-demand', $html, $page);
        }

        // The next request of the same worker wants nothing at once.
        $this->assertStringNotContainsString("src=\"{$base}/slider.js\"", (string) $this->get('/slider')->getContent());
    }

    #[Test]
    public function the_parts_of_a_form_are_fetched_together_in_the_groups_order(): void
    {
        $base = $this->sync();
        $list = self::list((string) $this->get('/form')->assertOk()->getContent());

        $this->assertSame(['form'], array_keys($list));
        $this->assertSame(["{$base}/form.js", "{$base}/steps.js"], $list['form']['src']);
        $this->assertSame('form[data-webx-send], form.webx-form--steps', $list['form']['on']);
        // The fields' few lines at once: Show of a password is hidden until the script.
        $this->assertStringContainsString("<script type=\"module\" src=\"{$base}/field.js\"></script>", (string) $this->get('/form')->getContent());

        // One part wanted at once, the group whole: the steps start on the form's script.
        $html = (string) $this->get('/form-eager')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-webx-on-demand', $html);
        $this->assertLessThan(strpos($html, "{$base}/steps.js"), (int) strpos($html, "{$base}/form.js"));
    }

    #[Test]
    public function what_has_no_element_to_wait_for_loads_at_once(): void
    {
        $base = $this->sync();
        $html = (string) $this->get('/at-once')->assertOk()->getContent();

        $this->assertStringContainsString("<script type=\"module\" src=\"{$base}/contacts.js\"></script>", $html);
        $this->assertStringContainsString("<script type=\"module\" src=\"{$base}/attribution.js\"></script>", $html);
        $this->assertStringNotContainsString('data-webx-on-demand', $html);
    }

    #[Test]
    public function a_piece_of_a_page_gets_its_scripts_as_tags(): void
    {
        $base = $this->sync();
        $widgets = app(Widgets::class);
        $widgets->need('slider');

        $piece = $widgets->piece('<div data-webx-slider></div>');

        $this->assertContains("<script type=\"module\" src=\"{$base}/slider.js\"></script>", $piece['body']);
        $this->assertStringNotContainsString('data-webx-on-demand', implode("\n", $piece['body']));
    }

    /** The published base of the package's files. */
    private function sync(): string
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();
        $layer = app(BottomLayers::class)->get(Widgets::NAME);
        $this->assertNotNull($layer);

        return 'http://localhost/themes/webx-ui/widgets/'.app(ThemeAssets::class)->current($layer);
    }

    /**
     * @return array<string, array{src: list<string>, on: string}>
     */
    private static function list(string $html): array
    {
        if (preg_match(self::LIST, $html, $found) !== 1) {
            return [];
        }

        /** @var array<string, array{src: list<string>, on: string}> */
        return json_decode($found[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The selectors a script passes to `webx.widget`: a string, or a constant of the file.
     *
     * @return list<string>
     */
    private static function registered(string $source): array
    {
        $constants = [];
        preg_match_all("~const ([A-Z_]+) = '([^']+)'~", $source, $found, PREG_SET_ORDER);
        foreach ($found as [, $name, $value]) {
            $constants[$name] = $value;
        }

        $selectors = [];
        preg_match_all("~webx\\.widget\\?\\.\\(\\s*'[a-z-]+',\\s*(?:'([^']+)'|([A-Z_]+))~", $source, $calls, PREG_SET_ORDER);
        foreach ($calls as $call) {
            $selector = ($call[1] ?? '') !== '' ? $call[1] : ($constants[$call[2] ?? ''] ?? '');
            foreach (explode(',', $selector) as $part) {
                $selectors[] = trim($part);
            }
        }

        return $selectors;
    }
}
