<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\View\Components\Lightbox;

/**
 * Spec §8: a picture that opens over the page — a link to the picture with its sizes and group,
 * claimed by the component or found on the page wherever the link was written, with the words and
 * the icons the script is told once before `</body>`.
 */
final class LightboxTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        // A link written by hand — a block, a module's view — with no component to claim anything.
        $router->get('/by-hand', static fn (): string => Blade::render('<x-layout><a href="/big.jpg" data-webx-lightbox="g" data-width="2000" data-height="1000"><img src="/small.jpg" alt="A"></a></x-layout>'));
        $router->get('/component', static fn (): string => Blade::render('<x-layout><x-webx-lightbox src="/big.jpg" width="2000" height="1000" /></x-layout>'));
        // Words about the attribute are not the attribute.
        $router->get('/plain', static fn (): string => Blade::render('<x-layout><p>Write data-webx-lightbox on a link.</p><a href="/x" data-webx-lightboxes="no">x</a></x-layout>'));
    }

    #[Test]
    public function a_picture_of_the_library_is_a_link_to_it_with_its_sizes_and_its_thumbnail_inside(): void
    {
        // What a media field of a block resolves to.
        $picture = ['url' => '/media/sea.webp', 'thumb' => '/media/sea-320.webp', 'alt' => 'The sea', 'title' => null, 'width' => 2560, 'height' => 1440];

        $html = Blade::render('<x-webx-lightbox :image="$picture" group="trip" class="is-wide" />', ['picture' => $picture]);

        $this->assertSame(
            '<a href="/media/sea.webp" data-webx-lightbox="trip" data-width="2560" data-height="1440" class="webx-lightbox-link is-wide">'
            .'<img class="webx-lightbox-link__image" src="/media/sea-320.webp" alt="The sea" loading="lazy"></a>',
            trim($html),
        );
        $this->assertContains('lightbox', Widgets::claimed());
    }

    #[Test]
    public function an_object_with_a_url_and_sizes_will_do_and_the_props_say_it_over(): void
    {
        $photo = new class
        {
            public int $width = 1200;

            public int $height = 900;

            public string $alt = 'Front';

            public function url(): string
            {
                return '/catalog/front.jpg';
            }
        };

        $html = Blade::render('<x-webx-lightbox :image="$photo" alt="Over" height="800" />', ['photo' => $photo]);

        $this->assertStringContainsString('href="/catalog/front.jpg" data-webx-lightbox="" data-width="1200" data-height="800"', $html);
        // No thumbnail: the picture itself, lazily.
        $this->assertStringContainsString('<img class="webx-lightbox-link__image" src="/catalog/front.jpg" alt="Over" loading="lazy">', $html);
    }

    #[Test]
    public function the_slot_is_what_the_link_shows_and_unknown_sizes_are_left_out(): void
    {
        $html = Blade::render('<x-webx-lightbox src="/a.jpg" width="0"><span>Look</span></x-webx-lightbox>');

        $this->assertSame('<a href="/a.jpg" data-webx-lightbox="" class="webx-lightbox-link"><span>Look</span></a>', trim($html));
    }

    #[Test]
    public function a_link_to_nothing_is_a_mistake_in_the_template(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            Blade::render('<x-webx-lightbox :image="[\'url\' => null]" />');
        } catch (\Throwable $error) {
            throw $error->getPrevious() instanceof InvalidArgumentException ? $error->getPrevious() : $error;
        }
    }

    #[Test]
    public function a_link_written_by_hand_claims_the_lightbox_and_the_page_gets_its_files_and_words(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        foreach (['/by-hand', '/component'] as $page) {
            $html = (string) $this->get($page)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]+/lightbox\.css">~', $html, $page);
            $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/lightbox\.js"></script>~', $html, $page);
            $this->assertSame(1, substr_count($html, '<script type="application/json" id="webx-lightbox">'), $page);
            // Told before the script that reads it.
            $this->assertLessThan(strpos($html, 'lightbox.js'), (int) strpos($html, 'id="webx-lightbox"'), $page);
        }

        $plain = (string) $this->get('/plain')->assertOk()->getContent();
        $this->assertStringNotContainsString('lightbox.js', $plain);
        $this->assertStringNotContainsString('id="webx-lightbox"', $plain);
    }

    #[Test]
    public function the_script_is_told_the_words_of_the_page_and_the_icons_of_the_chain(): void
    {
        app()->setLocale('de');

        $settings = $this->settings();

        $this->assertSame('Bildansicht', $settings['words']['dialog']);
        $this->assertSame('Nächstes Bild', $settings['words']['next']);
        $this->assertSame('Schließen', $settings['words']['close']);

        foreach (['prev', 'next', 'close', 'zoom'] as $icon) {
            $this->assertStringStartsWith('<svg class="webx-icon webx-lightbox__icon"', $settings['icons'][$icon]);
        }
    }

    #[Test]
    public function the_attribute_is_found_on_a_link_only(): void
    {
        $this->assertTrue(Lightbox::wanted('<a class="x" data-webx-lightbox href="/a.jpg">'));
        $this->assertTrue(Lightbox::wanted("<A\nhref='/a.jpg' data-webx-lightbox='g'>"));
        $this->assertFalse(Lightbox::wanted('<p>data-webx-lightbox</p>'));
        $this->assertFalse(Lightbox::wanted('<a data-webx-lightbox-group="g">'));
    }

    /**
     * @return array{words: array<string, string>, icons: array<string, string>}
     */
    private function settings(): array
    {
        preg_match('~<script type="application/json" id="webx-lightbox">(.+?)</script>~s', view('webx-widgets::lightbox')->render(), $match);

        $settings = json_decode($match[1] ?? '', true);
        $this->assertIsArray($settings);

        /** @var array{words: array<string, string>, icons: array<string, string>} $settings */
        return $settings;
    }
}
