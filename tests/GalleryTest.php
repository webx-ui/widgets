<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
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
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\View\Sliders;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The `gallery` block's head, the mosaic's spacing and narrow column, the carousel's arrows under
 * the head, captions on the picture and the ribbon of posts (§15.1). A block saved before them
 * prints what it printed before: `Fixtures/before/gallery.blade.php` is the template before them.
 */
final class GalleryTest extends TestCase
{
    use MigratesOnce;

    /** The fields of this stage; a block saved before it has none of them. */
    private const array NEW = ['eyebrow', 'intro', 'align', 'frames', 'gap', 'captions', 'narrow', 'arrows_at'];

    /** What a mosaic's picture says to the browser: fetched near the screen, decoded off the main thread. */
    private const string LATER = ' loading="lazy" decoding="async"';

    /** Each render a block of its own key, as on a page. */
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
    public function a_block_saved_before_prints_what_it_printed_before(): void
    {
        $pictures = $this->pictures(7);
        $pictures[0]['title'] = 'Under the first';

        $saved = [
            'grid' => ['heading' => 'Work', 'pictures' => $pictures, 'columns' => 4],
            'grid without zoom' => ['pictures' => $pictures, 'zoom' => false],
            'slider' => ['heading' => 'Work', 'pictures' => $pictures, 'view' => 'slider', 'magnify' => 'side'],
            'slider of three' => ['pictures' => $pictures, 'view' => 'slider', 'per_view' => 3, 'dots' => true],
            'mosaic' => ['heading' => 'Work', 'pictures' => $pictures, 'view' => 'mosaic', 'ratio' => '4:3'],
            'mosaic carousel' => ['heading' => 'Work', 'pictures' => $pictures, 'view' => 'mosaic', 'mosaic' => 'slider', 'autoplay' => true],
            'mosaic of two' => ['pictures' => array_slice($pictures, 0, 2), 'view' => 'mosaic'],
        ];

        foreach ($saved as $case => $values) {
            $now = $this->render($values, 'k-now');
            $defaults = $this->render($values + ['eyebrow' => '', 'intro' => '', 'align' => 'start', 'gap' => 'narrow', 'captions' => 'under', 'narrow' => 'pair', 'arrows_at' => 'below', 'frames' => []], 'k-now');
            $this->before();
            $then = $this->render($values, 'k-now');
            $this->after();

            // The mosaic's pictures wait until they near the screen: the one thing it adds.
            $this->assertSame(self::earlier($then), self::earlier($now), $case);
            $this->assertSame(self::earlier($then), self::earlier($defaults), "{$case}, the new fields at their defaults");
        }
    }

    #[Test]
    public function the_new_fields_default_to_what_the_block_was_and_show_where_they_mean_something(): void
    {
        $fields = self::fields();

        $this->assertSame('eyebrow', self::type()['schema'][0]['id'], 'above the heading in the form too');
        $this->assertSame(['start', 'center'], array_column($fields['align']['props']['options'], 'value'));
        $this->assertSame('start', $fields['align']['default']);
        $this->assertSame(['grid', 'slider', 'mosaic', 'ribbon'], array_column($fields['view']['props']['options'], 'value'));
        $this->assertSame(['narrow', 'small', 'medium', 'large', 'wide'], array_column($fields['gap']['props']['options'], 'value'));
        $this->assertSame('narrow', $fields['gap']['default']);
        $this->assertSame(['when' => 'view', 'not' => 'slider'], $fields['gap']['visible']);
        $this->assertSame('under', $fields['captions']['default']);
        $this->assertSame(['when' => 'view', 'not' => 'ribbon'], $fields['captions']['visible']);
        $this->assertSame('pair', $fields['narrow']['default']);
        $this->assertSame(['when' => 'view', 'is' => 'mosaic'], $fields['narrow']['visible']);
        $this->assertSame('below', $fields['arrows_at']['default']);
        $this->assertSame(['when' => 'view', 'is' => 'ribbon'], $fields['frames']['visible']);
        $this->assertSame(['when' => 'view', 'not' => 'ribbon'], $fields['pictures']['visible']);
        $this->assertSame(['picture', 'link', 'badge'], array_column($fields['frames']['children'], 'id'));
        $this->assertSame(['none', 'pin', 'video'], array_column($fields['frames']['children'][2]['props']['options'], 'value'));

        // The sample is the form's defaults: what the panel draws for a new block.
        foreach (self::NEW as $id) {
            $this->assertArrayHasKey($id, self::type()['sample'], $id);
        }
    }

    #[Test]
    public function the_head_prints_the_line_above_the_heading_and_the_words_under_it(): void
    {
        $pictures = $this->pictures(3);

        $html = $this->render(['eyebrow' => 'Our work', 'heading' => 'Rooms', 'intro' => 'Three of them', 'align' => 'center', 'pictures' => $pictures, 'view' => 'mosaic']);
        $this->assertMatchesRegularExpression('~<div class="b-gallery__head">\s*<p class="b-gallery__eyebrow">Our work</p>\s*<h2 class="b-gallery__heading">Rooms</h2>\s*<p class="b-gallery__intro">Three of them</p>\s*</div>~', $html);
        $this->assertStringContainsString('class="b-gallery b-gallery--mosaic b-gallery--center"', $html);

        // The heading alone stands as it did, the head's alignment on the section.
        $alone = $this->render(['heading' => 'Rooms', 'align' => 'center', 'pictures' => $pictures]);
        $this->assertStringNotContainsString('b-gallery__head"', $alone);
        $this->assertStringContainsString('b-gallery--center', $alone);

        $styles = (string) self::type()['styles'];
        $this->assertStringContainsString('.b-gallery--center .b-gallery__head,', $styles);
        $this->assertStringContainsString('.b-gallery__eyebrow {', $styles);
    }

    #[Test]
    public function the_mosaic_takes_its_space_from_the_theme_and_a_narrow_column_one_under_another(): void
    {
        $pictures = $this->pictures(3);

        $html = $this->render(['pictures' => $pictures, 'view' => 'mosaic', 'gap' => 'large', 'narrow' => 'column']);
        $this->assertStringContainsString('style="--gallery-columns: 3; --gallery-gap: var(--site-space-6, 2rem)"', $html);
        $this->assertStringContainsString('b-gallery--column', $html);

        // The slider has a gap of its own: the field is not its.
        $this->assertStringNotContainsString('--gallery-gap', $this->render(['pictures' => $pictures, 'view' => 'slider', 'gap' => 'large']));
        $this->assertStringNotContainsString('b-gallery--column', $this->render(['pictures' => $pictures, 'view' => 'grid', 'narrow' => 'column']));

        $this->assertMatchesRegularExpression('~@container b-gallery \(max-width: 35\.99em\) \{\s*\.b-gallery--column \.b-gallery__mosaic \{\s*grid-template-columns: minmax\(0, 1fr\);~', (string) self::type()['styles']);
    }

    #[Test]
    public function the_arrows_stand_under_the_head_and_only_when_there_is_something_to_page_through(): void
    {
        $pictures = $this->pictures(6);
        $values = ['heading' => 'Rooms', 'view' => 'mosaic', 'mosaic' => 'slider', 'arrows_at' => 'head'];

        $six = $this->render(['pictures' => $pictures] + $values);
        $this->assertStringContainsString('b-gallery--arrows-head', $six);
        $this->assertStringContainsString('webx-slider__prev', $six);
        $this->assertSame(2, substr_count($six, 'b-gallery__mosaic--3'), 'two threes to page through');

        // One three: nothing to page through, no slider and no arrows.
        $three = $this->render(['pictures' => array_slice($pictures, 0, 3)] + $values);
        $this->assertStringNotContainsString('webx-slider', $three);
        $this->assertSame(1, substr_count($three, 'b-gallery__mosaic--3'));

        $styles = (string) self::type()['styles'];
        $this->assertMatchesRegularExpression('~\.b-gallery--arrows-head \.webx-slider__controls \{\s*order: -1;\s*grid-row: 1;~', $styles);
        // The slider names its first cell for the slides (an overlay shares it): `order` alone would leave the arrows under them.
        $this->assertMatchesRegularExpression('~\.b-gallery--arrows-head \.webx-slider__viewport,\s*\.b-gallery--arrows-head \.webx-slider__overlay \{\s*grid-row: 2;~', $styles);
        $this->assertStringContainsString('--gallery-arrow-size: 52px;', $styles);
        $this->assertStringContainsString('--gallery-head-space: var(--site-space-6, 2rem);', $styles);
        $this->assertStringContainsString('--gallery-arrows-space: calc(var(--site-space-6, 2rem) + var(--site-space-2, 0.5rem));', $styles);
    }

    #[Test]
    public function a_caption_on_the_picture_is_its_alt_else_its_title(): void
    {
        $pictures = $this->pictures(3);
        $pictures[1]['alt'] = '';
        $pictures[1]['title'] = 'The hall';

        foreach (['grid', 'slider', 'mosaic'] as $view) {
            $html = $this->render(['pictures' => $pictures, 'view' => $view, 'captions' => 'over']);

            $this->assertStringContainsString('b-gallery--plates', $html, $view);
            // Said once: the alt is the picture's already.
            $this->assertStringContainsString('<figcaption class="b-gallery__caption" aria-hidden="true">Photo 1</figcaption>', $html, $view);
            $this->assertStringContainsString('<figcaption class="b-gallery__caption">The hall</figcaption>', $html, $view);
        }

        // Under the picture: the title alone, as it always was.
        $under = $this->render(['pictures' => $pictures, 'view' => 'mosaic']);
        $this->assertSame(1, substr_count($under, '<figcaption'));
        $this->assertStringNotContainsString('b-gallery--plates', $under);

        // The lightbox and the magnifier stay where they were.
        $slider = $this->render(['pictures' => $pictures, 'view' => 'slider', 'captions' => 'over', 'magnify' => 'inner']);
        $this->assertSame(3, substr_count($slider, 'data-zoom-mode="inner"'));

        $styles = (string) self::type()['styles'];
        $this->assertMatchesRegularExpression('~\.b-gallery--plates \.b-gallery__caption \{[^}]*position: absolute;[^}]*pointer-events: none;~', $styles);
        $this->assertStringContainsString('--gallery-plate-inset: var(--site-space-5, 1.5rem);', $styles);
        $this->assertStringContainsString('--gallery-plate-padding: var(--site-space-2, 0.5rem) var(--site-space-3, 0.75rem);', $styles);
    }

    #[Test]
    public function the_mosaic_still_and_turning_fetches_its_pictures_near_the_screen(): void
    {
        $pictures = $this->pictures(6);

        foreach (['still' => 3, 'slider' => 6] as $mosaic => $count) {
            foreach ([true, false] as $zoom) {
                $html = $this->render(['pictures' => $pictures, 'view' => 'mosaic', 'mosaic' => $mosaic, 'zoom' => $zoom]);
                $case = $mosaic.($zoom ? ', with zoom' : ', without zoom');

                $this->assertSame($count, substr_count($html, '<img class="b-gallery__image"'), $case);
                $this->assertSame($count, preg_match_all('~<img class="b-gallery__image"[^>]*height="1200"\s*loading="lazy" decoding="async">~', $html), $case);
                $this->assertStringNotContainsString('fetchpriority', $html, $case);
            }
        }
    }

    #[Test]
    public function the_ribbon_links_each_frame_to_its_post_in_a_new_tab_with_its_badge(): void
    {
        $link = static fn (string $url): array => ['target' => 'url', 'entity_type' => null, 'entity_id' => null, 'url' => $url, 'hash' => null, 'new_tab' => false, 'rel' => []];
        $frames = [
            ['picture' => ['path' => $this->picture('post-1')->path, 'alt' => 'A pinned post'], 'link' => $link('https://social.example/p/1'), 'badge' => 'pin'],
            ['picture' => ['path' => $this->picture('post-2')->path, 'alt' => 'A reel'], 'link' => $link('https://social.example/p/2'), 'badge' => 'video'],
            ['picture' => ['path' => $this->picture('post-3')->path, 'alt' => 'Just a photo'], 'link' => null, 'badge' => 'none'],
            ['picture' => ['path' => 'media/gone.webp'], 'link' => $link('https://social.example/p/4'), 'badge' => 'pin'],
        ];

        $html = $this->render(['eyebrow' => 'Follow us', 'heading' => 'In the feed', 'view' => 'ribbon', 'frames' => $frames, 'pictures' => $this->pictures(3)]);

        $this->assertStringContainsString('class="b-gallery b-gallery--ribbon site-bleed"', $html);
        $this->assertStringContainsString('<div class="b-gallery__head">', $html);
        $this->assertMatchesRegularExpression('~<ul class="b-gallery__ribbon" role="list" tabindex="0"\s+aria-label="In the feed"\s*>~', $html);
        $this->assertSame(3, substr_count($html, 'class="b-gallery__frame"'), 'a picture gone from the library is left out');
        $this->assertSame(2, preg_match_all('~<a class="b-gallery__post" href="https://social\.example/p/[12]" target="_blank" rel="noopener"~', $html));
        $this->assertStringContainsString('<span class="b-gallery__post">', $html, 'a frame without a link is a picture');
        $this->assertStringContainsString('b-gallery__badge b-gallery__badge--pin', $html);
        $this->assertStringContainsString('b-gallery__badge b-gallery__badge--video', $html);
        $this->assertSame(2, substr_count($html, 'b-gallery__badge '));
        $this->assertStringNotContainsString('data-webx-lightbox', $html, 'a frame is a link to its post, not to the lightbox');
        $this->assertStringNotContainsString('b-gallery__grid', $html, 'the pictures of the other views are not the ribbon\'s');

        // Nothing to show, nothing printed.
        $this->assertSame('', trim($this->render(['view' => 'ribbon', 'frames' => [], 'pictures' => $this->pictures(2)])));

        $styles = (string) self::type()['styles'];
        $this->assertMatchesRegularExpression('~\.b-gallery__ribbon \.b-gallery__image \{\s*aspect-ratio: 3 / 4;~', $styles);
        $this->assertStringContainsString('scroll-snap-type: x mandatory;', $styles);
    }

    /** The published version of the type swapped for the template before this stage. */
    private function before(): void
    {
        $block = Block::query()->where('slug', 'gallery')->firstOrFail();
        $block->saveVersion(['template' => (string) file_get_contents(__DIR__.'/Fixtures/before/gallery.blade.php')]);
        $block->refresh()->publish();
        Cache::flush();
    }

    /** And back to the template of the package. */
    private function after(): void
    {
        $block = Block::query()->where('slug', 'gallery')->firstOrFail();
        $block->saveVersion(['template' => (string) self::type()['template']]);
        $block->refresh()->publish();
        Cache::flush();
    }

    /** @return list<array<string, string>> */
    private function pictures(int $count): array
    {
        return array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path, 'alt' => "Photo {$n}"], range(1, $count));
    }

    /** A picture in the library, the way an upload leaves one — the bytes need not be there. */
    private function picture(string $name): MediaFile
    {
        $found = MediaFile::query()->where('name', $name)->first();

        if ($found !== null) {
            return $found;
        }

        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => "media/ab/cd/{$name}.webp",
            'hash' => md5($name),
            'name' => $name,
            'file_name' => "{$name}.webp",
            'extension' => 'webp',
            'mime' => 'image/webp',
            'size' => 2048,
            'width' => 1800,
            'height' => 1200,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function render(array $values, ?string $key = null): string
    {
        $this->rendered++;
        // Each render a page of its own: the ids of its sliders start over.
        $this->app->forgetInstance(Sliders::class);

        return (string) $this->app->make(Renderer::class)->render([['key' => $key ?? "k{$this->rendered}", 'type' => 'gallery', 'values' => $values]]);
    }

    /** The indent a template line left out leaves before the next one, which the browser drops. */
    private static function tight(string $html): string
    {
        return trim((string) preg_replace(['~\s+~', '~ >~'], [' ', '>'], $html));
    }

    /**
     * The markup without what the mosaic's pictures say since: a carousel's slides past the first
     * were lazy by the slider's hand, and its id is a hash of its slides, so both go too.
     */
    private static function earlier(string $html): string
    {
        return self::tight((string) preg_replace(['~webx-slider-[0-9a-f]{8}~', '~<img loading="lazy" ~'], ['webx-slider-*', '<img '], str_replace(self::LATER, '', $html)));
    }

    /** @return array<string, array<string, mixed>> */
    private static function fields(): array
    {
        return array_column(self::type()['schema'], null, 'id');
    }

    /** @return array<string, mixed> */
    private static function type(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../resources/blocks/gallery.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
