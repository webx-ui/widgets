<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Encryption\Encrypter;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Facades\Consent as ConsentFacade;

/**
 * Spec §9: the banner on every page, the answer read on the server, the version of the policy,
 * Global Privacy Control, and code that waits for an answer.
 */
final class ConsentTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/plain', static fn (): string => Blade::render('<x-layout><p>Hello</p></x-layout>'));
        $router->get('/video', static fn (): string => Blade::render('<x-layout><iframe data-webx-consent="media" data-src="https://www.youtube-nocookie.com/embed/x"></iframe></x-layout>'));
        $router->get('/gate', static fn (): string => Blade::render('<x-layout><x-webx-consent category="statistics"><script src="https://counter.example/c.js"></script></x-webx-consent></x-layout>'));
        $router->get('/link', static fn (): string => Blade::render('<x-layout><footer><x-webx-consent-link class="site-footer__link" /></footer></x-layout>'));
        $router->get('/has', static fn (): string => json_encode(array_map(ConsentFacade::has(...), Consent::CATEGORIES), JSON_THROW_ON_ERROR));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('webx:theme:sync')->assertSuccessful();
    }

    #[Test]
    public function the_banner_and_its_dialog_are_on_every_page_hidden_until_the_script_reads_the_cookie(): void
    {
        $html = (string) $this->get('/plain')->assertOk()->getContent();

        $this->assertStringContainsString('data-webx-consent-root data-version="1" data-lifetime="365">', $html);
        $this->assertMatchesRegularExpression('/<section class="webx-consent"[^>]*data-webx-consent-banner hidden>/', $html);
        $this->assertStringContainsString('<dialog class="webx-dialog webx-consent-dialog" id="webx-consent"', $html);

        // "Reject all" and "Accept all": the same element, the same class — the same weight.
        $this->assertStringContainsString('<button type="button" class="webx-consent__button" data-webx-consent-reject>Reject all</button>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-consent__button" data-webx-consent-accept>Accept all</button>', $html);
        $this->assertStringContainsString('data-webx-dialog="webx-consent">Customize</button>', $html);

        // Necessary is always on and cannot be switched off; nothing else is on.
        $this->assertMatchesRegularExpression('/name="necessary"[^>]* checked disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="(?:statistics|marketing|media|preferences)"[^>]* checked/', $html);
    }

    #[Test]
    public function a_category_nothing_on_the_site_uses_is_not_offered_and_one_seen_once_stays_offered(): void
    {
        $plain = (string) $this->get('/plain')->getContent();
        $this->assertStringContainsString('data-webx-consent-row="media" hidden', $plain);
        $this->assertStringContainsString('data-webx-consent-row="necessary">', $plain);

        $this->assertStringContainsString('data-webx-consent-row="media">', (string) $this->get('/video')->getContent());

        // The site has a video now: the dialog on any page offers media.
        $this->assertStringContainsString('data-webx-consent-row="media">', (string) $this->get('/plain')->getContent());
        $this->assertStringContainsString('data-webx-consent-row="statistics" hidden', (string) $this->get('/plain')->getContent());
    }

    #[Test]
    public function the_categories_chosen_in_the_settings_are_the_ones_offered(): void
    {
        config()->set('webx-widgets.consent.categories', ['statistics']);

        $html = (string) $this->get('/video')->getContent();

        $this->assertStringContainsString('data-webx-consent-row="statistics">', $html);
        $this->assertStringContainsString('data-webx-consent-row="media" hidden', $html);
    }

    #[Test]
    public function the_server_reads_the_answer_to_the_current_version_only(): void
    {
        $this->assertSame('[true,false,false,false,false]', $this->get('/has')->getContent());

        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => 1, 'd' => '2026-10-09', 'c' => ['media', 'statistics', 'bogus']], JSON_THROW_ON_ERROR));
        $this->assertSame('[true,false,true,false,true]', $this->get('/has')->getContent());

        // The policy changed: an answer to version 1 is no answer.
        config()->set('webx-widgets.consent.version', 2);
        $this->assertSame('[true,false,false,false,false]', $this->get('/has')->getContent());

        $this->withUnencryptedCookie(Consent::COOKIE, '{not json');
        $this->assertSame('[true,false,false,false,false]', $this->get('/has')->getContent());
    }

    #[Test]
    public function the_answer_survives_cookie_encryption_because_it_is_not_encrypted(): void
    {
        $this->assertTrue((new EncryptCookies(new Encrypter(random_bytes(32), 'aes-256-cbc')))->isDisabled(Consent::COOKIE));
    }

    #[Test]
    public function the_dialog_shows_what_was_agreed_to(): void
    {
        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => '1', 'd' => '2026-10-09', 'c' => ['marketing']], JSON_THROW_ON_ERROR));

        $html = (string) $this->get('/plain')->getContent();

        $this->assertMatchesRegularExpression('/name="marketing"[^>]* checked/', $html);
        $this->assertStringContainsString('data-webx-consent-row="marketing">', $html);
    }

    #[Test]
    public function with_the_banner_off_everything_is_allowed_and_nothing_is_asked(): void
    {
        config()->set('webx-widgets.consent.enabled', false);

        $this->assertSame('[true,true,true,true,true]', $this->get('/has')->getContent());

        $html = (string) $this->get('/plain')->getContent();
        $this->assertStringContainsString('data-webx-consent-root data-version="1" data-lifetime="365" data-off>', $html);
        $this->assertStringNotContainsString('webx-consent__button', $html);
        $this->assertStringNotContainsString('"consent","default"', $html);
        // The script still comes: it is what switches on the code that waits.
        $this->assertStringContainsString('consent.js', $html);

        $this->assertStringNotContainsString('webx-consent-link', (string) $this->get('/link')->getContent());
    }

    #[Test]
    public function global_privacy_control_is_told_to_the_script_and_shown_in_the_dialog(): void
    {
        $html = (string) $this->withHeader('Sec-GPC', '1')->get('/plain')->getContent();

        $this->assertStringContainsString(' data-gpc>', $html);
        $this->assertStringContainsString('<p class="webx-consent-dialog__gpc" data-webx-consent-gpc>', $html);

        $this->flushHeaders();
        $this->assertStringContainsString('<p class="webx-consent-dialog__gpc" data-webx-consent-gpc hidden>', (string) $this->get('/plain')->getContent());
    }

    #[Test]
    public function google_consent_mode_defaults_to_denied_and_follows_the_answer(): void
    {
        $html = (string) $this->get('/plain')->getContent();
        $this->assertStringContainsString('"analytics_storage":"denied"', $html);
        $this->assertStringContainsString('"ad_storage":"denied"', $html);
        $this->assertStringContainsString('"security_storage":"granted"', $html);

        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => '1', 'c' => ['statistics']], JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('"analytics_storage":"granted"', (string) $this->get('/plain')->getContent());
    }

    #[Test]
    public function code_that_waits_is_inert_until_agreed_to_and_printed_as_it_is_after(): void
    {
        $this->assertStringContainsString('<template data-webx-consent="statistics"><script src="https://counter.example/c.js"></script></template>', (string) $this->get('/gate')->getContent());

        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => '1', 'c' => ['statistics']], JSON_THROW_ON_ERROR));
        $html = (string) $this->get('/gate')->getContent();
        $this->assertStringContainsString('<script src="https://counter.example/c.js"></script>', $html);
        $this->assertStringNotContainsString('<template data-webx-consent', $html);
    }

    #[Test]
    public function a_category_that_does_not_exist_is_a_typo_not_a_block_forever(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConsentFacade::has('analytics');
    }

    #[Test]
    public function the_footer_link_reopens_the_dialog(): void
    {
        $this->assertStringContainsString(
            '<a href="#webx-consent" data-webx-dialog="webx-consent" class="webx-consent-link site-footer__link">Cookie settings</a>',
            (string) $this->get('/link')->getContent(),
        );
    }

    #[Test]
    public function the_words_are_the_settings_then_the_dictionary_and_the_policy_is_linked(): void
    {
        config()->set('webx-widgets.consent.texts', ['title' => ['en' => 'We bake cookies'], 'media' => 'Videos from elsewhere']);
        config()->set('webx-widgets.consent.policy', '/cookies');

        $html = (string) $this->get('/plain')->getContent();

        $this->assertStringContainsString('>We bake cookies</h2>', $html);
        $this->assertStringContainsString('>Videos from elsewhere</p>', $html);
        $this->assertStringContainsString('>The session, protection against forged requests and this answer itself. The site does not work without them.</p>', $html);
        $this->assertStringContainsString('<a class="webx-consent__policy" href="/cookies">Cookie policy</a>', $html);

        app()->setLocale('ru');
        $this->assertStringContainsString('>Принять все</button>', (string) $this->get('/plain')->getContent());
    }
}
