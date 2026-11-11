<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\ContentValues;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The blocks `gallery`, `logos`, `video`, `map`, `counters`, `countdown`, `compare`, `toc` and `tabs` (§15.1, §14): offered to a site with blocks and a media
 * library, installed and published by `webx:blocks:offered --install` (the line `webx:setup` prints), and printed with the
 * slider, the lightbox and the video inside — pictures of the library with their sizes, one
 * group per block; a video of a provider behind the consent, a file of the library as is; a
 * map of the contacts or of its own coordinates, behind the consent too.
 */
final class BlocksTest extends TestCase
{
    use MigratesOnce;

    /** Each render a block of its own key, as on a page: k1, k2… */
    private int $rendered = 0;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
            MediaServiceProvider::class,
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['widgets']])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    #[Test]
    public function both_are_installed_and_published_on_their_samples(): void
    {
        foreach (['gallery', 'logos', 'video', 'map', 'counters', 'countdown', 'compare', 'toc', 'tabs'] as $slug) {
            $block = Block::query()->where('slug', $slug)->with('publishedVersion')->firstOrFail();

            $this->assertSame('Offered by widgets', $block->publishedVersion?->comment, "{$slug} is published — it draws on its sample");
        }
    }

    #[Test]
    public function the_grid_links_every_picture_to_the_lightbox_of_its_block(): void
    {
        $pictures = [...array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path, 'alt' => "Photo {$n}"], [1, 2, 3]), ['path' => 'media/gone.webp']];
        $pictures[0]['title'] = 'Under the first';

        $html = $this->render('gallery', ['heading' => 'Work', 'pictures' => $pictures, 'columns' => 4]);

        $this->assertStringContainsString('class="b-gallery b-gallery--grid"', $html, 'a grid unless told otherwise');
        $this->assertStringContainsString('--gallery-columns: 4', $html);
        $this->assertSame(3, substr_count($html, 'class="b-gallery__cell"'), 'a picture gone from the library is left out');
        $this->assertSame(3, substr_count($html, 'data-webx-lightbox="gallery-k1"'), 'zoom is on unless switched off, one group for the block');
        $this->assertStringContainsString('data-width="1800" data-height="1200"', $html, 'the sizes stored at upload');
        $this->assertStringContainsString('<figcaption class="b-gallery__caption">Under the first</figcaption>', $html);
        $this->assertStringContainsString('alt="Photo 2"', $html);
        $this->assertStringContainsString('<h2 class="b-gallery__heading">Work</h2>', $html);
        $this->assertContains('lightbox', Widgets::claimed());

        $plain = $this->render('gallery', ['pictures' => $pictures, 'zoom' => false]);
        $this->assertStringNotContainsString('data-webx-lightbox', $plain);
        $this->assertSame(3, substr_count($plain, 'class="b-gallery__image"'));
    }

    #[Test]
    public function the_gallery_opens_its_pictures_in_the_frame_it_was_given_and_as_they_are_by_default(): void
    {
        $pictures = array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path], [1, 2]);

        // A block saved before the field, and one that left it at "As is": the lightbox as it was.
        foreach ([['pictures' => $pictures], ['pictures' => $pictures, 'ratio' => 'auto'], ['pictures' => $pictures, 'ratio' => 'auto', 'view' => 'slider']] as $values) {
            $this->assertStringNotContainsString('data-webx-lightbox-ratio', $this->render('gallery', $values));
        }

        $this->assertSame(2, substr_count($this->render('gallery', ['pictures' => $pictures, 'ratio' => '4:3']), 'data-webx-lightbox="gallery-k4" data-width="1800" data-height="1200" data-webx-lightbox-ratio="4:3"'));
        $this->assertSame(2, substr_count($this->render('gallery', ['pictures' => $pictures, 'ratio' => '16:9', 'view' => 'slider']), 'data-webx-lightbox-ratio="16:9"'));

        // The field is offered only where a click opens the picture.
        /** @var array{schema: list<array<string, mixed>>} $type */
        $type = json_decode((string) file_get_contents(__DIR__.'/../resources/blocks/gallery.json'), true);
        /** @var array{visible: mixed, props: array{options: list<array{value: string}>}} $field */
        $field = array_values(array_filter($type['schema'], static fn (array $node): bool => ($node['id'] ?? null) === 'ratio'))[0];
        $this->assertSame(['when' => 'zoom', 'not' => false], $field['visible']);
        $this->assertSame(['auto', '4:3', '16:9', '1:1'], array_column($field['props']['options'], 'value'));
    }

    #[Test]
    public function the_slider_is_the_gallery_variant_with_a_thumbnail_and_a_link_per_slide(): void
    {
        $pictures = array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path], [1, 2, 3, 4]);

        $html = $this->render('gallery', ['heading' => 'Work', 'pictures' => $pictures, 'view' => 'slider']);

        $this->assertStringContainsString('webx-slider--gallery', $html);
        $this->assertStringContainsString('aria-label="Work"', $html, 'the slider is named by the heading');
        $this->assertSame(4, substr_count($html, 'class="webx-slider__slide"'));
        $this->assertSame(4, substr_count($html, 'data-thumb="'));
        $this->assertSame(4, substr_count($html, 'data-webx-lightbox="gallery-k1"'));
        $this->assertContains('slider', Widgets::claimed());

        // Two galleries on a page page through their own pictures.
        $this->assertStringContainsString('data-webx-lightbox="gallery-k2"', $this->render('gallery', ['pictures' => $pictures, 'view' => 'slider']));
        $this->assertStringNotContainsString('data-webx-zoom', $html, 'no magnifying unless asked');
    }

    #[Test]
    public function the_slider_magnifies_its_pictures_through_the_lightboxs_link_when_asked(): void
    {
        $pictures = array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path], [1, 2, 3]);

        $html = $this->render('gallery', ['pictures' => $pictures, 'view' => 'slider', 'magnify' => 'side']);

        // The attribute on the link: a mouse magnifies, a click and a key still open the lightbox.
        $this->assertSame(3, preg_match_all('~<a [^>]*data-webx-lightbox="gallery-k1"[^>]* data-webx-zoom="" data-zoom-mode="side">~', $html));
        $this->assertSame(1, substr_count($this->render('gallery', ['pictures' => [$pictures[0]], 'view' => 'slider', 'magnify' => 'inner']), 'data-zoom-mode="inner"'));

        // A grid's cells are too small to magnify; without the lightbox a keyboard would have no way in.
        $this->assertStringNotContainsString('data-webx-zoom', $this->render('gallery', ['pictures' => $pictures, 'magnify' => 'side']));
        $this->assertStringNotContainsString('data-webx-zoom', $this->render('gallery', ['pictures' => $pictures, 'view' => 'slider', 'magnify' => 'side', 'zoom' => false]));
    }

    #[Test]
    public function the_slider_takes_its_settings_and_a_block_saved_before_them_stays_as_it_was(): void
    {
        $pictures = array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path], [1, 2, 3, 4]);
        $config = function (array $values) use ($pictures): array {
            preg_match('/data-webx-slider="([^"]+)"/', $this->render('gallery', ['pictures' => $pictures, 'view' => 'slider'] + $values), $match);
            $config = json_decode(html_entity_decode($match[1] ?? ''), true);
            $this->assertIsArray($config);

            return $config;
        };
        $html = fn (array $values): string => $this->render('gallery', ['pictures' => $pictures, 'view' => 'slider'] + $values);

        // Saved before the fields, and saved with every field at its default: the slider it always was.
        $old = $config([]);
        $this->assertSame(['effect' => 'slide', 'loop' => false, 'autoplay' => 0, 'thumbs' => true], array_intersect_key($old, array_flip(['effect', 'loop', 'autoplay', 'thumbs'])));
        $this->assertSame($old, $config(['per_view' => 1, 'effect' => 'slide', 'arrows' => true, 'dots' => false, 'thumbs' => true, 'endless' => false, 'autoplay' => false, 'interval' => 5]));
        $this->assertStringContainsString('webx-slider__prev', $html([]));
        $this->assertStringNotContainsString('webx-slider__pagination', $html([]));
        $this->assertStringNotContainsString('webx-slider__pause', $html([]), 'nothing moves on its own');
        $this->assertStringNotContainsString('b-gallery--row', $html([]));
        $this->assertStringContainsString('--webx-slider-per-view: 1;', $html([]));

        // Turning on its own, every five seconds unless told otherwise, with a pause button; dots, no arrows, around.
        $moving = $config(['autoplay' => true, 'dots' => true, 'arrows' => false, 'endless' => true, 'effect' => 'fade']);
        $this->assertSame(['effect' => 'fade', 'loop' => true, 'autoplay' => 5000], array_intersect_key($moving, array_flip(['effect', 'loop', 'autoplay'])));
        $this->assertSame(8000, $config(['autoplay' => true, 'interval' => 8])['autoplay']);
        $this->assertSame(2000, $config(['autoplay' => true, 'interval' => 1])['autoplay'], 'no faster than two seconds');
        $this->assertSame(0, $config(['autoplay' => false, 'interval' => 8])['autoplay'], 'an interval without autoplay moves nothing');
        $shown = $html(['autoplay' => true, 'dots' => true, 'arrows' => false]);
        $this->assertStringContainsString('webx-slider__pause', $shown);
        $this->assertStringContainsString('webx-slider__pagination', $shown);
        $this->assertStringNotContainsString('webx-slider__prev', $shown);

        // Three at a time: by the width of the column, no thumbnails, no fade, every slide still opens the lightbox.
        $three = $html(['per_view' => 3, 'arrows' => false, 'effect' => 'fade', 'thumbs' => true]);
        $this->assertStringContainsString('b-gallery--slider b-gallery--row', $three);
        $this->assertStringContainsString('--webx-slider-per-view: 1;', $three);
        $this->assertStringContainsString('@container webx-slider (min-width: 640px) { #', $three);
        $this->assertMatchesRegularExpression('/min-width: 960px\) \{ #[^ ]+ \{ --webx-slider-per-view: 3; \}/', $three);
        $this->assertSame(4, preg_match_all('/data-webx-lightbox="gallery-k\d+"/', $three));
        $this->assertSame(['effect' => 'slide', 'thumbs' => false], array_intersect_key($config(['per_view' => 3, 'effect' => 'fade', 'thumbs' => true]), array_flip(['effect', 'thumbs'])));
        $this->assertStringContainsString('--webx-slider-per-view: 1;', $html(['per_view' => 7]), 'an unknown count is one');

        // The settings are offered only to a slider, the interval only to autoplay.
        /** @var array{schema: list<array{id: string, visible?: mixed}>} $type */
        $type = json_decode((string) file_get_contents(__DIR__.'/../resources/blocks/gallery.json'), true);
        $visible = array_column($type['schema'], 'visible', 'id');
        $this->assertSame(['when' => 'view', 'is' => 'slider'], $visible['per_view']);
        // The carousel of the mosaic turns by the same switches.
        $turning = ['any' => [['when' => 'view', 'is' => 'slider'], ['all' => [['when' => 'view', 'is' => 'mosaic'], ['when' => 'mosaic', 'is' => 'slider']]]]];
        foreach (['arrows', 'dots', 'endless', 'autoplay'] as $id) {
            $this->assertSame($turning, $visible[$id], $id);
        }
        $this->assertSame(['all' => [$turning, ['when' => 'autoplay', 'is' => true]]], $visible['interval']);
        $this->assertSame(['all' => [['when' => 'view', 'is' => 'slider'], ['when' => 'per_view', 'is' => 1]]], $visible['effect']);
    }

    #[Test]
    public function the_mosaic_shows_three_and_the_lightbox_pages_through_them_all(): void
    {
        $pictures = array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path, 'alt' => "Photo {$n}"], [1, 2, 3, 4, 5, 6, 7]);
        $pictures[1]['title'] = 'Under the second';

        $html = $this->render('gallery', ['heading' => 'Work', 'pictures' => $pictures, 'view' => 'mosaic', 'ratio' => '4:3']);

        $this->assertStringContainsString('class="b-gallery b-gallery--mosaic"', $html);
        $this->assertSame(1, substr_count($html, 'class="b-gallery__mosaic b-gallery__mosaic--3"'), 'still unless told otherwise: one group of three');
        $this->assertSame(3, substr_count($html, 'class="b-gallery__tile"'));
        $this->assertSame(3, substr_count($html, 'class="b-gallery__image"'));
        $this->assertStringNotContainsString('webx-slider', $html);
        $this->assertStringContainsString('<figcaption class="b-gallery__caption">Under the second</figcaption>', $html);
        // The four not shown are links without a picture in a hidden box: in the lightbox's group, never loaded, never in the way of Tab.
        $this->assertSame(7, preg_match_all('~<a [^>]*data-webx-lightbox="gallery-k1"[^>]*data-webx-lightbox-ratio="4:3"~', $html), 'the frame of the field');
        $this->assertMatchesRegularExpression('~<div class="b-gallery__unseen" hidden>(?:(?!<img).)*?aria-label="Photo 4"~s', $html);
        $this->assertSame(1, preg_match_all('~<div class="b-gallery__unseen" hidden>(?:(?!</div>).)*</div>~s', $html, $match));
        $this->assertStringNotContainsString('<img', $match[0][0]);

        // Without the lightbox nothing is printed past the third.
        $plain = $this->render('gallery', ['pictures' => $pictures, 'view' => 'mosaic', 'zoom' => false]);
        $this->assertStringNotContainsString('data-webx-lightbox', $plain);
        $this->assertStringNotContainsString('b-gallery__unseen', $plain);

        // Fewer than three: as many as there are, the shape says how many.
        $this->assertStringContainsString('b-gallery__mosaic--2', $this->render('gallery', ['pictures' => array_slice($pictures, 0, 2), 'view' => 'mosaic']));
        $this->assertStringContainsString('b-gallery__mosaic--1', $this->render('gallery', ['pictures' => array_slice($pictures, 0, 1), 'view' => 'mosaic', 'mosaic' => 'slider']));
    }

    #[Test]
    public function the_mosaic_carousel_pages_through_threes_with_the_switches_of_the_slider(): void
    {
        $pictures = array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path], [1, 2, 3, 4, 5, 6, 7]);
        $html = fn (array $values): string => $this->render('gallery', ['heading' => 'Work', 'pictures' => $pictures, 'view' => 'mosaic', 'mosaic' => 'slider'] + $values);

        $seven = $html([]);
        $this->assertStringContainsString('class="b-gallery b-gallery--mosaic"', $seven);
        $this->assertStringContainsString('webx-slider--gallery', $seven);
        $this->assertStringContainsString('aria-label="Work"', $seven);
        $this->assertSame(3, substr_count($seven, 'class="webx-slider__slide"'), 'seven make three groups');
        $this->assertSame(2, substr_count($seven, 'b-gallery__mosaic--3'));
        $this->assertSame(1, substr_count($seven, 'b-gallery__mosaic--1'), 'the last group holds what is left');
        $this->assertSame(7, substr_count($seven, 'data-webx-lightbox="gallery-k1"'), 'one group for the lightbox across the slides');
        $this->assertStringNotContainsString('b-gallery__unseen', $seven, 'every picture is on a slide');
        $this->assertStringNotContainsString('data-thumb', $seven, 'no thumbnails under a mosaic');

        preg_match('/data-webx-slider="([^"]+)"/', $seven, $config);
        $config = json_decode(html_entity_decode($config[1] ?? ''), true);
        $this->assertIsArray($config);
        $this->assertSame(['effect' => 'slide', 'loop' => false, 'autoplay' => 0, 'thumbs' => false], array_intersect_key($config, array_flip(['effect', 'loop', 'autoplay', 'thumbs'])));
        $this->assertStringContainsString('webx-slider__prev', $seven, 'arrows unless switched off');
        $this->assertStringContainsString('--webx-slider-per-view: 1;', $seven);

        $moving = $html(['autoplay' => true, 'interval' => 8, 'dots' => true, 'arrows' => false, 'endless' => true]);
        $this->assertStringContainsString('webx-slider__pause', $moving);
        $this->assertStringContainsString('webx-slider__pagination', $moving);
        $this->assertStringNotContainsString('webx-slider__prev', $moving);
        $this->assertStringContainsString('&quot;autoplay&quot;:8000', $moving);

        // Three or fewer have nothing to page through: the still mosaic.
        $three = $this->render('gallery', ['pictures' => array_slice($pictures, 0, 3), 'view' => 'mosaic', 'mosaic' => 'slider']);
        $this->assertStringNotContainsString('webx-slider', $three);
        $this->assertStringContainsString('b-gallery__mosaic--3', $three);
    }

    #[Test]
    public function the_old_views_print_as_they_did_and_the_mosaic_is_offered_beside_them(): void
    {
        $pictures = array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path], [1, 2, 3, 4]);

        foreach ([[], ['view' => 'grid'], ['view' => 'slider'], ['view' => 'grid', 'mosaic' => 'slider'], ['view' => 'slider', 'mosaic' => 'slider']] as $values) {
            $html = $this->render('gallery', ['pictures' => $pictures] + $values);
            $this->assertStringNotContainsString('b-gallery__mosaic', $html);
            $this->assertStringNotContainsString('b-gallery__unseen', $html);
            $this->assertStringContainsString(($values['view'] ?? 'grid') === 'slider' ? 'b-gallery--slider' : 'b-gallery--grid', $html);
        }

        /** @var array{schema: list<array<string, mixed>>, sample: array<string, mixed>} $type */
        $type = json_decode((string) file_get_contents(__DIR__.'/../resources/blocks/gallery.json'), true);
        $nodes = array_column($type['schema'], null, 'id');
        $this->assertSame(['grid', 'slider', 'mosaic'], array_column($nodes['view']['props']['options'], 'value'));
        $this->assertSame('grid', $nodes['view']['default']);
        $this->assertSame('still', $nodes['mosaic']['default']);
        $this->assertSame(['still', 'slider'], array_column($nodes['mosaic']['props']['options'], 'value'));
        $this->assertSame(['when' => 'view', 'is' => 'mosaic'], $nodes['mosaic']['visible']);
        $this->assertSame('still', $type['sample']['mosaic']);
    }

    #[Test]
    public function a_gallery_with_nothing_to_show_prints_nothing(): void
    {
        $this->assertStringNotContainsString('b-gallery', $this->render('gallery', ['heading' => 'Work', 'pictures' => []]));
    }

    #[Test]
    public function the_logos_run_in_the_strip_each_a_link_when_it_has_one(): void
    {
        $logo = $this->picture('logo-1', 'image/svg+xml');

        $html = $this->render('logos', ['heading' => 'Clients', 'logos' => [
            ['name' => 'Strategy Co.', 'logo' => ['path' => $logo->path], 'link' => ['target' => 'url', 'url' => 'https://strategy.example', 'new_tab' => true]],
            ['name' => 'Design Co.', 'logo' => null, 'link' => null],
            ['name' => '', 'logo' => ['path' => $logo->path, 'alt' => 'From the library'], 'link' => null],
            ['name' => '  ', 'logo' => null, 'link' => null],
        ]]);

        $this->assertStringContainsString('webx-slider--logos', $html);
        $this->assertStringContainsString('webx-slider__pause', $html, 'it moves on its own, so it has a pause button');
        $this->assertSame(3, substr_count($html, 'class="webx-slider__slide"'), 'a row with nothing to show is left out');
        $this->assertMatchesRegularExpression('#<a class="b-logos__item" href="https://strategy.example"\s+target="_blank"\s+rel="noopener noreferrer"\s*>#', $html);
        $this->assertStringContainsString('alt="Strategy Co."', $html);
        $this->assertStringContainsString('<span class="b-logos__item">', $html);
        $this->assertStringContainsString('<span class="b-logos__name">Design Co.</span>', $html);
        $this->assertStringContainsString('alt="From the library"', $html, 'no name: the picture says it');
    }

    #[Test]
    public function a_link_is_the_video_of_its_provider_waiting_for_consent_with_the_caption_under_it(): void
    {
        $poster = $this->picture('poster');

        $html = $this->render('video', [
            'heading' => 'Our workshop',
            'link' => 'https://youtu.be/aqz-KE-bpKQ',
            'poster' => ['path' => $poster->path],
            'caption' => 'Two minutes in the workshop',
            'ratio' => '4/3',
        ]);

        $this->assertStringContainsString('class="b-video b-video--4-3"', $html);
        $this->assertStringContainsString('<h2 class="b-video__heading">Our workshop</h2>', $html);
        $this->assertStringContainsString('webx-video--youtube', $html, 'a link unless the switch says file');
        $this->assertStringContainsString('is-blocked', $html, 'no answer yet: the placeholder of §9.4');
        $this->assertStringContainsString('--webx-video-ratio: 4 / 3', $html);
        $this->assertStringContainsString('aria-label="Play: Our workshop"', $html, 'the video is named by the heading');
        $this->assertMatchesRegularExpression('#class="webx-video__poster" src="[^"]*/'.preg_quote($poster->path, '#').'[^"]*"#', $html, 'the poster of the library');
        $this->assertMatchesRegularExpression('#</div>\s*<figcaption class="b-video__caption">Two minutes in the workshop</figcaption>\s*</figure>#', $html, 'the caption under the frame');
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertContains('video', Widgets::claimed());

        // Vimeo too, and a shape the field does not offer is the default.
        $vimeo = $this->render('video', ['link' => 'https://vimeo.com/1084537', 'poster' => ['path' => $poster->path], 'ratio' => '21/9']);
        $this->assertStringContainsString('webx-video--vimeo', $vimeo);
        $this->assertStringContainsString('class="b-video b-video--16-9"', $vimeo);
        $this->assertStringNotContainsString('b-video__heading', $vimeo);
        $this->assertStringNotContainsString('b-video__caption', $vimeo);
    }

    #[Test]
    public function a_file_of_the_library_plays_on_the_site_with_no_consent_asked(): void
    {
        $clip = $this->picture('clip', 'video/mp4');
        $poster = $this->picture('poster');

        $html = $this->render('video', ['source' => 'file', 'link' => 'https://youtu.be/aqz-KE-bpKQ', 'file' => ['path' => $clip->path], 'poster' => ['path' => $poster->path]]);

        $this->assertStringContainsString('webx-video--file', $html, 'the switch decides, not whichever field is filled');
        $this->assertStringContainsString('preload="none"', $html);
        $this->assertMatchesRegularExpression('#<source src="[^"]*/'.preg_quote($clip->path, '#').'[^"]*"\s+type="video/mp4"\s*>#', $html);
        $this->assertMatchesRegularExpression('#poster="[^"]*/'.preg_quote($poster->path, '#').'[^"]*"#', $html);
        $this->assertStringNotContainsString('data-webx-consent', $html);
        $this->assertStringNotContainsString('youtube', $html);
    }

    #[Test]
    public function a_video_with_nothing_to_play_prints_nothing(): void
    {
        Http::fake();

        $nothing = [
            'no address' => ['heading' => 'Video', 'link' => ''],
            'an address of no provider' => ['heading' => 'Video', 'link' => 'https://example.com/clip'],
            'not an address' => ['heading' => 'Video', 'link' => 'our film'],
            'a file gone from the library' => ['heading' => 'Video', 'source' => 'file', 'file' => ['path' => 'media/gone.mp4']],
            'the switch on file, a link filled' => ['heading' => 'Video', 'source' => 'file', 'link' => 'https://youtu.be/aqz-KE-bpKQ'],
        ];

        foreach ($nothing as $case => $values) {
            $this->assertStringNotContainsString('b-video', $this->render('video', $values), $case);
        }

        // A poster gone from the library is no poster: the video's own preview stands in.
        $html = $this->render('video', ['link' => 'https://youtu.be/aqz-KE-bpKQ', 'poster' => ['path' => 'media/gone.webp']]);
        $this->assertStringContainsString('webx-video--youtube', $html);
        $this->assertStringNotContainsString('gone.webp', $html);
        Http::assertNothingSent();
    }

    #[Test]
    public function the_map_of_the_contacts_is_their_main_address_waiting_for_consent(): void
    {
        app(Settings::class)->save(['contacts.addresses' => [
            ['address' => ['en' => '1 Example Street, Sample City'], 'latitude' => 12.34, 'longitude' => -34.56, 'primary' => true],
        ]]);

        // A block saved before the fields had values: the contacts, zoom 15, medium.
        $html = $this->render('map', ['heading' => 'Find us']);

        $this->assertStringContainsString('<section class="b-map" data-wx-block="map">', $html);
        $this->assertStringContainsString('<h2 class="b-map__heading">Find us</h2>', $html);
        $this->assertStringContainsString('style="--webx-map-height: 24em; --webx-map-height-narrow: 24em" class="webx-map is-blocked"', $html);
        $this->assertStringContainsString('<p class="webx-map__address">1 Example Street, Sample City</p>', $html);
        $this->assertStringContainsString('&quot;lat&quot;:12.34,&quot;lng&quot;:-34.56,&quot;zoom&quot;:15', $html);
        $this->assertStringContainsString('webx-map__attribution', $html);
        $this->assertContains('map', Widgets::claimed());

        $tall = $this->render('map', ['source' => 'settings', 'zoom' => 18, 'height' => 'large', 'latitude' => 1, 'longitude' => 1]);
        $this->assertStringContainsString('--webx-map-height: 32em', $tall);
        $this->assertStringContainsString('&quot;lat&quot;:12.34,&quot;lng&quot;:-34.56,&quot;zoom&quot;:18', $tall, 'the switch decides, not whichever field is filled');
        $this->assertStringNotContainsString('b-map__heading', $tall);
    }

    #[Test]
    public function the_map_of_its_own_coordinates_has_its_own_address(): void
    {
        $html = $this->render('map', ['source' => 'coordinates', 'latitude' => 48.8584, 'longitude' => 2.2945, 'address' => 'Champ de Mars, Paris', 'zoom' => 40, 'height' => 'small']);

        $this->assertStringContainsString('--webx-map-height: 16em', $html);
        $this->assertStringContainsString('aria-label="Map: Champ de Mars, Paris"', $html);
        $this->assertStringContainsString('<p class="webx-map__address">Champ de Mars, Paris</p>', $html);
        $this->assertStringContainsString('&quot;lat&quot;:48.8584,&quot;lng&quot;:2.2945,&quot;zoom&quot;:19', $html, 'a zoom past the field is held to it');
        $this->assertStringContainsString('&quot;marker&quot;:&quot;Champ de Mars, Paris&quot;', $html);

        // No address: a pin with no label, and the map named a map.
        $bare = $this->render('map', ['source' => 'coordinates', 'latitude' => 48.8584, 'longitude' => 2.2945]);
        $this->assertStringContainsString('aria-label="Map: Map"', $bare);
        $this->assertStringContainsString('&quot;marker&quot;:&quot;&quot;', $bare);
        $this->assertStringNotContainsString('webx-map__address', $bare);
    }

    #[Test]
    public function the_map_of_googles_code_is_its_frame_waiting_for_consent(): void
    {
        $src = 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d2483.0!2d-0.128!3d51.508!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2suk!4v1700000000000';
        $html = $this->render('map', ['heading' => 'Our location', 'source' => 'embed', 'embed' => $src, 'address' => 'Trafalgar Square, London', 'title' => 'Map of the square', 'height' => 'large', 'height_narrow' => 'small', 'width' => 'bleed', 'zoom' => 3]);

        $this->assertStringContainsString('<section class="b-map site-bleed" data-wx-block="map">', $html);
        $this->assertStringContainsString('style="--webx-map-height: 32em; --webx-map-height-narrow: 16em" class="webx-map webx-map--embed is-blocked"', $html);
        $this->assertStringContainsString('aria-label="Map: Map of the square"', $html);
        $this->assertStringContainsString('<p class="webx-map__address">Trafalgar Square, London</p>', $html);
        $this->assertStringContainsString('query=51.508,-0.128', $html, 'Open in Google Maps, read from the code');
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertContains('map-embed', Widgets::claimed());

        $linked = $this->render('map', ['source' => 'embed', 'embed' => $src, 'link' => 'https://maps.app.goo.gl/AbCdEf123']);
        $this->assertStringContainsString('<section class="b-map" data-wx-block="map">', $linked);
        $this->assertStringContainsString('href="https://maps.app.goo.gl/AbCdEf123"', $linked);
        $this->assertStringContainsString('--webx-map-height: 24em; --webx-map-height-narrow: 24em', $linked, 'the same height on a phone unless told');
    }

    #[Test]
    public function show_the_map_without_consent_is_the_blocks_and_off_unless_turned_on(): void
    {
        $src = 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d2483.0!2d-0.128!3d51.508!5e0';
        /** @var array{schema: list<array<string, mixed>>} $type */
        $type = json_decode((string) file_get_contents(dirname(__DIR__).'/resources/blocks/map.json'), true, flags: JSON_THROW_ON_ERROR);
        $schema = array_values(array_filter($type['schema'], static fn (array $node): bool => ($node['id'] ?? null) === 'at_once'))[0] ?? [];
        $this->assertSame('wx-switch', $schema['type'] ?? null);
        $this->assertFalse($schema['default'] ?? null, 'off for a new block');
        $this->assertSame('trans::webx-widgets::panel.map.at-once-help', $schema['help'] ?? null, 'the warning, in the panel\'s language');

        // Saved before the switch, and off: no frame before consent.
        foreach ([['source' => 'embed', 'embed' => $src], ['source' => 'embed', 'embed' => $src, 'at_once' => false]] as $values) {
            $waiting = $this->render('map', $values);
            $this->assertStringNotContainsString('<iframe', $waiting);
            $this->assertStringContainsString('data-webx-consent="media"', $waiting);
            $this->assertStringContainsString('data-webx-map-load', $waiting);
        }

        $free = $this->render('map', ['source' => 'embed', 'embed' => $src, 'at_once' => true, 'title' => 'Map of the square']);
        $this->assertStringContainsString('<iframe class="webx-map__frame" src="'.e($src).'" title="Map of the square" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>', $free, 'the frame in the HTML at once');
        $this->assertStringContainsString('class="webx-map webx-map--embed is-loaded"', $free);
        $this->assertStringNotContainsString('data-webx-consent', $free);
        $this->assertStringNotContainsString('data-webx-map-load', $free, 'no notice, no buttons');

        // A map of coordinates is told the same: it loads without the notice.
        $tiles = $this->render('map', ['source' => 'coordinates', 'latitude' => 48.8556, 'longitude' => 2.2986, 'at_once' => true]);
        $this->assertStringNotContainsString('data-webx-consent', $tiles);
        $this->assertStringContainsString('&quot;consent&quot;:false', $tiles);
    }

    #[Test]
    public function the_field_of_googles_code_keeps_the_address_and_refuses_another_sites_frame(): void
    {
        $src = 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d2483.0!2d-0.128!3d51.508!5e0';
        $code = '<iframe src="'.htmlspecialchars($src).'" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';

        $kept = $this->app->make(ContentValues::class)->store([['key' => 'k-map', 'type' => 'map', 'values' => ['source' => 'embed', 'embed' => $code]]]);
        $this->assertSame($src, $kept[0]['values']['embed'], 'the address, never the HTML');

        foreach (['<iframe src="https://maps.example.com/embed?pb=1"></iframe>', $code.'<script>alert(1)</script>', 'https://www.google.com/maps/embed/v1/place?key=AIza&q=x'] as $pasted) {
            try {
                $this->app->make(ContentValues::class)->store([['key' => 'k-map', 'type' => 'map', 'values' => ['source' => 'embed', 'embed' => $pasted]]]);
                $this->fail("Kept: {$pasted}");
            } catch (ValidationException $refused) {
                $this->assertStringContainsString('Embed a map', implode(' ', array_merge(...array_values($refused->errors()))), $pasted);
            }
        }
    }

    #[Test]
    public function a_map_with_nowhere_to_show_prints_nothing(): void
    {
        $nothing = [
            'empty contacts' => ['heading' => 'Map'],
            'no coordinates' => ['heading' => 'Map', 'source' => 'coordinates', 'address' => 'Sample City'],
            'a latitude only' => ['heading' => 'Map', 'source' => 'coordinates', 'latitude' => 51.5],
            'off the earth' => ['heading' => 'Map', 'source' => 'coordinates', 'latitude' => 120, 'longitude' => 0],
            'not numbers' => ['heading' => 'Map', 'source' => 'coordinates', 'latitude' => 'north', 'longitude' => 'west'],
            'no code' => ['heading' => 'Map', 'source' => 'embed'],
            'a code of another site, written before the field checked it' => ['heading' => 'Map', 'source' => 'embed', 'embed' => '<iframe src="https://maps.example.com/embed"></iframe>'],
        ];

        foreach ($nothing as $case => $values) {
            $this->assertStringNotContainsString('b-map', $this->render('map', $values), $case);
        }
    }

    #[Test]
    public function the_counters_count_each_number_with_its_words_and_skip_a_row_without_one(): void
    {
        $html = $this->render('counters', ['heading' => 'In numbers', 'numbers' => [
            ['value' => '15', 'prefix' => '', 'suffix' => ' years', 'label' => 'on the market'],
            ['value' => '3,000', 'prefix' => '', 'suffix' => '+', 'label' => 'clients'],
            ['value' => '4,9', 'label' => 'average rating'],
            ['value' => '12 500', 'prefix' => '$', 'label' => 'saved'],
            ['value' => 'many', 'label' => 'not a number'],
            ['value' => '', 'label' => 'empty'],
        ]]);

        $this->assertStringContainsString('<section class="b-counters" data-wx-block="counters">', $html);
        $this->assertStringContainsString('<h2 class="b-counters__heading">In numbers</h2>', $html);
        $this->assertSame(4, substr_count($html, '<li class="b-counters__item">'));
        $this->assertStringContainsString('<span class="webx-counter__number">15</span><span class="webx-counter__suffix"> years</span>', $html);
        $this->assertStringContainsString('{&quot;value&quot;:3000,&quot;decimals&quot;:0,&quot;duration&quot;:2000}"><span class="webx-counter__number">3,000</span><span class="webx-counter__suffix">+</span>', $html, '3,000 is three thousand');
        $this->assertStringContainsString('<span class="webx-counter__number">4.9</span>', $html, '4,9 is four point nine');
        $this->assertStringContainsString('<span class="webx-counter__prefix">$</span><span class="webx-counter__number">12,500</span>', $html);
        $this->assertStringContainsString('<span class="b-counters__label">average rating</span>', $html);
        $this->assertStringNotContainsString('not a number', $html);
        $this->assertContains('counter', Widgets::claimed());

        $this->assertStringNotContainsString('b-counters', $this->render('counters', ['heading' => 'Nothing', 'numbers' => [['value' => 'x']]]));
        $this->assertStringNotContainsString('b-counters', $this->render('counters', ['heading' => 'Nothing']));
    }

    #[Test]
    public function the_countdown_runs_to_a_wall_time_of_the_site_zone_from_the_contacts(): void
    {
        app(Settings::class)->save(['contacts.timezone' => 'Asia/Tokyo']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-30 09:00', 'UTC'));

        try {
            $html = $this->render('countdown', ['heading' => 'The sale ends in', 'text' => 'Everything at half price.', 'date' => '2026-12-31', 'time' => '18:00', 'end' => 'text', 'ended' => 'The sale is over']);

            $this->assertStringContainsString('<section class="b-countdown" data-wx-block="countdown" data-webx-countdown-scope>', $html);
            $this->assertStringContainsString('<p class="b-countdown__text">Everything at half price.</p>', $html);
            // 18:00 in Tokyo is 09:00 UTC: one day exactly from now.
            $this->assertStringContainsString('<time datetime="2026-12-31T18:00:00+09:00">', $html);
            $this->assertStringContainsString('{&quot;end&quot;:1798707600000}', $html);
            $this->assertStringContainsString('data-unit="days">1</span>', $html);
            $this->assertStringContainsString('data-unit="hours">00</span>', $html);
            $this->assertStringContainsString('<p class="webx-countdown__ended"  hidden >The sale is over</p>', $html);
            $this->assertContains('countdown', Widgets::claimed());

            // No time: midnight. "Hide the block": no text, whatever the field says.
            $midnight = $this->render('countdown', ['date' => '2026-12-31', 'end' => 'hide', 'ended' => 'Ignored']);
            $this->assertStringContainsString('<time datetime="2026-12-31T00:00:00+09:00">', $midnight);
            $this->assertStringNotContainsString('Ignored', $midnight);
            $this->assertStringNotContainsString('b-countdown__heading', $midnight);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function a_countdown_that_is_over_says_its_text_or_is_not_there(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-02-01 00:00', 'UTC'));

        try {
            $said = $this->render('countdown', ['heading' => 'The sale', 'date' => '2027-01-01', 'time' => '18:00', 'ended' => 'The sale is over']);
            $this->assertStringContainsString('<h2 class="b-countdown__heading">The sale</h2>', $said);
            $this->assertStringContainsString('<p class="webx-countdown__ended" >The sale is over</p>', $said);
            $this->assertStringNotContainsString('webx-countdown__units', $said);

            $nothing = [
                'hidden at the end' => ['heading' => 'Sale', 'date' => '2027-01-01', 'end' => 'hide'],
                'nothing to say' => ['heading' => 'Sale', 'date' => '2027-01-01', 'ended' => ''],
                'no date' => ['heading' => 'Sale', 'ended' => 'Over'],
                'not a date' => ['heading' => 'Sale', 'date' => 'soon', 'ended' => 'Over'],
                'not a day' => ['heading' => 'Sale', 'date' => '2027-02-31', 'ended' => 'Over'],
            ];

            foreach ($nothing as $case => $values) {
                $this->assertStringNotContainsString('b-countdown', $this->render('countdown', $values), $case);
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function before_and_after_are_two_pictures_of_the_library_with_their_sizes_and_labels(): void
    {
        $old = $this->picture('old');
        $new = $this->picture('new');

        $html = $this->render('compare', ['heading' => 'The kitchen', 'before' => ['path' => $old->path, 'alt' => '2019'], 'after' => ['path' => $new->path], 'after_label' => 'Now', 'start' => 30, 'caption' => 'Two weeks apart']);

        $this->assertStringContainsString('<section class="b-compare" data-wx-block="compare">', $html);
        $this->assertStringContainsString('<h2 class="b-compare__heading">The kitchen</h2>', $html);
        $this->assertStringContainsString('<figure style="--webx-compare-ratio: 1800 / 1200; --webx-compare-position: 30%" class="webx-compare b-compare__compare" data-webx-compare>', $html);
        $this->assertMatchesRegularExpression('#src="[^"]*/'.preg_quote($old->path, '#').'[^"]*" alt="2019"\s+width="1800" height="1200"#', $html);
        $this->assertStringContainsString('webx-compare__label--before">Before</span>', $html, 'an empty label is the word of the page');
        $this->assertStringContainsString('webx-compare__label--after">Now</span>', $html);
        $this->assertStringContainsString('<figcaption class="webx-compare__caption">Two weeks apart</figcaption>', $html);
        $this->assertContains('compare', Widgets::claimed());

        // The middle when the field was never saved; one picture alone compares nothing.
        $this->assertStringContainsString('--webx-compare-position: 50%', $this->render('compare', ['before' => ['path' => $old->path], 'after' => ['path' => $new->path]]));

        foreach ([['before' => ['path' => $old->path]], ['before' => ['path' => $old->path], 'after' => ['path' => 'media/gone.webp']], []] as $case => $values) {
            $this->assertStringNotContainsString('b-compare', $this->render('compare', $values), "case {$case}");
        }
    }

    #[Test]
    public function the_contents_list_the_page_where_the_block_stands_or_stand_beside_a_text_of_their_own(): void
    {
        // The list is made once the page is finished: here, in the block, its marker.
        $page = $this->render('toc', ['heading' => 'In this guide', 'depth' => '2']);
        $this->assertStringContainsString('<section class="b-toc" data-wx-block="toc">', $page);
        $this->assertStringContainsString('<div class="webx-toc__layout webx-toc__layout--alone">', $page);
        $this->assertSame(['from' => '<main>', 'depth' => 2, 'title' => 'In this guide', 'beside' => false], $this->marker($page));
        $this->assertContains('toc', Widgets::claimed());

        $text = $this->render('toc', ['source' => 'text', 'body' => '<h2>Who we are</h2><p>…</p>', 'side' => 'start']);
        $this->assertStringContainsString('<section class="b-toc b-toc--text" data-wx-block="toc">', $text);
        $this->assertStringContainsString('<div class="webx-toc__layout webx-toc__layout--start">', $text);
        $this->assertStringContainsString('<div class="b-toc__body"><h2>Who we are</h2><p>…</p></div>', $text);
        $settings = $this->marker($text);
        $this->assertMatchesRegularExpression('~^webx-toc-\d+-content$~', (string) $settings['from'], 'its own slot');
        $this->assertSame(['depth' => 3, 'title' => 'On this page', 'beside' => true], array_diff_key($settings, ['from' => true]));

        // A text of its own with nothing in it: no block.
        $this->assertStringNotContainsString('b-toc', $this->render('toc', ['source' => 'text', 'body' => '  ']));
    }

    #[Test]
    public function the_tabs_are_panels_of_the_tabs_component_with_a_picture_and_a_text_each(): void
    {
        $photo = $this->picture('room');
        $tabs = [
            ['title' => 'Overview', 'picture' => ['path' => $photo->path, 'alt' => 'The room'], 'text' => '<p>Bright and quiet.</p>'],
            ['title' => 'Plan', 'picture' => ['path' => $photo->path], 'text' => ''],
            ['title' => 'Around', 'picture' => null, 'text' => '<p>A park nearby.</p>'],
            ['title' => '', 'picture' => ['path' => $photo->path], 'text' => '<p>No title.</p>'],
            ['title' => 'Empty', 'picture' => ['path' => 'media/gone.webp'], 'text' => '  '],
        ];

        $html = $this->render('tabs', ['heading' => 'The flat', 'tabs' => $tabs, 'picture_side' => 'start', 'align' => 'center']);

        $this->assertStringContainsString('<section class="b-tabs b-tabs--start b-tabs--center" data-wx-block="tabs">', $html);
        $this->assertStringContainsString('<h2 class="b-tabs__heading">The flat</h2>', $html);
        $this->assertStringContainsString('<div data-webx-tabs="" data-webx-tabs-label="The flat" class="webx-tabs b-tabs__tabs">', $html);
        $this->assertSame(3, substr_count($html, '<section data-webx-tab="" class="webx-tabs__panel">'), 'a tab without a title or with nothing under it is left out');
        $this->assertStringContainsString('<h3 class="webx-tabs__title">Overview</h3>', $html, 'the title is the heading without JavaScript and the tab with it');
        $this->assertStringContainsString('<h3 class="webx-tabs__title">Around</h3>', $html);
        $this->assertStringNotContainsString('Empty', $html);
        $this->assertMatchesRegularExpression('#<img class="b-tabs__picture" src="[^"]*/'.preg_quote($photo->path, '#').'[^"]*" alt="The room"\s+width="1800" height="1200"\s*>#', $html);
        $this->assertSame(1, substr_count($html, 'loading="lazy"'), 'the picture of the first tab, open when the page opens, is not put off');
        $this->assertSame(1, substr_count($html, 'b-tabs__body b-tabs__body--picture'), 'beside only when there is both');
        $this->assertStringContainsString('<div class="b-tabs__text"><p>A park nearby.</p></div>', $html);
        $this->assertContains('tabs', Widgets::claimed());

        // A block saved before the options: the picture above, the tabs at the start; no heading — the titles are the second level.
        $old = $this->render('tabs', ['tabs' => [$tabs[0]]]);
        $this->assertStringContainsString('<section class="b-tabs b-tabs--top" data-wx-block="tabs">', $old);
        $this->assertStringContainsString('<h2 class="webx-tabs__title">Overview</h2>', $old);
        $this->assertStringNotContainsString('data-webx-tabs-label', $old);
        $this->assertStringNotContainsString('data-webx-tabs-transition', $old, 'switching at once, as before the option');
        $this->assertStringNotContainsString('data-webx-tabs-transition', $html);

        // Fade: the component cross-fades; tabs with only a picture print the picture alone — no empty text, no gap for it.
        $plan = $this->render('tabs', ['tabs' => [$tabs[1], ['title' => 'Floor 2', 'picture' => ['path' => $photo->path], 'text' => '']], 'transition' => 'fade']);
        $this->assertStringContainsString('<div data-webx-tabs="" data-webx-tabs-transition="fade" class="webx-tabs b-tabs__tabs">', $plan);
        $this->assertSame(2, substr_count($plan, '<div class="b-tabs__body">'));
        $this->assertStringNotContainsString('b-tabs__text', $plan);
        $this->assertStringNotContainsString('data-webx-tabs-transition', $this->render('tabs', ['tabs' => [$tabs[0]], 'transition' => 'instant']));

        foreach ([['tabs' => []], ['tabs' => [$tabs[3], $tabs[4]]], []] as $case => $values) {
            $this->assertStringNotContainsString('b-tabs', $this->render('tabs', $values), "case {$case}");
        }
    }

    #[Test]
    public function the_tabs_type_reads_the_fields_its_template_uses(): void
    {
        $type = json_decode((string) file_get_contents(__DIR__.'/../resources/blocks/tabs.json'), true);
        $fields = array_column($type['schema'], null, 'id');

        $this->assertSame(['heading', 'tabs', 'picture_side', 'align', 'transition'], array_keys($fields));
        $this->assertSame(['title', 'picture', 'text'], array_column($fields['tabs']['children'], 'id'));
        $this->assertSame('wx-rich-text', $fields['tabs']['children'][2]['type']);
        $this->assertTrue($fields['tabs']['children'][0]['localized'] && $fields['tabs']['children'][2]['localized']);
        $this->assertSame(array_keys($fields), array_keys($type['sample']), 'a value for every field');
        $this->assertCount(3, $type['sample']['tabs']);
        $this->assertSame('instant', $fields['transition']['default'], 'a new block switches the way an old one does');
        $this->assertSame(['instant', 'fade'], array_column($fields['transition']['props']['options'], 'value'));
    }

    /** A picture in the library, the way an upload leaves one — the bytes need not be there. */
    private function picture(string $name, string $mime = 'image/webp'): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
        $extension = match ($mime) {
            'image/svg+xml' => 'svg',
            'video/mp4' => 'mp4',
            default => 'webp',
        };

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => "media/ab/cd/{$name}.{$extension}",
            'hash' => md5($name),
            'name' => $name,
            'file_name' => "{$name}.{$extension}",
            'extension' => $extension,
            'mime' => $mime,
            'size' => 2048,
            'width' => $extension === 'webp' ? 1800 : null,
            'height' => $extension === 'webp' ? 1200 : null,
        ]);
    }

    /** @return array<string, mixed> What a table of contents left for the finished page to fill. */
    private function marker(string $html): array
    {
        $this->assertSame(1, preg_match('~<!--webx-toc:([A-Za-z0-9+/=]+)-->~', $html, $found));
        $settings = (array) json_decode((string) base64_decode($found[1]), true);
        unset($settings['id']);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function render(string $type, array $values): string
    {
        $this->rendered++;

        return (string) $this->app->make(Renderer::class)->render([['key' => "k{$this->rendered}", 'type' => $type, 'values' => $values]]);
    }
}
