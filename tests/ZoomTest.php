<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\View\Components\Zoom;

/**
 * Spec §14, W5.5: the zoom of a picture — a link to the original that opens the lightbox, with
 * what the script magnifies on a hover and under two fingers; `data-webx-zoom` claims it wherever
 * it was written.
 */
final class ZoomTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/component', static fn (): string => Blade::render('<x-layout><x-webx-zoom src="/small.webp" large="/big.webp" width="1800" height="1200" alt="A chair" /></x-layout>'));
        // A gallery's link, written by a block or a module's view: no component claims anything.
        $router->get('/by-hand', static fn (): string => Blade::render('<x-layout><a href="/big.webp" data-webx-lightbox="g" data-webx-zoom data-width="1800" data-height="1200"><img src="/big.webp" alt=""></a></x-layout>'));
        $router->get('/plain', static fn (): string => Blade::render('<x-layout><p>Write data-webx-zoom on a link.</p><a href="/x" data-webx-zoomed="no">x</a></x-layout>'));
    }

    #[Test]
    public function a_picture_of_the_library_is_a_link_to_its_original_that_the_script_magnifies(): void
    {
        $html = Blade::render('<x-webx-zoom :image="$picture" mode="side" scale="2" group="product-7" class="b-product__zoom" />', [
            'picture' => ['url' => '/media/chair.webp', 'thumb' => '/media/thumbs/chair.webp', 'alt' => 'A chair', 'width' => 1800, 'height' => 1200],
        ]);

        // A link to the original — the lightbox's for a keyboard, the file itself without JavaScript.
        $this->assertStringContainsString('<a href="/media/chair.webp" data-webx-zoom="/media/chair.webp" data-zoom-mode="side" data-zoom-scale="2" data-webx-lightbox="product-7" data-width="1800" data-height="1200" aria-label="Zoom: A chair" class="webx-zoom webx-zoom-link webx-zoom--side b-product__zoom">', $html);
        // The shown picture is the original too when nothing smaller is given; lazy unless told.
        $this->assertStringContainsString('<img class="webx-zoom__picture" src="/media/chair.webp" alt="A chair"  width="1800" height="1200"   loading="lazy"  decoding="async" draggable="false">', $html);
        $this->assertMatchesRegularExpression('~<span class="webx-zoom__badge" aria-hidden="true"><svg class="webx-icon webx-zoom__icon"[^>]*>.*</svg>\s*</span></a>~s', $html);
        $this->assertSame(['zoom', 'lightbox'], Widgets::claimed());
    }

    #[Test]
    public function a_smaller_copy_is_shown_and_the_original_waits_for_a_hover(): void
    {
        $photo = new class
        {
            public int $width = 1200;

            public int $height = 1600;

            public function url(): string
            {
                return '/catalog/photo.jpg';
            }
        };

        $html = Blade::render('<x-webx-zoom :image="$photo" src="/catalog/photo-1200.jpg" alt="Front" :eager="true" />', ['photo' => $photo]);

        $this->assertStringContainsString('href="/catalog/photo.jpg" data-webx-zoom="/catalog/photo.jpg" data-zoom-mode="inner" data-zoom-scale="2.5" data-webx-lightbox="" data-width="1200" data-height="1600" aria-label="Zoom: Front"', $html);
        $this->assertStringContainsString('src="/catalog/photo-1200.jpg" alt="Front"  width="1200" height="1600"   decoding="async"', $html);
        $this->assertStringNotContainsString('loading="lazy"', $html);
        // `large` over the image's address; no sizes at all — none printed.
        $bare = Blade::render('<x-webx-zoom src="/a.webp" large="/a-full.webp" />');
        $this->assertStringContainsString('href="/a-full.webp" data-webx-zoom="/a-full.webp"', $bare);
        $this->assertStringNotContainsString('data-width', $bare);
        $this->assertDoesNotMatchRegularExpression('~<img[^>]* width=~', $bare);
        $this->assertStringContainsString('aria-label="Zoom"', $bare);
    }

    #[Test]
    public function the_name_is_in_the_language_of_the_page_or_the_one_it_is_told(): void
    {
        app()->setLocale('de');
        $this->assertStringContainsString('aria-label="Vergrößern: Ein Stuhl"', Blade::render('<x-webx-zoom src="/a.webp" alt="Ein Stuhl" />'));
        app()->setLocale('ru');
        $this->assertStringContainsString('aria-label="Увеличить"', Blade::render('<x-webx-zoom src="/a.webp" />'));
        $this->assertStringContainsString('aria-label="Open the label"', Blade::render('<x-webx-zoom src="/a.webp" alt="Этикетка" label="Open the label" />'));
    }

    #[Test]
    public function the_attribute_claims_the_zoom_wherever_it_was_written_and_the_page_gets_its_files(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        foreach (['/component', '/by-hand'] as $page) {
            $html = (string) $this->get($page)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]+/zoom\.css">~', $html, $page);
            $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/zoom\.js"></script>~', $html, $page);
            // The keyboard's way in is the lightbox: it comes along.
            $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/lightbox\.js"></script>~', $html, $page);
        }

        $plain = (string) $this->get('/plain')->assertOk()->getContent();
        $this->assertStringNotContainsString('zoom.js', $plain);
        $this->assertStringNotContainsString('zoom.css', $plain);
    }

    #[Test]
    public function the_attribute_is_found_on_an_element_only(): void
    {
        $this->assertTrue(Zoom::wanted('<a href="/a.jpg" data-webx-zoom>'));
        $this->assertTrue(Zoom::wanted("<DIV\nclass='slide' data-webx-zoom='/big.jpg'>"));
        $this->assertTrue(Zoom::wanted('<img src="/a.jpg" data-webx-zoom/>'));
        $this->assertFalse(Zoom::wanted('<p>data-webx-zoom</p>'));
        $this->assertFalse(Zoom::wanted('<a data-webx-zoom-mode="side">'));
    }

    #[Test]
    public function a_typo_says_what_it_takes(): void
    {
        foreach ([
            '<x-webx-zoom />' => 'no picture',
            '<x-webx-zoom :image="[\'url\' => \'\']" />' => 'no picture',
            '<x-webx-zoom src="/a.webp" mode="lens" />' => 'inner or side',
            '<x-webx-zoom src="/a.webp" scale="10" />' => '1.25 to 6',
            '<x-webx-zoom src="/a.webp" scale="big" />' => '1.25 to 6',
        ] as $template => $message) {
            try {
                Blade::render($template);
                $this->fail("{$template} rendered.");
            } catch (Throwable $error) {
                $error = $error instanceof ViewException ? ($error->getPrevious() ?? $error) : $error;
                $this->assertInstanceOf(InvalidArgumentException::class, $error, $template);
                $this->assertStringContainsString($message, $error->getMessage(), $template);
            }
        }
    }
}
