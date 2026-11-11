<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Widgets;

/**
 * `<x-webx-photos>` (spec §14): the photos of a card. One photo is a plain picture and claims no
 * script; two and more are the frame, the first photo shown without JavaScript, the bars and the
 * pause button printed hidden for the script.
 */
final class PhotosTest extends TestCase
{
    private const array ONE = ['url' => '/media/a.jpg', 'alt' => 'The hall', 'width' => 1200, 'height' => 800];

    private const array TWO = ['url' => '/media/b.jpg', 'alt' => 'The kitchen', 'width' => 1200, 'height' => 800];

    #[Test]
    public function one_photo_is_a_plain_picture_with_no_script(): void
    {
        $html = Blade::render('<x-webx-photos :images="$images" class="card__picture" />', ['images' => [self::ONE, ['url' => ''], null]]);

        $this->assertSame('<img class="card__picture" src="/media/a.jpg" alt="The hall" loading="lazy" width="1200" height="800" >', self::tight($html));
        $this->assertSame([], app(Widgets::class)->claimed());
    }

    #[Test]
    public function no_photo_prints_nothing(): void
    {
        $this->assertSame('', trim(Blade::render('<x-webx-photos :images="[]" />')));
        $this->assertSame('', trim(Blade::render('<x-webx-photos :images="null" />')));
        $this->assertSame([], app(Widgets::class)->claimed());
    }

    #[Test]
    public function two_photos_are_a_frame_with_the_first_shown_and_the_controls_for_the_script(): void
    {
        $html = Blade::render('<x-webx-photos :images="$images" ratio="3:2" class="card__photos" data-webx-reveal />', ['images' => [self::ONE, self::TWO]]);

        $this->assertSame(['photos'], app(Widgets::class)->claimed());
        $this->assertStringContainsString('class="webx-photos card__photos"', $html);
        $this->assertStringContainsString('style="--webx-photos-ratio: 3 / 2; --webx-photos-interval: 5000ms"', $html);
        $this->assertStringContainsString('role="group" aria-roledescription="carousel" aria-label="Photos"', $html);
        $this->assertStringContainsString('data-webx-photos="{&quot;autoplay&quot;:5000,&quot;words&quot;:{&quot;pause&quot;:&quot;Pause&quot;,&quot;play&quot;:&quot;Play&quot;}}"', $html);

        // The first is shown, the rest wait for the script; every one is a real picture with its alt.
        $this->assertMatchesRegularExpression('~<img class="webx-photos__photo is-active" src="/media/a.jpg" alt="The hall"\s+loading="lazy"\s+decoding="async"~', $html);
        $this->assertMatchesRegularExpression('~<img class="webx-photos__photo" src="/media/b.jpg" alt="The kitchen"\s+data-webx-later\s+loading="lazy"~', $html);

        $this->assertStringContainsString('<div class="webx-photos__bars" hidden>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-photos__bar" aria-label="Photo 2 of 2"><span class="webx-photos__fill"></span></button>', $html);
        $this->assertMatchesRegularExpression('~<button type="button" class="webx-photos__pause" aria-label="Pause" hidden>~', $html);
    }

    #[Test]
    public function the_interval_the_shape_and_the_name_are_props(): void
    {
        $html = Blade::render('<x-webx-photos :images="$images" :autoplay="8000" label="The flat" eager />', ['images' => [self::ONE, (object) ['url' => '/media/c.jpg']]]);

        $this->assertStringContainsString('--webx-photos-ratio: 1200 / 800; --webx-photos-interval: 8000ms', $html);
        $this->assertStringContainsString('aria-label="The flat"', $html);
        $this->assertStringContainsString('&quot;autoplay&quot;:8000', $html);
        // An eager first photo: the first screen of a page.
        $this->assertMatchesRegularExpression('~<img class="webx-photos__photo is-active" src="/media/a.jpg" alt="The hall"\s+decoding="async"~', $html);
        $this->assertStringContainsString('src="/media/c.jpg" alt=""', $html);
    }

    #[Test]
    public function without_autoplay_there_is_no_pause_button(): void
    {
        $html = Blade::render('<x-webx-photos :images="$images" :autoplay="false" />', ['images' => ['/media/a.jpg', '/media/b.jpg']]);

        $this->assertStringContainsString('&quot;autoplay&quot;:0', $html);
        $this->assertStringContainsString('--webx-photos-ratio: 4 / 3', $html);
        $this->assertStringNotContainsString('webx-photos__pause', $html);
        $this->assertStringContainsString('webx-photos__bars', $html);
    }

    #[Test]
    public function the_view_is_the_theme_s_to_replace(): void
    {
        $this->assertFileExists(dirname(__DIR__).'/resources/views/components/photos.blade.php');
        $this->assertStringContainsString('webx-photos__bar', Blade::render('<x-webx-photos :images="$images" />', ['images' => [self::ONE, self::TWO]]));
    }

    #[Test]
    public function a_wrong_ratio_is_an_error(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('a ratio is');
        Blade::render('<x-webx-photos :images="$images" ratio="wide" />', ['images' => [self::ONE, self::TWO]]);
    }

    private static function tight(string $html): string
    {
        return trim((string) preg_replace('~\s+~', ' ', $html));
    }
}
