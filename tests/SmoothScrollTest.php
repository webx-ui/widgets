<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Widgets;

/**
 * Spec §14 — smooth scrolling: `data-webx-smooth-scroll` on the layout's <html> or <body> claims
 * the widget's files, which carry Lenis; a page without the switch never loads them.
 */
final class SmoothScrollTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $page = static fn (string $html, string $body = ''): string => Blade::render("<!doctype html>\n<html lang=\"en\"{$html}>\n<head>\n@webxTheme\n</head>\n<body{$body}>\n<p>Text</p>\n</body>\n</html>");

        $router->get('/on-html', static fn (): string => $page(' data-webx-smooth-scroll data-webx-smooth-scroll-duration="1.2s"'));
        $router->get('/on-body', static fn (): string => $page('', ' class="site" data-webx-smooth-scroll="on"'));
        // A setting alone is not the switch, nor are words about it.
        $router->get('/off', static fn (): string => $page(' data-webx-smooth-scroll-wheel="2"', '><p>data-webx-smooth-scroll</p'));
    }

    #[Test]
    public function the_switch_on_html_or_body_claims_the_widget_and_the_page_gets_its_files(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        foreach (['/on-html', '/on-body'] as $page) {
            $html = (string) $this->get($page)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]+/smooth-scroll\.css">~', $html, $page);
            $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/smooth-scroll\.js"></script>~', $html, $page);
            // After the runtime: it registers through `webx.widget`.
            $this->assertLessThan(strpos($html, 'smooth-scroll.js'), (int) strpos($html, 'runtime.js'), $page);
        }

        $off = (string) $this->get('/off')->assertOk()->getContent();
        $this->assertStringContainsString('runtime.js', $off);
        $this->assertStringNotContainsString('smooth-scroll', preg_replace('~data-webx-smooth-scroll[-\w]*~', '', $off) ?? '');
    }

    #[Test]
    public function the_switch_is_found_on_html_and_body_only(): void
    {
        $this->assertTrue(Widgets::smoothScroll('<html lang="en" data-webx-smooth-scroll>'));
        $this->assertTrue(Widgets::smoothScroll("<BODY\nclass='site' data-webx-smooth-scroll=''>"));
        $this->assertTrue(Widgets::smoothScroll('<html data-webx-smooth-scroll-easing="expo" data-webx-smooth-scroll>'));
        $this->assertFalse(Widgets::smoothScroll('<div data-webx-smooth-scroll>'));
        $this->assertFalse(Widgets::smoothScroll('<html data-webx-smooth-scroll-wheel="2">'));
        $this->assertFalse(Widgets::smoothScroll('<p>data-webx-smooth-scroll</p>'));
    }
}
