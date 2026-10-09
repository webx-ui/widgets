<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Facades\Widgets;

/**
 * Spec §7: the slider's markup without JavaScript — a strip of slides sized per view by the
 * container — the settings each variant hands the script, and the pictures that wait.
 */
final class SliderTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/slides', static fn (): string => Blade::render('<x-layout><x-webx-slider><x-webx-slide>One</x-webx-slide><x-webx-slide>Two</x-webx-slide></x-webx-slider></x-layout>'));
        $router->get('/plain', static fn (): string => Blade::render('<x-layout><p>No slider</p></x-layout>'));
    }

    #[Test]
    public function a_slider_is_a_strip_of_slides_with_its_controls_hidden_until_the_script_comes(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-webx-slider id="services" label="Our services" class="is-wide">
                <x-webx-slide>One</x-webx-slide>
                <x-webx-slide>Two</x-webx-slide>
                <x-webx-slide>Three</x-webx-slide>
            </x-webx-slider>
            BLADE);

        $this->assertMatchesRegularExpression('~<section id="services" data-webx-slider="[^"]+" aria-roledescription="carousel" aria-label="Our services" class="webx-slider webx-slider--cards is-wide">~', $html);
        $this->assertStringContainsString('<div class="webx-slider__track" id="services-track">', $html);
        $this->assertSame(3, substr_count($html, '<div role="group" aria-roledescription="slide" class="webx-slider__slide">'));
        $this->assertStringContainsString('<div class="webx-slider__controls" hidden>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-slider__button webx-slider__prev" aria-label="Previous slide" aria-controls="services-track">', $html);
        $this->assertStringContainsString('<div class="webx-slider__pagination"></div>', $html);
        // Nothing moves on its own in `cards`: no pause button.
        $this->assertStringNotContainsString('webx-slider__pause', $html);
        $this->assertContains('slider', Widgets::claimed());
    }

    #[Test]
    public function per_view_is_by_the_width_of_the_container_written_beside_the_slider(): void
    {
        $html = Blade::render('<x-webx-slider id="s"><x-webx-slide>One</x-webx-slide></x-webx-slider>');

        $this->assertStringContainsString(
            '<style>#s-track { --webx-slider-per-view: 1.2; }'
            .' @container webx-slider (min-width: 640px) { #s-track { --webx-slider-per-view: 2; } }'
            .' @container webx-slider (min-width: 960px) { #s-track { --webx-slider-per-view: 3; } }</style>',
            $html,
        );

        // A width in pixels, and one number for every width; below the narrowest given, its count.
        $this->assertStringContainsString(
            '<style>#p-track { --webx-slider-per-view: 1; } @container webx-slider (min-width: 500px) { #p-track { --webx-slider-per-view: 1.5; } }</style>',
            Blade::render('<x-webx-slider id="p" :per-view="[500 => 1.5, \'sm\' => 1]"><x-webx-slide>x</x-webx-slide></x-webx-slider>'),
        );
        $this->assertStringContainsString(
            '<style>#n-track { --webx-slider-per-view: 4; } @container webx-slider (min-width: 960px) { #n-track { --webx-slider-per-view: 5; } }</style>',
            Blade::render('<x-webx-slider id="n" :per-view="[\'lg\' => 5, 640 => 4]"><x-webx-slide>x</x-webx-slide></x-webx-slider>'),
        );
        $this->assertStringContainsString(
            '<style>#one-track { --webx-slider-per-view: 2; }</style>',
            Blade::render('<x-webx-slider id="one" per-view="2"><x-webx-slide>x</x-webx-slide></x-webx-slider>'),
        );
    }

    #[Test]
    public function each_variant_hands_the_script_its_settings_and_a_prop_overrides_any(): void
    {
        $hero = $this->config('<x-webx-slider variant="hero" id="h"><x-webx-slide>1</x-webx-slide><x-webx-slide>2</x-webx-slide></x-webx-slider>');
        $this->assertSame(['variant' => 'hero', 'effect' => 'fade', 'loop' => true, 'autoplay' => 6000, 'continuous' => false, 'thumbs' => false, 'speed' => 600], array_diff_key($hero, ['options' => 0, 'words' => 0]));
        $this->assertSame('Go to slide :index', $hero['words']['go_to']);

        $gallery = $this->config('<x-webx-slider variant="gallery"><x-webx-slide>1</x-webx-slide></x-webx-slider>');
        $this->assertTrue($gallery['thumbs']);

        $logos = $this->config('<x-webx-slider variant="logos"><x-webx-slide>1</x-webx-slide></x-webx-slider>');
        $this->assertTrue($logos['continuous']);
        $this->assertTrue($logos['loop']);

        $cards = $this->config('<x-webx-slider :loop="true" autoplay="4000" :speed="300" :options="[\'centeredSlides\' => true]"><x-webx-slide>1</x-webx-slide></x-webx-slider>');
        $this->assertTrue($cards['loop']);
        $this->assertSame(4000, $cards['autoplay']);
        $this->assertSame(300, $cards['speed']);
        $this->assertSame(['centeredSlides' => true], $cards['options']);

        // A fade shows one slide at a time whatever per view says.
        $this->assertStringContainsString(
            '<style>#f-track { --webx-slider-per-view: 1; }</style>',
            Blade::render('<x-webx-slider id="f" effect="fade" per-view="3"><x-webx-slide>x</x-webx-slide></x-webx-slider>'),
        );
    }

    #[Test]
    public function what_moves_on_its_own_has_a_pause_button(): void
    {
        $hero = Blade::render('<x-webx-slider variant="hero"><x-webx-slide>1</x-webx-slide><x-webx-slide>2</x-webx-slide></x-webx-slider>');
        $this->assertStringContainsString('<button type="button" class="webx-slider__button webx-slider__pause" aria-label="Pause">', $hero);

        // Logos: no arrows, no dots — and still a pause button.
        $logos = Blade::render('<x-webx-slider variant="logos"><x-webx-slide>1</x-webx-slide><x-webx-slide>2</x-webx-slide></x-webx-slider>');
        $this->assertStringContainsString('webx-slider__pause', $logos);
        $this->assertStringNotContainsString('webx-slider__prev', $logos);
        $this->assertStringNotContainsString('webx-slider__pagination', $logos);

        $this->assertStringNotContainsString('webx-slider__pause', Blade::render('<x-webx-slider variant="hero" :autoplay="false"><x-webx-slide>1</x-webx-slide><x-webx-slide>2</x-webx-slide></x-webx-slider>'));
    }

    #[Test]
    public function one_slide_has_nothing_to_move_and_gets_no_controls(): void
    {
        $this->assertStringNotContainsString('webx-slider__controls', Blade::render('<x-webx-slider variant="hero"><x-webx-slide>Only</x-webx-slide></x-webx-slider>'));
    }

    #[Test]
    public function only_the_first_slides_in_view_load_their_pictures_at_once(): void
    {
        $slides = implode('', array_map(
            static fn (int $i): string => "<x-webx-slide><img src=\"/{$i}.jpg\" alt=\"\"></x-webx-slide>",
            range(1, 5),
        ));
        $html = Blade::render("<x-webx-slider>{$slides}</x-webx-slider>");

        // `cards` shows up to three at the widest.
        foreach ([1, 2, 3] as $i) {
            $this->assertStringContainsString("<img src=\"/{$i}.jpg\" alt=\"\">", $html);
        }

        foreach ([4, 5] as $i) {
            $this->assertStringContainsString("<img loading=\"lazy\" src=\"/{$i}.jpg\" alt=\"\">", $html);
        }

        // A picture that chose for itself keeps its choice; a hero has one slide in view.
        $hero = Blade::render('<x-webx-slider variant="hero"><x-webx-slide><img src="/a.jpg"></x-webx-slide><x-webx-slide><img src="/b.jpg" loading="eager"></x-webx-slide><x-webx-slide><img src="/c.jpg"></x-webx-slide></x-webx-slider>');
        $this->assertStringContainsString('<img src="/a.jpg">', $hero);
        $this->assertStringContainsString('<img src="/b.jpg" loading="eager">', $hero);
        $this->assertStringContainsString('<img loading="lazy" src="/c.jpg">', $hero);
    }

    #[Test]
    public function a_slide_names_its_thumbnail(): void
    {
        $this->assertStringContainsString(
            'data-thumb="/small.jpg"',
            Blade::render('<x-webx-slider variant="gallery"><x-webx-slide thumb="/small.jpg"><img src="/big.jpg"></x-webx-slide></x-webx-slider>'),
        );
    }

    #[Test]
    public function two_sliders_given_no_id_get_two_ids_even_with_the_same_slides(): void
    {
        $one = '<x-webx-slider><x-webx-slide>Same</x-webx-slide></x-webx-slider>';

        preg_match_all('/<section[^>]* id="(webx-slider-[a-z0-9-]+)"/', Blade::render($one.$one), $ids);

        $this->assertCount(2, $ids[1]);
        $this->assertNotSame($ids[1][0], $ids[1][1]);
    }

    #[Test]
    public function the_words_follow_the_locale(): void
    {
        app()->setLocale('de');

        $html = Blade::render('<x-webx-slider><x-webx-slide>1</x-webx-slide><x-webx-slide>2</x-webx-slide></x-webx-slider>');

        $this->assertStringContainsString('aria-roledescription="Karussell"', $html);
        $this->assertStringContainsString('aria-label="Nächste Folie"', $html);
    }

    #[Test]
    public function a_page_with_a_slider_loads_its_files_and_a_page_without_one_does_not(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/slides')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]+/slider\.css">~', $html);
        $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/slider\.js"></script>~', $html);

        $this->assertStringNotContainsString('slider.js', (string) $this->get('/plain')->assertOk()->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function typos(): iterable
    {
        yield 'variant' => ['variant="carousel"'];
        yield 'effect' => ['effect="cube"'];
        yield 'breakpoint' => [':per-view="[\'huge\' => 2]"'];
        yield 'per view' => ['per-view="0"'];
        yield 'autoplay' => ['autoplay="soon"'];
        yield 'id' => ['id="a b"'];
    }

    #[Test]
    #[DataProvider('typos')]
    public function a_setting_that_does_not_exist_is_a_typo(string $attribute): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            Blade::render("<x-webx-slider {$attribute}><x-webx-slide>x</x-webx-slide></x-webx-slider>");
        } catch (\Throwable $error) {
            // Blade wraps what a component throws in a ViewException.
            throw $error->getPrevious() instanceof InvalidArgumentException ? $error->getPrevious() : $error;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function config(string $blade): array
    {
        preg_match('/data-webx-slider="([^"]+)"/', Blade::render($blade), $match);

        $config = json_decode(html_entity_decode($match[1] ?? ''), true);
        $this->assertIsArray($config);

        return $config;
    }
}
