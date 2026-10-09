<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Remote\HostResolver;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Video\Posters;
use WebxUi\Widgets\Video\VideoProviders;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * Spec §10: a YouTube or Vimeo video without a poster gets the video's preview — fetched once by
 * the site into its media library, after the response of the page that first showed it, so no
 * visitor ever asks the provider for it and none waits for it.
 */
final class VideoPostersTest extends TestCase
{
    private const string YOUTUBE = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ';

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
            MediaServiceProvider::class,
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
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);

        // The providers' hosts answer a public address with no network asked.
        $app->instance(HostResolver::class, new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return ['142.250.74.110'];
            }
        });
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/video', static fn (): string => Blade::render('<x-layout><x-webx-video src="'.self::YOUTUBE.'" /></x-layout>'));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function the_first_page_shows_no_poster_and_the_next_one_shows_the_sites_own(): void
    {
        Http::fake([
            'i.ytimg.com/vi/aqz-KE-bpKQ/maxresdefault.jpg' => Http::response('', 404),
            'i.ytimg.com/vi/aqz-KE-bpKQ/hqdefault.jpg' => Http::response(self::jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $first = (string) $this->get('/video')->assertOk()->getContent();

        $this->assertStringNotContainsString('<img', $first, 'the visitor who came first does not wait for YouTube');
        // Fetched after that response: the largest preview YouTube has.
        Http::assertSentCount(2);

        $folder = MediaDirectory::query()->where('title', Posters::FOLDER)->first();
        $this->assertNotNull($folder, 'a folder of its own at the root of the library');
        $poster = MediaFile::query()->where('directory_id', $folder->getKey())->first();
        $this->assertNotNull($poster);
        $this->assertSame('youtube-aqz-KE-bpKQ', $poster->name);
        $this->assertSame('image/webp', $poster->mime, 'through the library pipeline');

        $second = (string) $this->get('/video')->assertOk()->getContent();

        // The library's address, with its version of the file after `?v=`.
        $this->assertStringContainsString('<img class="webx-video__poster" src="'.Storage::disk('public')->url($poster->path).'?v=', $second);
        $this->assertStringContainsString('width="480" height="360"', $second);
        $this->assertStringNotContainsString('ytimg', $second);
        Http::assertSentCount(2);
    }

    #[Test]
    public function a_preview_that_failed_is_not_asked_for_again_the_same_day(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->get('/video')->assertOk();
        $this->get('/video')->assertOk();

        Http::assertSentCount(2);
        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function vimeo_names_its_preview_in_its_oembed(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['thumbnail_url' => 'https://i.vimeocdn.com/video/1-d_1280']),
            'i.vimeocdn.com/*' => Http::response(self::jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $video = app(VideoProviders::class)->find('https://vimeo.com/1084537');
        $this->assertNotNull($video);

        $file = app(Posters::class)->fetch($video);

        $this->assertNotNull($file);
        $this->assertSame('vimeo-1084537', $file->name);
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), 'oembed.json?url=https%3A%2F%2Fvimeo.com%2F1084537'));
    }

    #[Test]
    public function a_renamed_poster_is_still_the_videos_and_a_deleted_one_is_fetched_again(): void
    {
        Http::fake(['*' => Http::response(self::jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);
        $video = app(VideoProviders::class)->find(self::YOUTUBE);
        $this->assertNotNull($video);

        $file = app(Posters::class)->fetch($video);
        $this->assertNotNull($file);
        $file->update(['name' => 'Bunny']);

        $this->assertStringStartsWith(Storage::disk('public')->url($file->path), (string) (app(Posters::class)->find($video)['url'] ?? ''));
        Http::assertSentCount(1);

        $file->delete();
        $this->assertNull(app(Posters::class)->find($video));
        $this->assertNotNull(app(Posters::class)->fetch($video));
        Http::assertSentCount(2);
    }

    #[Test]
    public function an_answer_that_is_not_a_picture_is_no_poster(): void
    {
        Http::fake(['*' => Http::response('<html>not found</html>', 200, ['Content-Type' => 'image/jpeg'])]);
        $video = app(VideoProviders::class)->find(self::YOUTUBE);
        $this->assertNotNull($video);

        $this->assertNull(app(Posters::class)->fetch($video));
        $this->assertSame(0, MediaFile::query()->count());
    }

    private static function jpeg(): string
    {
        $image = imagecreatetruecolor(480, 360);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 40, 90, 160));
        ob_start();
        imagejpeg($image, null, 80);

        return (string) ob_get_clean();
    }
}
