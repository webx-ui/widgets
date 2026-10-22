<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Blocks\BlocksServiceProvider;
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
 * The blocks `gallery`, `logos`, `video`, `map`, `counters`, `countdown`, `compare` and `toc` (§15.1, §14): offered to a site with blocks and a media
 * library, installed and published by `webx:blocks:offered --install` (the line `webx:setup` prints), and printed with the
 * slider, the lightbox and the video inside — pictures of the library with their sizes, one
 * group per block; a video of a provider behind the consent, a file of the library as is; a
 * map of the contacts or of its own coordinates, behind the consent too.
 */
final class BlocksTest extends TestCase
{
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

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
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
        foreach (['gallery', 'logos', 'video', 'map', 'counters', 'countdown', 'compare', 'toc'] as $slug) {
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
        $this->assertStringContainsString('style="--webx-map-height: 24em" class="webx-map is-blocked"', $html);
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
    public function a_map_with_nowhere_to_show_prints_nothing(): void
    {
        $nothing = [
            'empty contacts' => ['heading' => 'Map'],
            'no coordinates' => ['heading' => 'Map', 'source' => 'coordinates', 'address' => 'Sample City'],
            'a latitude only' => ['heading' => 'Map', 'source' => 'coordinates', 'latitude' => 51.5],
            'off the earth' => ['heading' => 'Map', 'source' => 'coordinates', 'latitude' => 120, 'longitude' => 0],
            'not numbers' => ['heading' => 'Map', 'source' => 'coordinates', 'latitude' => 'north', 'longitude' => 'west'],
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
