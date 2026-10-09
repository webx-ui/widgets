<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Audit\AuditServiceProvider;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Fixes\AuditFixes;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Audit\BannerOn;
use WebxUi\Widgets\Audit\ThirdParty;
use WebxUi\Widgets\Audit\WaitForConsent;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Tests\Fixtures\AuditContent;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * §15.2: the widgets' checks in the site audit, on one small site that makes every mistake they
 * look for — and pages that make none, which stay quiet — and their two fixes.
 */
final class AuditTest extends TestCase
{
    private const BASE = 'https://shop.example.com';

    /** A player pasted into a post, as an editor pastes it: `&amp;` and all. */
    private const PASTED = '<p>Watch:</p><iframe width="560" src="https://www.youtube.com/embed/abc?si=1&amp;start=5" title="Tour"></iframe>';

    private const SNIPPET = '<script>(function(w,d){var s=d.createElement("script");s.src="https://www.googletagmanager.com/gtm.js?id=GTM-1";d.head.appendChild(s)})(window,document)</script>';

    private AuditContent $content;

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
            AuditServiceProvider::class,
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
        $app['config']->set('app.url', self::BASE);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'database');
        $app['config']->set('filesystems.links', []);
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->content = new AuditContent;
        $this->content->records = [
            'tour' => ['label' => 'Our tour', 'fields' => [
                'body' => self::PASTED,
                // A block tree, as module-blocks hands it over: JSON with HTML in its strings.
                'blocks' => ContentField::json([['type' => 'html', 'data' => ['code' => self::SNIPPET]]]),
            ]],
        ];
        $this->app->make(AuditContentSources::class)->register($this->content);

        $this->fakeSite();
    }

    #[Test]
    public function every_check_finds_its_mistake_and_only_there(): void
    {
        $this->artisan('webx:audit:run')->assertSuccessful();

        $this->assertSame([
            '/raw' => [
                'https://www.google.com/maps/embed?pb=1',
                'https://www.googletagmanager.com/gtm.js?id=GTM-1',
                'https://www.youtube.com/embed/abc?si=1&start=5',
                'https://www.youtube.com/s/player.js',
            ],
        ], $this->keys('widgets.before_consent'), 'The page that waits for consent, the own iframe and the unknown host stay quiet.');

        $this->assertSame(['/lightbox'], array_keys($this->keys('widgets.lightbox_size')));
        $this->assertSame(['/slider'], array_keys($this->keys('widgets.slider_pause')));
        $this->assertSame(['/contacts'], array_keys($this->keys('widgets.contact_both')));
        $this->assertSame([], $this->keys('widgets.banner_off'), 'The banner is on.');

        $issue = AuditIssue::query()->where('check', 'widgets.before_consent')->where('key', 'like', '%youtube.com/embed%')->firstOrFail();
        $this->assertSame('error', $issue->severity);
        $this->assertSame('webx-widgets::audit.before-consent', $issue->details['summary']['key']);
        $this->assertSame(['kind' => 'iframe', 'host' => 'www.youtube.com', 'category' => 'media'], $issue->details['summary']['params']);
        $this->assertStringContainsString('<iframe width="560"', (string) $issue->details['table']['rows'][0]['markup']);

        $unsized = AuditIssue::query()->where('check', 'widgets.lightbox_size')->firstOrFail();
        $this->assertSame(['count' => 2], $unsized->details['summary']['params'], 'No size, and a size of zero; the sized link is fine.');
        $this->assertCount(2, $unsized->details['table']['rows']);

        $slider = AuditIssue::query()->where('check', 'widgets.slider_pause')->firstOrFail();
        $this->assertSame(['count' => 1], $slider->details['summary']['params']);
        $this->assertStringStartsWith('<section class="webx-slider webx-slider--hero" id="lost"', (string) $slider->details['table']['rows'][0]['markup']);

        // What the reader keeps of a page: only what is there.
        $home = AuditPage::query()->where('url', self::BASE.'/')->firstOrFail();
        $this->assertSame(['waits' => ['media' => 2, 'statistics' => 1]], $home->fact('widgets'));
        $this->assertNull(AuditPage::query()->where('url', self::BASE.'/plain')->firstOrFail()->fact('widgets'));

        // Every check has its three texts, in the dictionary of the package.
        $this->actingAs($this->admin(['audit.view']), 'cms');
        $checks = array_column((array) $this->getJson(route('webx.audit.runs.checks', AuditRun::query()->latest('id')->firstOrFail()))->assertOk()->json('data'), null, 'id');
        $this->assertSame('Third parties that load before consent', $checks['widgets.before_consent']['title'] ?? null);
        $this->assertSame([WaitForConsent::ID], $checks['widgets.before_consent']['fixes'] ?? null);
        $this->assertSame([BannerOn::ID], app(AuditFixes::class)->forCheck('widgets.banner_off'));
    }

    #[Test]
    public function the_fix_makes_the_pasted_code_wait_where_it_is_stored(): void
    {
        $this->artisan('webx:audit:run')->assertSuccessful();

        $run = AuditRun::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin(['audit.view', 'audit.manage']), 'cms');

        $player = AuditIssue::query()->where('check', 'widgets.before_consent')->where('key', 'like', '%youtube.com/embed%')->firstOrFail();

        $this->getJson(route('webx.audit.runs.issues.fixes', [$run, $player->id]))
            ->assertOk()
            ->assertJsonPath('data.0.id', WaitForConsent::ID)
            ->assertJsonPath('data.0.total', 1)
            ->assertJsonPath('data.0.changes.0.label', 'Our tour')
            ->assertJsonPath('data.0.changes.0.field', 'body · en')
            ->assertJsonPath('data.0.changes.0.after', 'data-webx-consent="media"');

        $this->postJson(route('webx.audit.runs.issues.fixes.store', [$run, $player->id, WaitForConsent::ID]))->assertOk()->assertJsonPath('data.applied', true);

        $this->assertSame(
            '<p>Watch:</p><iframe width="560" data-src="https://www.youtube.com/embed/abc?si=1&amp;start=5" title="Tour" data-webx-consent="media"></iframe>',
            $this->content->replaced[0]['value'] ?? null,
        );

        $counter = AuditIssue::query()->where('check', 'widgets.before_consent')->where('key', 'like', '%gtm.js%')->firstOrFail();
        $this->postJson(route('webx.audit.runs.issues.fixes.store', [$run, $counter->id, WaitForConsent::ID]))->assertOk();

        $blocks = json_decode((string) ($this->content->replaced[1]['value'] ?? ''), true);
        $this->assertSame('blocks', $this->content->replaced[1]['field'] ?? null);
        $this->assertStringStartsWith('<script type="text/plain" data-webx-consent="statistics">(function(w,d)', $blocks[0]['data']['code'] ?? '');

        // What a template prints is in no field: no fix to offer.
        $map = AuditIssue::query()->where('check', 'widgets.before_consent')->where('key', 'like', '%google.com/maps%')->firstOrFail();
        $this->getJson(route('webx.audit.runs.issues.fixes', [$run, $map->id]))->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function a_banner_switched_off_is_one_finding_for_the_site_and_the_fix_turns_it_on(): void
    {
        app(Settings::class)->save(['consent.enabled' => false]);

        $this->artisan('webx:audit:run')->assertSuccessful();

        $this->assertSame([], $this->keys('widgets.before_consent'), 'With the banner off nothing waits — the one finding says so.');

        $off = AuditIssue::query()->where('check', 'widgets.banner_off')->firstOrFail();
        $this->assertNull($off->url);
        $this->assertSame('warning', $off->severity);
        $this->assertSame(['count' => 2], $off->details['summary']['params'], 'The home page with what waits, the page that asks at once.');
        $this->assertSame(
            [[self::BASE.'/', 'media, statistics'], [self::BASE.'/raw', 'www.youtube.com, www.googletagmanager.com, www.google.com']],
            array_map(static fn (array $row): array => [$row['url'], $row['value']], $off->details['table']['rows']),
        );

        $run = AuditRun::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin(['audit.view', 'audit.manage']), 'cms');

        $this->getJson(route('webx.audit.runs.issues.fixes', [$run, $off->id]))
            ->assertOk()
            ->assertJsonPath('data.0.id', BannerOn::ID)
            ->assertJsonPath('data.0.changes.0.label', 'Cookie banner')
            ->assertJsonPath('data.0.changes.0.before', 'Off')
            ->assertJsonPath('data.0.changes.0.after', 'On');

        $this->postJson(route('webx.audit.runs.issues.fixes.store', [$run, $off->id, BannerOn::ID]))->assertOk();

        $this->assertTrue(app(Consent::class)->enabled());
        $this->assertSame([], $this->getJson(route('webx.audit.runs.issues.fixes', [$run, $off->id]))->json('data'), 'On already: nothing to offer.');
    }

    #[Test]
    public function the_known_third_parties_by_host_and_path(): void
    {
        $this->assertSame('media', ThirdParty::category('https://m.youtube.com/watch?v=1'));
        $this->assertSame('media', ThirdParty::category('//player.vimeo.com/video/1'));
        $this->assertSame('media', ThirdParty::category('https://www.google.com/maps/embed?pb=1'));
        $this->assertNull(ThirdParty::category('https://www.google.com/search?q=maps'), 'Google is not a map; its maps are.');
        $this->assertNull(ThirdParty::category('https://notyoutube.com/embed/1'));
        $this->assertNull(ThirdParty::category('/embed/own'));
        $this->assertSame('statistics', ThirdParty::category('https://www.googletagmanager.com/gtag/js?id=G-1'));
        $this->assertSame('marketing', ThirdParty::category('https://connect.facebook.net/en_US/fbevents.js'));

        config()->set('webx-widgets.audit.third-party', ['widget.example.org' => 'marketing']);
        $this->assertSame('marketing', ThirdParty::category('https://cdn.widget.example.org/w.js'), 'A site adds its own.');

        $this->assertSame('https://mc.yandex.ru/metrika/tag.js', ThirdParty::inText('k=e.createElement(t),k.src="//mc.yandex.ru/metrika/tag.js"'));
        $this->assertNull(ThirdParty::inText('fetch("/api/ping")'));
    }

    /**
     * The findings of a check, by page path, with their keys.
     *
     * @return array<string, list<string>>
     */
    private function keys(string $check): array
    {
        $found = [];

        foreach (AuditIssue::query()->where('check', $check)->orderBy('key')->get() as $issue) {
            $found[substr((string) $issue->url, strlen(self::BASE)) ?: ''][] = (string) $issue->key;
        }

        ksort($found);

        return $found;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function admin(array $permissions): CmsUser
    {
        $user = CmsUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => 'auditor-'.bin2hex(random_bytes(4)), 'name' => 'Auditor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    private function fakeSite(): void
    {
        $html = ['Content-Type' => 'text/html; charset=utf-8'];
        $page = static fn (string $title, string $body): string => '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'.$title.'</title></head><body><h1>'.$title.'</h1>'.$body.'</body></html>';
        $slide = '<div class="webx-slider__slide">A</div><div class="webx-slider__slide">B</div>';
        $pause = '<button type="button" class="webx-slider__button webx-slider__pause">Pause</button>';

        $site = [
            '/' => $page('Home', '<a href="/raw">Raw</a> <a href="/lightbox">Lightbox</a> <a href="/slider">Slider</a> <a href="/contacts">Contacts</a> <a href="/plain">Plain</a>'
                // What waits as it should: the video and the map widgets, hand-made code marked.
                .'<div class="webx-video" data-webx-video="{}" data-webx-consent="media"><a class="webx-video__facade" href="https://www.youtube.com/watch?v=x">Play</a></div>'
                .'<iframe data-webx-consent="media" data-src="https://www.youtube-nocookie.com/embed/x" title="x"></iframe>'
                .'<script type="text/plain" data-webx-consent="statistics" src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>'
                .'<script type="text/plain" data-webx-consent="necessary">window.ok = 1</script>'
                .'<a href="/a.jpg" data-webx-lightbox="g" data-width="1800" data-height="1200">A</a>'
                .'<section class="webx-slider webx-slider--logos" data-webx-slider=\'{"autoplay":0,"continuous":true}\'>'.$slide.$pause.'</section>'
                .'<section class="webx-slider webx-slider--cards" data-webx-slider=\'{"autoplay":0,"continuous":false}\'>'.$slide.'</section>'
                .'<div class="webx-contact-button"></div>'),
            '/raw' => $page('Raw', self::PASTED.self::SNIPPET
                .'<iframe src="https://www.google.com/maps/embed?pb=1" title="Map"></iframe>'
                .'<script src="https://www.youtube.com/s/player.js"></script>'
                .'<iframe src="/embed/own" title="Own"></iframe>'
                .'<iframe src="https://widgets.unknown.example.org/w" title="Unknown"></iframe>'
                .'<script type="application/ld+json">{"sameAs":["https://www.youtube.com/@shop"]}</script>'
                // Marked, but asking anyway: still a finding.
                .'<iframe data-webx-consent="media" src="https://www.youtube.com/embed/abc?si=1&amp;start=5" title="Twice"></iframe>'),
            '/lightbox' => $page('Lightbox', '<a href="/a.jpg" data-webx-lightbox="g">A</a> <a href="/b.jpg" data-webx-lightbox="g" data-width="0" data-height="900">B</a>'
                .'<a href="/c.jpg" data-webx-lightbox="g" data-width="900" data-height="600">C</a>'),
            '/slider' => $page('Slider', '<section class="webx-slider webx-slider--hero" id="lost" data-webx-slider=\'{"autoplay":6000,"continuous":false}\'>'.$slide.'</section>'
                .'<section class="webx-slider webx-slider--hero" data-webx-slider=\'{"autoplay":6000}\'><div class="webx-slider__slide">Only one</div></section>'),
            '/contacts' => $page('Contacts', '<div class="webx-contact-button"></div><div class="webx-contact-bar"></div>'),
            '/plain' => $page('Plain', '<p>Nothing of the widgets here.</p>'),
        ];

        Http::fake(static function (Request $request) use ($site, $html) {
            $url = $request->url();
            $body = str_starts_with($url, self::BASE.'/') ? ($site[substr($url, strlen(self::BASE))] ?? null) : null;

            return $body === null ? Http::response('Not found', 404) : Http::response($body, 200, $html);
        });
    }
}
