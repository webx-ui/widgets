<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;

/**
 * A strip dragged with the mouse (§14): `data-webx-drag-scroll` claims `dist/drag-scroll.*`
 * wherever it was written — a gallery's ribbon, a module's view — and a page without it loads
 * nothing more than before.
 */
final class DragScrollTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/strip', static fn (): string => Blade::render('<x-layout><ul class="b-gallery__ribbon" role="list" tabindex="0" data-webx-drag-scroll><li>1</li></ul></x-layout>'));
        $router->get('/plain', static fn (): string => Blade::render('<x-layout><p>Write data-webx-drag-scroll on a strip.</p><div data-webx-drag-scrolled="no">x</div></x-layout>'));
    }

    #[Test]
    public function the_attribute_claims_the_drag_and_a_page_without_it_loads_nothing_more(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/strip')->assertOk()->getContent();
        $this->assertLoadsScript('drag-scroll', $html);
        $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]+/drag-scroll\.css">~', $html);

        $plain = (string) $this->get('/plain')->assertOk()->getContent();
        $this->assertStringNotContainsString('drag-scroll.js', $plain);
        $this->assertStringNotContainsString('drag-scroll.css', $plain);
    }
}
