<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Video\VideoProviders;
use WebxUi\Widgets\Widgets as WidgetsService;

/**
 * Spec §10 and §9.4: a YouTube or Vimeo video is a facade — a link with the site's poster that
 * the script makes a play button — and before consent to `media` the server prints the
 * placeholder in its place, from the cookie; a file of the site is a `<video preload="none">`.
 */
final class VideoTest extends TestCase
{
    private const string YOUTUBE = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ';

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/video', static fn (): string => Blade::render('<x-layout><x-webx-video src="'.self::YOUTUBE.'" title="Big Buck Bunny" poster="/media/poster.webp" /></x-layout>'));
        $router->get('/file', static fn (): string => Blade::render('<x-layout><x-webx-video file="/media/clip.mp4" /></x-layout>'));
    }

    #[Test]
    public function before_consent_the_server_prints_the_placeholder_over_the_link_to_the_video(): void
    {
        $html = Blade::render('<x-webx-video src="'.self::YOUTUBE.'" title="Big Buck Bunny" poster="/media/poster.webp" />');

        $this->assertStringContainsString('style="--webx-video-ratio: 16 / 9" class="webx-video webx-video--youtube is-blocked"', $html);
        $this->assertStringContainsString('data-webx-consent="media"', $html, 'the banner offers media wherever a video waits for it');
        $this->assertStringContainsString('data-webx-video="{&quot;embed&quot;:&quot;https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?autoplay=1&amp;playsinline=1&amp;rel=0&quot;,&quot;title&quot;:&quot;Big Buck Bunny&quot;}"', $html);
        // Without JavaScript: a link to the video with the site's poster in it.
        $this->assertStringContainsString('<a class="webx-video__facade" href="https://www.youtube.com/watch?v=aqz-KE-bpKQ" aria-label="Play: Big Buck Bunny">', $html);
        $this->assertStringContainsString('<img class="webx-video__poster" src="/media/poster.webp" alt="" loading="lazy" decoding="async"', $html);
        $this->assertStringContainsString('<p class="webx-video__notice">The video loads from YouTube, which may set cookies.</p>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-video__button" data-webx-video-load>Load</button>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-video__button webx-video__button--always" data-webx-video-always>Always load videos</button>', $html);
        $this->assertStringNotContainsString('<iframe', $html, 'the player only on a click');
        $this->assertStringNotContainsString('ytimg', $html);
        $this->assertContains('video', Widgets::claimed());
    }

    #[Test]
    public function with_consent_to_media_it_is_the_facade_alone(): void
    {
        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => 1, 'd' => '2026-10-09', 'c' => ['media']], JSON_THROW_ON_ERROR));

        $html = (string) $this->get('/video')->assertOk()->getContent();

        $this->assertStringContainsString('style="--webx-video-ratio: 16 / 9" class="webx-video webx-video--youtube"', $html);
        $this->assertStringContainsString('class="webx-video__facade"', $html);
        $this->assertStringNotContainsString('webx-video__consent', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    #[Test]
    public function an_answer_without_media_and_a_banner_switched_off_decide_it_as_well(): void
    {
        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => 1, 'd' => '2026-10-09', 'c' => ['statistics']], JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('webx-video__consent', (string) $this->get('/video')->getContent());

        config()->set('webx-widgets.consent.enabled', false);
        $this->assertStringNotContainsString('webx-video__consent', (string) $this->get('/video')->getContent());
    }

    #[Test]
    public function the_page_of_a_video_loads_its_files_and_the_banner_offers_media(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();
        $layer = app(BottomLayers::class)->get(WidgetsService::NAME);
        $this->assertNotNull($layer);
        $base = 'http://localhost/themes/webx-ui/widgets/'.app(ThemeAssets::class)->current($layer);

        $html = (string) $this->get('/video')->assertOk()->getContent();

        $this->assertStringContainsString("<link rel=\"stylesheet\" href=\"{$base}/video.css\">", $html);
        $this->assertStringContainsString("<script type=\"module\" src=\"{$base}/video.js\"></script>", $html);
        $this->assertMatchesRegularExpression('/data-webx-consent-row="media"(?![^>]*hidden)/', $html);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function addresses(): iterable
    {
        yield 'watch' => ['https://www.youtube.com/watch?v=aqz-KE-bpKQ&list=x', 'youtube', 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?autoplay=1&playsinline=1&rel=0', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ'];
        yield 'short link with a start' => ['https://youtu.be/aqz-KE-bpKQ?t=1m30s', 'youtube', 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?autoplay=1&playsinline=1&rel=0&start=90', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ&t=90s'];
        yield 'shorts on the phone' => ['https://m.youtube.com/shorts/aqz-KE-bpKQ', 'youtube', 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?autoplay=1&playsinline=1&rel=0', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ'];
        yield 'an embed already' => ['https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?start=10', 'youtube', 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?autoplay=1&playsinline=1&rel=0&start=10', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ&t=10s'];
        yield 'music' => ['https://music.youtube.com/watch?v=aqz-KE-bpKQ', 'youtube', 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?autoplay=1&playsinline=1&rel=0', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ'];
        yield 'vimeo' => ['https://vimeo.com/1084537', 'vimeo', 'https://player.vimeo.com/video/1084537?autoplay=1&dnt=1', 'https://vimeo.com/1084537'];
        yield 'unlisted vimeo with a start' => ['https://vimeo.com/1084537/ab12cd34ef#t=45s', 'vimeo', 'https://player.vimeo.com/video/1084537?h=ab12cd34ef&autoplay=1&dnt=1#t=45s', 'https://vimeo.com/1084537/ab12cd34ef#t=45s'];
        yield 'vimeo player' => ['https://player.vimeo.com/video/1084537?h=ab12cd34ef', 'vimeo', 'https://player.vimeo.com/video/1084537?h=ab12cd34ef&autoplay=1&dnt=1', 'https://vimeo.com/1084537/ab12cd34ef'];
        yield 'vimeo channel' => ['https://vimeo.com/channels/staffpicks/1084537', 'vimeo', 'https://player.vimeo.com/video/1084537?autoplay=1&dnt=1', 'https://vimeo.com/1084537'];
    }

    #[Test]
    #[DataProvider('addresses')]
    public function each_address_of_a_provider_is_its_video(string $url, string $provider, string $embed, string $page): void
    {
        $video = app(VideoProviders::class)->find($url);

        $this->assertNotNull($video);
        $this->assertSame($provider, $video->provider->key());
        $this->assertSame($embed, $video->provider->embed($video));
        $this->assertSame($page, $video->provider->page($video));
    }

    #[Test]
    public function an_address_that_is_neither_is_nobodys(): void
    {
        foreach (['https://example.com/watch?v=aqz-KE-bpKQ', 'https://www.youtube.com/watch?v=short', 'https://vimeo.com/about', 'not a url'] as $url) {
            $this->assertNull(app(VideoProviders::class)->find($url), $url);
        }
    }

    #[Test]
    public function a_file_of_the_site_is_a_video_that_asks_for_nothing(): void
    {
        // What a media field resolves to.
        $file = ['url' => '/media/clip.bin', 'mime' => 'video/mp4', 'width' => null, 'height' => null];
        $poster = ['url' => '/media/poster.webp', 'width' => 1280, 'height' => 720];

        $html = Blade::render('<x-webx-video :file="$file" :poster="$poster" title="The clip" ratio="4:3" />', ['file' => $file, 'poster' => $poster]);

        $this->assertStringContainsString('style="--webx-video-ratio: 4 / 3" class="webx-video webx-video--file"', $html);
        $this->assertStringContainsString('<video class="webx-video__player" controls preload="none" playsinline aria-label="The clip"  poster="/media/poster.webp" >', $html);
        $this->assertStringContainsString('<source src="/media/clip.bin"  type="video/mp4" >', $html);
        $this->assertStringContainsString('<a href="/media/clip.bin">The clip</a>', $html);
        $this->assertStringNotContainsString('data-webx-consent', $html, 'first party: no consent to ask for');
        $this->assertStringNotContainsString('webx-video__consent', $html);

        // An address: the type from its extension; no poster, none printed.
        $html = Blade::render('<x-webx-video file="/media/clip.webm" />');
        $this->assertStringContainsString('type="video/webm"', $html);
        $this->assertStringNotContainsString('poster=', $html);
        $this->assertStringContainsString('aria-label="Video"', $html);
    }

    #[Test]
    public function the_ratio_is_kept_as_written_or_16_9(): void
    {
        $ratio = static fn (string $prop): string => Blade::render('<x-webx-video file="/a.mp4" '.$prop.' />');

        $this->assertStringContainsString('--webx-video-ratio: 16 / 9', $ratio(''));
        $this->assertStringContainsString('--webx-video-ratio: 9 / 16', $ratio('ratio="9/16"'));
        $this->assertStringContainsString('--webx-video-ratio: 2.35 / 1', $ratio(':ratio="2.35"'));

        try {
            $ratio('ratio="wide"');
            $this->fail('a ratio of words rendered');
        } catch (\Throwable $error) {
            $this->assertInstanceOf(InvalidArgumentException::class, $error->getPrevious() ?? $error);
        }
    }

    #[Test]
    public function an_address_nobody_plays_or_two_sources_or_none_are_a_typo(): void
    {
        foreach ([
            '<x-webx-video src="https://example.com/clip" />' => 'is not an address of YouTube or Vimeo',
            '<x-webx-video src="'.self::YOUTUBE.'" file="/a.mp4" />' => 'either `src`',
            '<x-webx-video />' => 'either `src`',
        ] as $template => $said) {
            try {
                Blade::render($template);
                $this->fail("{$template} rendered");
            } catch (\Throwable $error) {
                // Blade wraps what a component throws in a ViewException.
                $this->assertInstanceOf(InvalidArgumentException::class, $error->getPrevious() ?? $error);
                $this->assertStringContainsString($said, $error->getMessage());
            }
        }
    }

    #[Test]
    public function a_poster_object_and_the_words_of_the_language(): void
    {
        app()->setLocale('ru');
        $poster = new class
        {
            public int $width = 1200;

            public int $height = 675;

            public function url(): string
            {
                return '/catalog/poster.jpg';
            }
        };

        $html = Blade::render('<x-webx-video src="https://vimeo.com/1084537" :poster="$poster" />', ['poster' => $poster]);

        $this->assertStringContainsString('src="/catalog/poster.jpg" alt="" loading="lazy" decoding="async"  width="1200" height="675"', $html);
        $this->assertStringContainsString('aria-label="Смотреть: Видео"', $html);
        $this->assertStringContainsString('Видео загрузится с Vimeo, который может ставить cookie.', $html);
        $this->assertStringContainsString('>Всегда загружать видео</button>', $html);
    }

    #[Test]
    public function without_a_poster_and_without_a_library_the_frame_stands_on_its_background(): void
    {
        $html = Blade::render('<x-webx-video src="'.self::YOUTUBE.'" />');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('webx-video__play', $html);
    }
}
