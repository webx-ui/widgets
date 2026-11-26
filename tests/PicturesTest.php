<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Blocks\BlocksServiceProvider;
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
use WebxUi\Widgets\Pictures;
use WebxUi\Widgets\View\Sliders;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * A block's picture of the library in the formats the site cuts (§14.2): `@webxPicture` around the
 * block's `<img>`, the pictures nobody sees yet held back, the copies in AVIF cut after the
 * response — and, without the formats, every byte as it was.
 */
final class PicturesTest extends TestCase
{
    use MigratesOnce;

    /** The `<img>` of a block, the way a template prints it: indented, its own line. */
    private const string IMAGE = "        <img class=\"b-x__picture\" src=\"{{ \$picture['url'] }}\" alt=\"{{ \$picture['alt'] }}\" width=\"1800\" height=\"1200\" loading=\"lazy\">\n";

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
        $app['config']->set('webx-media.thumbs.widths', [160, 320, 480, 1080]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['widgets']])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    #[Test]
    public function without_formats_the_img_is_printed_byte_for_byte(): void
    {
        $picture = $this->value('photo-1');
        $this->cut('photo-1', 'avif', [480, 1080, 1800]);
        $plain = Blade::render(self::IMAGE, ['picture' => $picture], deleteCachedView: true);

        $wrapped = Blade::render("@webxPicture(\$picture, '100vw', ['lead' => true])\n".self::IMAGE."@endwebxPicture\n", ['picture' => $picture], deleteCachedView: true);

        $this->assertSame($plain, $wrapped);
        // Not a picture of the library, or none at all: as it is, whatever the site cuts.
        config(['webx-media.thumbs.formats' => ['avif']]);
        $this->assertSame($plain, Blade::render("@webxPicture(null)\n".self::IMAGE."@endwebxPicture\n", ['picture' => $picture], deleteCachedView: true));
    }

    #[Test]
    public function with_formats_the_img_gets_a_source_per_format_and_its_own_copies(): void
    {
        config(['webx-media.thumbs.formats' => ['avif', 'webp']]);
        $this->cut('photo-1', 'avif', [480, 1080, 1800]);
        $this->cut('photo-1', 'webp', [480, 1080]);

        $html = Blade::render("@webxPicture(\$picture, '(min-width: 40rem) 50vw, 100vw')\n".self::IMAGE."@endwebxPicture\n", ['picture' => $this->value('photo-1')], deleteCachedView: true);

        $this->assertMatchesRegularExpression('~^\s*<picture class="webx-picture" style="display: contents"><source type="image/avif" srcset="[^"]+/media/thumbs/photo-1/480\.avif\?v=\w+ 480w, [^"]+/1080\.avif\?v=\w+ 1080w, [^"]+/1800\.avif\?v=\w+ 1800w" sizes="\(min-width: 40rem\) 50vw, 100vw"><img class="b-x__picture" src="[^"]+/photo-1\.webp\?v=\w+" alt="Photo 1" width="1800" height="1200" loading="lazy" srcset="[^"]+/480\.webp\?v=\w+ 480w, [^"]+/1080\.webp\?v=\w+ 1080w, [^"]+/photo-1\.webp\?v=\w+ 1800w" sizes="\(min-width: 40rem\) 50vw, 100vw"></picture>\s*$~', $html);
    }

    #[Test]
    public function a_copy_in_avif_not_cut_yet_is_cut_after_the_response(): void
    {
        config(['webx-media.thumbs.formats' => ['avif']]);
        $image = imagecreatetruecolor(1800, 1200);
        ob_start();
        imagewebp($image);
        Storage::disk('public')->put('media/ab/cd/photo-1.webp', (string) ob_get_clean());
        $template = "@webxPicture(\$picture)\n".self::IMAGE."@endwebxPicture\n";

        // Nothing cut: nothing to offer, and the visitor does not wait for the resize.
        $first = Blade::render($template, ['picture' => $this->value('photo-1')], deleteCachedView: true);
        $this->assertStringNotContainsString('<source', $first);
        $this->app->terminate();

        foreach ([480, 1080, 1800] as $width) {
            $this->assertStringContainsString('ftypavif', (string) Storage::disk('public')->get("media/thumbs/photo-1/{$width}.avif"), (string) $width);
        }

        $this->app->forgetScopedInstances();
        $this->assertStringContainsString('<source type="image/avif" srcset="', Blade::render($template, ['picture' => $this->value('photo-1')], deleteCachedView: true));
    }

    #[Test]
    public function held_pictures_keep_their_addresses_aside_only_where_the_site_says(): void
    {
        $html = '<picture><source type="image/avif" srcset="/a.avif 480w"><img src="/a.jpg" srcset="/a.jpg 800w" alt=""></picture><a src="/not-a-picture">';

        // By default it follows the formats: without them, the markup it always was.
        $this->assertSame($html, Pictures::defer($html));

        config(['webx-media.thumbs.formats' => ['avif']]);
        $this->assertSame('<picture><source type="image/avif" data-webx-srcset="/a.avif 480w"><img data-webx-src="/a.jpg" data-webx-srcset="/a.jpg 800w" alt=""></picture><a src="/not-a-picture">', Pictures::defer($html));

        config(['webx-widgets.pictures.defer' => false]);
        $this->assertSame($html, Pictures::defer($html));
    }

    #[Test]
    public function the_slides_past_the_first_in_view_have_no_address_until_the_slider_shows_them(): void
    {
        $pictures = array_map(fn (int $n): array => ['path' => $this->file("photo-{$n}")->path, 'alt' => "Photo {$n}"], range(1, 4));
        config(['webx-widgets.pictures.defer' => true]);

        $html = $this->render(['pictures' => $pictures, 'view' => 'slider']);
        $slides = array_slice(explode('aria-roledescription="slide"', $html), 1);

        $this->assertCount(4, $slides);
        // The first slide, in view: its address as always.
        $this->assertMatchesRegularExpression('~<img class="b-gallery__image" src="[^"]+photo-1\.webp~', $slides[0]);
        foreach (array_slice($slides, 1) as $at => $slide) {
            $this->assertDoesNotMatchRegularExpression('~\s(?:src|srcset)="~', $slide, (string) ($at + 2));
            $this->assertMatchesRegularExpression('~<img loading="lazy" class="b-gallery__image" data-webx-src="[^"]+photo-'.($at + 2).'\.webp~', $slide);
        }

        // The tabs: a panel's picture past the first, the same way.
        $tabs = $this->render(['tabs' => array_map(static fn (array $picture): array => ['title' => $picture['alt'], 'text' => '', 'picture' => $picture], array_slice($pictures, 0, 2))], 'tabs');
        $this->assertSame(1, substr_count($tabs, ' src="'));
        $this->assertSame(1, substr_count($tabs, ' data-webx-src="'));
    }

    #[Test]
    public function a_wide_tab_picture_that_scrolls_on_a_phone_is_chosen_for_its_own_width_there(): void
    {
        config(['webx-media.thumbs.formats' => ['avif']]);
        $this->cut('photo-1', 'avif', [480, 1080, 1800]);
        $tab = ['title' => 'Plan', 'text' => '', 'picture' => $this->value('photo-1')];

        // 1800 × 1200 at the screen's height is 150 screens' widths of a phone, not one.
        $wide = $this->render(['tabs' => [$tab], 'media_bleed' => true], 'tabs');
        $this->assertMatchesRegularExpression('~<source type="image/avif" srcset="[^"]+" sizes="\(max-width: 40rem\) 150vw, 100vw">\s*<img class="b-tabs__picture" [^>]*style="--b-tabs-ratio: 1800 / 1200"~', $wide, 'its size survives the formats');

        // Without the option, or with a text beside it: the width of the screen, or of half the column, as before.
        $this->assertSame(1, substr_count($this->render(['tabs' => [$tab]], 'tabs'), 'sizes="100vw"'));
        $this->assertSame(1, substr_count($this->render(['tabs' => [['text' => '<p>A word.</p>'] + $tab], 'media_bleed' => true], 'tabs'), 'sizes="(min-width: 40rem) 50vw, 100vw"'));
    }

    /** @param  list<int>  $widths The copies already cut: the bytes need not be a picture. */
    private function cut(string $name, string $format, array $widths): void
    {
        foreach ($widths as $width) {
            Storage::disk('public')->put("media/thumbs/{$name}/{$width}.{$format}", 'x');
        }
    }

    /** @return array<string, mixed> The picture as a media field gives it to a block. */
    private function value(string $name): array
    {
        $file = $this->file($name);

        return ['url' => Storage::disk('public')->url($file->path).'?v='.substr($file->hash, 0, 8), 'path' => $file->path, 'alt' => 'Photo 1', 'width' => 1800, 'height' => 1200];
    }

    /** A picture in the library, the way an upload leaves one. */
    private function file(string $name): MediaFile
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
    private function render(array $values, string $type = 'gallery'): string
    {
        $this->rendered++;
        $this->app->forgetInstance(Sliders::class);

        // Under an empty block, which prints nothing: not the first screen.
        return (string) $this->app->make(Renderer::class)->render([
            ['key' => 'above', 'type' => 'gallery', 'values' => []],
            ['key' => "k{$this->rendered}", 'type' => $type, 'values' => $values],
        ]);
    }
}
