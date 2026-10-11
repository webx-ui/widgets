<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\View\Components\Countdown;

/**
 * Spec §10 and §14, W5.2 — what moves on a page: the background video of a first screen, the
 * counter that counts up in view and the countdown to a moment in the site's time zone. Each
 * prints on the server what holds without JavaScript and claims its file only where it stands.
 */
final class MotionTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/moving', static fn (): string => Blade::render('<x-layout><x-webx-counter :value="3000" /><x-webx-countdown to="2030-01-01 00:00" /></x-layout>'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_background_is_a_muted_loop_behind_what_it_wraps_with_its_pause_button(): void
    {
        $html = Blade::render('<x-webx-video variant="background" file="/media/clip.mp4" poster="/media/still.webp" ratio="21/9"><h1>Hello</h1></x-webx-video>');

        $this->assertStringContainsString('style="--webx-video-ratio: 21 / 9" class="webx-video webx-video--background" data-webx-video-background>', $html);
        // Decor: the poster and the video hidden from screen readers, the button not.
        $this->assertStringContainsString('<div class="webx-video__backdrop" aria-hidden="true">', $html);
        $this->assertStringContainsString('<img class="webx-video__poster" src="/media/still.webp" alt="" decoding="async"', $html);
        $this->assertStringNotContainsString('loading="lazy"', $html, 'a first screen does not wait to show its poster');
        $this->assertStringContainsString('<video class="webx-video__background" muted loop playsinline preload="none" disablepictureinpicture tabindex="-1"  poster="/media/still.webp" >', $html);
        $this->assertStringContainsString('<source src="/media/clip.mp4"  type="video/mp4" >', $html);
        $this->assertStringNotContainsString('controls', $html);
        $this->assertStringNotContainsString(' autoplay', $html, 'the script plays it, so nothing moves without a way to stop it');
        $this->assertStringContainsString('<div class="webx-video__over"><h1>Hello</h1></div>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-video__pause is-paused" aria-label="Play the background video" data-pause="Pause the background video" data-play="Play the background video">', $html);
        $this->assertStringNotContainsString('data-webx-consent', $html, 'a file of the site asks for no consent');
        $this->assertContains('video', Widgets::claimed());

        // Nothing wrapped: no layer for it, the frame keeps its ratio alone.
        $bare = Blade::render('<x-webx-video variant="background" file="/media/clip.mp4" />');
        $this->assertStringNotContainsString('webx-video__over', $bare);
        $this->assertStringNotContainsString('webx-video__poster', $bare);
        $this->assertStringContainsString('--webx-video-ratio: 16 / 9', $bare);
    }

    #[Test]
    public function the_pause_button_speaks_the_language_of_the_page(): void
    {
        App::setLocale('de');

        $html = Blade::render('<x-webx-video variant="background" file="/media/clip.webm" />');

        $this->assertStringContainsString('aria-label="Hintergrundvideo abspielen" data-pause="Hintergrundvideo anhalten"', $html);
        $this->assertStringContainsString('type="video/webm"', $html);
    }

    #[Test]
    public function a_counter_prints_its_final_number_the_way_the_language_writes_it(): void
    {
        $html = Blade::render('<x-webx-counter :value="3000" suffix="+" />');

        $this->assertSame(
            '<span class="webx-counter" data-webx-counter="{&quot;value&quot;:3000,&quot;decimals&quot;:0,&quot;duration&quot;:2000}"><span class="webx-counter__number">3,000</span><span class="webx-counter__suffix">+</span></span>',
            trim($html),
        );
        $this->assertContains('counter', Widgets::claimed());

        // Decimals are what it was written with; a prefix stands before it; the language groups it.
        $rating = Blade::render('<x-webx-counter value="4.9" prefix="≈" :duration="800" />');
        $this->assertStringContainsString('{&quot;value&quot;:4.9,&quot;decimals&quot;:1,&quot;duration&quot;:800}', $rating);
        $this->assertStringContainsString('<span class="webx-counter__prefix">≈</span><span class="webx-counter__number">4.9</span>', $rating);

        App::setLocale('de');
        $this->assertStringContainsString('<span class="webx-counter__number">12.500,5</span>', Blade::render('<x-webx-counter value="12500.5" />'));
        $this->assertStringContainsString('<span class="webx-counter__number">3</span>', Blade::render('<x-webx-counter value="3.25" :decimals="0" />'));
    }

    #[Test]
    public function a_countdown_runs_to_a_wall_time_of_the_site_and_says_the_date_for_those_who_cannot_see_it_tick(): void
    {
        config()->set('app.timezone', 'Europe/Berlin');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-30 16:59:30', 'UTC'));

        $html = Blade::render('<x-webx-countdown to="2026-12-31 18:00" ended="The sale is over" />');

        // 18:00 in Berlin is 17:00 UTC: a day, no hours, no minutes, thirty seconds.
        $this->assertStringContainsString('<div class="webx-countdown" data-webx-countdown="{&quot;end&quot;:1798736400000}">', $html);
        $this->assertStringContainsString('<time datetime="2026-12-31T18:00:00+01:00">Ends on December 31, 2026 6:00 PM (UTC+01:00)</time>', $html);
        $this->assertStringContainsString('<div class="webx-countdown__units" aria-hidden="true">', $html);
        $this->assertMatchesRegularExpression('~<span class="webx-countdown__value" data-unit="days">1</span><span class="webx-countdown__label">Days</span>~', $html);
        $this->assertStringContainsString('data-unit="hours">00</span>', $html);
        $this->assertStringContainsString('data-unit="minutes">00</span>', $html);
        $this->assertStringContainsString('data-unit="seconds">30</span>', $html);
        $this->assertStringContainsString('<p class="webx-countdown__ended"  hidden >The sale is over</p>', $html);
        $this->assertContains('countdown', Widgets::claimed());

        // A moment with its offset is that moment, whatever the site's zone.
        $this->assertStringContainsString('{&quot;end&quot;:1798736400000}', Blade::render('<x-webx-countdown to="2026-12-31T19:00:00+02:00" />'));
    }

    #[Test]
    public function at_the_end_it_says_what_it_was_given_or_prints_nothing(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-02 00:00', 'UTC'));

        $over = Blade::render('<x-webx-countdown to="2027-01-01 00:00" ended="The sale is over" />');
        $this->assertStringContainsString('class="webx-countdown is-over"', $over);
        $this->assertStringContainsString('<p class="webx-countdown__ended" >The sale is over</p>', $over);
        $this->assertStringNotContainsString('webx-countdown__units', $over);
        $this->assertStringNotContainsString('<time', $over);

        $this->assertSame('', trim(Blade::render('<x-webx-countdown to="2027-01-01 00:00" />')));
    }

    #[Test]
    public function the_words_of_the_countdown_are_the_pages(): void
    {
        App::setLocale('ru');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-01 00:00', 'UTC'));

        $html = Blade::render('<x-webx-countdown to="2026-12-31 18:00" />');

        $this->assertStringContainsString('Заканчивается 31 декабря 2026 г., 18:00 (UTC+00:00)', $html);
        $this->assertStringContainsString('<span class="webx-countdown__label">Дней</span>', $html);
        $this->assertStringContainsString('<span class="webx-countdown__label">Секунд</span>', $html);
    }

    #[Test]
    public function the_page_loads_the_files_of_the_counter_and_the_countdown_where_they_stand(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/moving')->assertOk()->getContent();

        foreach (['counter', 'countdown'] as $widget) {
            $this->assertMatchesRegularExpression("~<link rel=\"stylesheet\" href=\"[^\"]+/{$widget}\.css\">~", $html);
            $this->assertMatchesRegularExpression("~<script type=\"module\" src=\"[^\"]+/{$widget}\.js\"></script>~", $html);
        }

        $this->assertStringNotContainsString('video.js', $html);
    }

    #[Test]
    public function the_site_zone_is_the_applications_without_the_contacts(): void
    {
        config()->set('app.timezone', 'America/New_York');

        $this->assertSame('America/New_York', Countdown::siteZone()->getName());
        $this->assertSame('2026-07-01T09:00:00-04:00', Countdown::moment('2026-07-01 09:00')->toAtomString());
    }

    #[Test]
    public function a_typo_says_what_it_takes(): void
    {
        foreach ([
            '<x-webx-video variant="hero" file="/a.mp4" />' => 'the one variant is "background"',
            '<x-webx-video variant="background" src="https://www.youtube.com/watch?v=aqz-KE-bpKQ" />' => 'never a `src`',
            '<x-webx-video variant="background" />' => 'a `file` of the site',
            '<x-webx-counter value="many" />' => 'a number',
            '<x-webx-counter :value="1" :decimals="9" />' => '0 to 6',
            '<x-webx-counter :value="1" :duration="60000" />' => 'milliseconds',
            '<x-webx-countdown to="next friday-ish o\'clock" />' => 'a date and time',
            '<x-webx-countdown to="" />' => 'a date and time',
        ] as $template => $message) {
            try {
                Blade::render($template);
                $this->fail("{$template} rendered.");
            } catch (Throwable $error) {
                $error = $error instanceof ViewException ? ($error->getPrevious() ?? $error) : $error;
                $this->assertInstanceOf(InvalidArgumentException::class, $error, $template);
                $this->assertStringContainsString($message, $error->getMessage(), $template);
            }
        }
    }
}
