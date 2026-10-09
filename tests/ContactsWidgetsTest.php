<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Settings\Contacts\Hours;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Contacts\HoursView;
use WebxUi\Widgets\Widgets;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * Spec §12.2–§12.5: the contacts widgets on the data of the Contacts tab — the markup they
 * print, the `tel:` links in E.164, the hours' words, and nothing at all without data.
 */
final class ContactsWidgetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            AdminServiceProvider::class,
            McpServiceProvider::class,
            AuthServiceProvider::class,
            SettingsServiceProvider::class,
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
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'de']]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/contacts', static fn (): string => Blade::render('<x-layout><x-webx-phones /><x-webx-socials /></x-layout>'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_main_number_dials_in_e164_and_the_others_are_in_the_dropdown(): void
    {
        $this->contacts();

        $html = Blade::render('<x-webx-phones callback="callback" />');

        $this->assertStringContainsString('class="webx-phones webx-phones--dropdown"', $html);
        $this->assertStringContainsString('<a class="webx-phones__number" href="tel:+44-20-7946-0958">+44 20 7946 0958</a>', $html);
        $this->assertStringContainsString('<details data-webx-dropdown="click" class="webx-dropdown webx-dropdown--bottom-end webx-phones__dropdown">', $html);
        $this->assertStringContainsString('aria-label="More numbers"', $html);
        $this->assertStringContainsString('<a class="webx-phones__number" href="tel:+1-202-555-0143">+1 202 555 0143</a>', $html);
        $this->assertStringContainsString('<span class="webx-phones__label">Support</span>', $html);
        // A number's messengers open a chat with that number.
        $this->assertStringContainsString('href="https://wa.me/442079460958" target="_blank" rel="noopener" aria-label="WhatsApp, +44 20 7946 0958"', $html);
        $this->assertStringContainsString('<a class="webx-phones__callback" href="#webx-form-callback">Request a call</a>', $html);

        $this->assertSame(['dropdown', 'contacts'], array_values(array_intersect(['dropdown', 'contacts'], app(Widgets::class)->claimed())));
        $this->assertSame(['callback'], app(Widgets::class)->forms());
    }

    #[Test]
    public function compact_is_a_handset_and_list_is_every_number(): void
    {
        $this->contacts();

        $compact = Blade::render('<x-webx-phones compact />');
        $this->assertStringContainsString('webx-phones--compact', $compact);
        $this->assertStringContainsString('aria-label="Phone numbers"', $compact);
        $this->assertSame(2, substr_count($compact, 'class="webx-phones__item"'));

        $list = Blade::render('<x-webx-phones layout="list" more="x" label="Call us" />');
        $this->assertStringContainsString('<ul class="webx-phones__list" aria-label="Call us">', $list);
        $this->assertStringNotContainsString('webx-dropdown', $list);
        $this->assertSame(2, substr_count($list, 'class="webx-phones__item"'));
        // The main number first, whatever its place in the list.
        $this->assertLessThan(strpos($list, '+1 202'), strpos($list, '+44 20'));

        $this->expectExceptionMessage('<x-webx-phones layout="grid">: dropdown or list.');
        Blade::render('<x-webx-phones layout="grid" />');
    }

    #[Test]
    public function without_contacts_the_widgets_print_nothing(): void
    {
        $html = Blade::render('<x-webx-phones /><x-webx-hours /><x-webx-socials /><x-webx-contact-button /><x-webx-contact-bar />');

        $this->assertSame('', trim($html));
        $this->assertNotContains('contacts', app(Widgets::class)->claimed());
    }

    #[Test]
    public function the_hours_say_the_status_and_fold_the_week(): void
    {
        $this->contacts();
        // Monday 12 October 2026, 10:00 in London.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 10:00', 'Europe/London'));

        $html = Blade::render('<x-webx-hours />');

        $this->assertStringContainsString('<span class="webx-hours__status is-open" data-webx-hours-status>Open until 7:00 PM</span>', $html);
        $this->assertStringContainsString('<th class="webx-hours__days" scope="row">Mon–Fri</th>', $html);
        $this->assertStringContainsString('<td class="webx-hours__times">9:00 AM–7:00 PM</td>', $html);
        $this->assertStringContainsString('<tr class="webx-hours__day is-today" data-weekdays="1,2,3,4,5">', $html);
        $this->assertStringContainsString('<td class="webx-hours__times">Closed</td>', $html);
        $this->assertStringContainsString('&quot;zone&quot;:&quot;Europe/London&quot;', $html);
        $this->assertStringContainsString('&quot;later&quot;:&quot;Closed, opens :day at :time&quot;', $html);

        // The page's language writes the time its way: 24 hours in German.
        app()->setLocale('de');
        $german = Blade::render('<x-webx-hours layout="table" />');
        $this->assertStringContainsString('<p class="webx-hours__status is-open" data-webx-hours-status>Geöffnet bis 19:00</p>', $german);
        $this->assertStringContainsString('Mo.–Fr.', $german);
    }

    #[Test]
    public function the_hours_name_the_day_they_open_next(): void
    {
        $view = new HoursView(Hours::fromSettings(
            [['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '09:00', 'closes' => '19:00']],
            [['date' => '2026-10-13', 'closed' => true, 'label' => 'Founders day']],
            'Europe/London',
        ), 'en');
        $london = static fn (string $at): CarbonImmutable => CarbonImmutable::parse($at, 'Europe/London');

        $this->assertSame('Closed, opens Wednesday at 9:00 AM', $view->status($london('2026-10-12 20:00'))['text']);
        $this->assertSame('Closed today, opens tomorrow at 9:00 AM', $view->status($london('2026-10-13 12:00'))['text']);
        $this->assertSame('Closed, opens tomorrow at 9:00 AM', $view->status($london('2026-10-14 20:00'))['text']);
        $this->assertSame([['date' => 'Tue, 13 Oct', 'iso' => '2026-10-13', 'hours' => 'Closed', 'label' => 'Founders day', 'today' => false]], $view->special($london('2026-10-12 10:00')));
    }

    #[Test]
    public function quick_contact_lists_the_chats_the_number_the_email_and_the_form(): void
    {
        $this->contacts();

        $button = Blade::render('<x-webx-contact-button form="callback" />');

        $this->assertStringContainsString('<div data-webx-contact-button="" class="webx-contact-button webx-contact-button--bottom-end">', $button);
        $this->assertStringContainsString('webx-dropdown--top-end', $button);
        $this->assertStringContainsString('aria-label="Contact us"', $button);

        preg_match_all('/webx-contact-button__item--([a-z]+)/', $button, $kinds);
        $this->assertSame(['whatsapp', 'telegram', 'phone', 'email', 'form'], $kinds[1]);
        $this->assertStringContainsString('href="https://t.me/example_bot" target="_blank" rel="noopener"', $button);
        $this->assertStringContainsString('href="#webx-form-callback"', $button);

        $only = Blade::render('<x-webx-contact-button corner="bottom-start" :items="[\'phone\']" />');
        $this->assertStringContainsString('webx-dropdown--top-start', $only);
        $this->assertSame(1, substr_count($only, 'webx-contact-button__item '));

        $bar = Blade::render('<x-webx-contact-bar form="callback" :breakpoint="600" />');
        $this->assertStringContainsString('@container (min-width: 600px)', $bar);
        $this->assertStringContainsString('class="webx-contact-bar__spacer"', $bar);
        preg_match_all('/webx-contact-bar__item--([a-z]+)" href="([^"]+)"/', $bar, $items);
        $this->assertSame(['call', 'write', 'form'], $items[1]);
        $this->assertSame(['tel:+44-20-7946-0958', 'https://wa.me/442079460958', '#webx-form-callback'], $items[2]);
    }

    #[Test]
    public function the_networks_are_icons_with_their_names(): void
    {
        $this->contacts();

        $html = Blade::render('<x-webx-socials />');

        $this->assertStringContainsString('<nav class="webx-socials" aria-label="Social networks">', $html);
        $this->assertStringContainsString('<a class="webx-socials__link webx-socials__link--instagram" href="https://www.instagram.com/example" target="_blank" rel="noopener" aria-label="Instagram"><svg class="webx-icon"', $html);
        // A network without a picture of its own gets the generic link.
        $this->assertStringContainsString('aria-label="Our forum"', $html);
        $this->assertSame(2, substr_count($html, '<svg class="webx-icon"'));

        $this->expectExceptionMessage('<x-webx-icon name="nope">: there is no such icon.');
        Blade::render('<x-webx-icon name="nope" />');
    }

    #[Test]
    public function a_page_with_contacts_loads_their_files(): void
    {
        $this->contacts();
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]+/contacts\.css">~', $html);
        $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/contacts\.js"></script>~', $html);
    }

    private function contacts(): void
    {
        app(Settings::class)->save([
            'contacts.phones' => [
                ['number' => '+1 202 555 0143', 'label' => ['en' => 'Support'], 'primary' => false, 'messengers' => []],
                ['number' => '+44 20 7946 0958', 'label' => ['en' => 'Sales'], 'primary' => true, 'messengers' => ['whatsapp']],
            ],
            'contacts.emails' => [['email' => 'hello@example.com']],
            'contacts.hours' => [['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '09:00', 'closes' => '19:00']],
            'contacts.timezone' => 'Europe/London',
            'contacts.messengers' => [['channel' => 'telegram', 'url' => 'https://t.me/example_bot']],
            'contacts.socials' => [
                ['network' => 'instagram', 'url' => 'https://www.instagram.com/example'],
                ['network' => 'other', 'url' => 'https://forum.example.com', 'label' => ['en' => 'Our forum']],
            ],
        ]);
    }
}
