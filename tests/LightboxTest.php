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
    public function a_link_around_a_picture_without_an_alt_is_named_for_what_it_does(): void
    {
        $picture = ['url' => '/media/plan.webp', 'thumb' => '/media/plan-320.webp', 'alt' => '', 'width' => 2560, 'height' => 1440];

        // Its place in the group, when the template says it.
        $html = Blade::render('<x-webx-lightbox :image="$picture" group="g" :index="2" :total="5"><img src="/media/plan.webp" alt=""></x-webx-lightbox>', ['picture' => $picture]);
        $this->assertStringContainsString('aria-label="Open picture 2 of 5"', $html);

        // Without it, and in the page's language.
        $this->assertStringContainsString('aria-label="Open the picture"', Blade::render('<x-webx-lightbox :image="$picture" />', ['picture' => $picture]));
        $this->app->setLocale('ru');
        $this->assertStringContainsString('aria-label="Открыть фото 1 из 3"', Blade::render('<x-webx-lightbox :image="$picture" :index="1" :total="3" />', ['picture' => $picture]));
        $this->app->setLocale('en');

        // A name of its own wins: the alt, an aria-label by hand, words or a described picture in the slot.
        $named = [
            '<x-webx-lightbox :image="$picture" alt="The plan" :index="1" :total="3" />',
            '<x-webx-lightbox :image="$picture" aria-label="Site plan" :index="1" :total="3" />',
            '<x-webx-lightbox :image="$picture" :index="1" :total="3"><span>Plan</span></x-webx-lightbox>',
            '<x-webx-lightbox :image="$picture" :index="1" :total="3"><img src="/p.webp" alt="Plan"></x-webx-lightbox>',
        ];

        foreach ($named as $template) {
            $html = Blade::render($template, ['picture' => $picture]);
            $this->assertStringNotContainsString('Open', $html, $template);
        }

        $this->assertSame(1, substr_count(Blade::render('<x-webx-lightbox :image="$picture" aria-label="Site plan" />', ['picture' => $picture]), 'aria-label='));
        // A null label from a template is no label: the link is named.
        $this->assertStringContainsString('aria-label="Open the picture"', Blade::render('<x-webx-lightbox :image="$picture" :aria-label="null" />', ['picture' => $picture]));
    }

    #[Test]
    public function the_slot_is_what_the_link_shows_and_unknown_sizes_are_left_out(): void
    {
        $html = Blade::render('<x-webx-lightbox src="/a.jpg" width="0"><span>Look</span></x-webx-lightbox>');

        $this->assertSame('<a href="/a.jpg" data-webx-lightbox="" class="webx-lightbox-link"><span>Look</span></a>', trim($html));
    }

    #[Test]
    public function a_frame_and_a_backdrop_are_said_on_the_link_and_left_out_unless_asked(): void
    {
        $html = Blade::render('<x-webx-lightbox src="/a.jpg" width="1200" height="800" ratio="4 / 3" opacity="0.9" />');
        $this->assertStringContainsString('data-width="1200" data-height="800" data-webx-lightbox-ratio="4:3" data-webx-lightbox-opacity="0.9"', $html);

        $this->assertStringContainsString('data-webx-lightbox-opacity="1"', Blade::render('<x-webx-lightbox src="/a.jpg" opacity="3" />'), 'held to 0…1');

        foreach (['', 'auto', '0:1', 'wide'] as $none) {
            $plain = Blade::render('<x-webx-lightbox src="/a.jpg" :ratio="$ratio" />', ['ratio' => $none]);
            $this->assertStringNotContainsString('data-webx-lightbox-', $plain, "'{$none}' is the picture as it is");
        }

        $this->assertSame('16:9', Lightbox::ratio('16:9'));
        $this->assertSame('1.5:1', Lightbox::ratio('1.5/1'));
        $this->assertNull(Lightbox::ratio(null));
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
            $this->assertLoadsScript('lightbox', $html, $page);
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
    public function the_script_is_told_the_windows_layout_of_the_config(): void
    {
        $layout = $this->settings()['layout'];

        $this->assertSame(768, $layout['narrow']);
        $this->assertSame(['side' => 100, 'max' => 1600, 'height' => 0.84, 'below' => false], $layout['wide']);
        $this->assertSame(['side' => 12, 'max' => 0, 'height' => 0.75, 'below' => true], $layout['phone']);

        config(['webx-widgets.lightbox.phone.below' => false]);
        $this->assertFalse($this->settings()['layout']['phone']['below']);
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
     * @return array{words: array<string, string>, icons: array<string, string>, layout: array<string, mixed>}
     */
    private function settings(): array
    {
        preg_match('~<script type="application/json" id="webx-lightbox">(.+?)</script>~s', view('webx-widgets::lightbox')->render(), $match);

        $settings = json_decode($match[1] ?? '', true);
        $this->assertIsArray($settings);

        /** @var array{words: array<string, string>, icons: array<string, string>, layout: array<string, mixed>} $settings */
        return $settings;
    }
}
