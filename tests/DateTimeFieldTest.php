<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-field type="datetime">` (FORM_STEPS §6.4): a page without the script gets a date and a
 * list of the day's times in the site's zone, and the calendar's script everything it draws by.
 */
final class DateTimeFieldTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function without_the_script_it_is_a_date_and_a_list_of_times_under_one_legend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-10 08:00:00', 'UTC'));

        $html = Blade::render('<x-webx-field type="datetime" name="fields.meeting" label="Meeting" hint="Any weekday" :step="30" from="09:00" to="17:00" :weekdays="[1, 2, 3, 4, 5]" timezone="Europe/Paris" required />');

        $this->assertMatchesRegularExpression('~^<fieldset[^>]* class="webx-field webx-field--datetime is-required"~', trim($html));
        $this->assertStringContainsString('<legend class="webx-field__label webx-field__label--legend">Meeting', $html);
        $this->assertMatchesRegularExpression('~<input type="date" class="webx-field__control" id="field-fields-meeting" name="fields\[meeting\]\[date\]" value="" min="2026-12-10" aria-describedby="field-fields-meeting-zone field-fields-meeting-hint field-fields-meeting-error"\s+required~', $html);
        $this->assertMatchesRegularExpression('~<select [^>]*name="fields\[meeting\]\[time\]"[^>]*required~', $html);
        // From 09:00 a step apart, the last a step before 17:00.
        preg_match_all('~<option value="(\d\d:\d\d)"~', $html, $times);
        $this->assertCount(16, $times[1]);
        $this->assertSame(['09:00', '09:30'], array_slice($times[1], 0, 2));
        $this->assertSame('16:30', end($times[1]));
        $this->assertStringContainsString('<p class="webx-datetime__zone" id="field-fields-meeting-zone">Times are in Europe/Paris (UTC+1)</p>', $html);
        $this->assertSame(['field', 'datetime'], Widgets::claimed());

        preg_match('~data-webx-datetime="([^"]+)"~', $html, $data);
        $config = json_decode(html_entity_decode($data[1]), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([1, 2, 3, 4, 5], $config['weekdays']);
        $this->assertSame('Europe/Paris', $config['zone']);
        $this->assertSame(['value' => '09:00', 'label' => '9:00 AM'], $config['times'][0]);
        $this->assertSame('Times on :date', $config['words']['times']);
    }

    #[Test]
    public function today_is_the_day_of_the_site_zone_not_of_the_server(): void
    {
        // Late on the 11th by the server's clock, already the 12th in Tokyo.
        Carbon::setTestNow(Carbon::parse('2026-12-11 23:30:00', 'UTC'));

        $html = Blade::render('<x-webx-field type="datetime" name="meeting" label="Meeting" timezone="Asia/Tokyo" />');

        $this->assertStringContainsString('min="2026-12-12"', $html);
        $this->assertStringContainsString('Times are in Asia/Tokyo (UTC+9)', $html);
        $this->assertStringNotContainsString('required', $html);
    }

    #[Test]
    public function an_unknown_zone_is_the_application_s_and_a_label_of_its_own_wins(): void
    {
        $html = Blade::render('<x-webx-field type="datetime" name="meeting" label="Meeting" timezone="Mars/Olympus" timezone-label="Time is local to the sales office" />');

        $this->assertStringContainsString('>Time is local to the sales office</p>', $html);
        $this->assertStringContainsString('&quot;zone&quot;:&quot;UTC&quot;', $html);
    }

    #[Test]
    public function a_form_that_came_back_shows_the_day_and_the_time_and_the_error(): void
    {
        $this->app['session']->start();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $this->app['session.store']->flashInput(['fields' => ['meeting' => ['date' => '2030-01-07', 'time' => '10:30']]]);
        $this->app['view']->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['fields.meeting' => ['Choose a weekday: Mon–Fri.']])));

        $html = Blade::render('<x-webx-field type="datetime" name="fields.meeting" label="Meeting" />');

        $this->assertStringContainsString('value="2030-01-07"', $html);
        $this->assertStringContainsString('<option value="10:30" selected', $html);
        $this->assertStringContainsString('>Choose a weekday: Mon–Fri.</p>', $html);
        $this->assertStringContainsString('class="webx-field webx-field--datetime is-invalid"', $html);
    }
}
