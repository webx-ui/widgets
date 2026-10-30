<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Captcha;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The site's captcha (ORDERS §28.1): where the provider and the keys come from, the check, what a
 * form draws, and the Captcha tab — with the secret never stored and never shown.
 */
final class CaptchaTest extends TestCase
{
    use MigratesOnce;

    private const string RECAPTCHA = 'https://www.google.com/recaptcha/api/siteverify';

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

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('webx-widgets.captcha.recaptcha.key', 'site-key');
        config()->set('webx-widgets.captcha.recaptcha.secret', 'site-secret');
    }

    #[Test]
    public function no_provider_is_no_captcha_and_a_form_that_wants_one_is_refused(): void
    {
        $captcha = app(Captcha::class);

        $this->assertNull($captcha->provider());
        $this->assertNull($captcha->widget('contact'));
        $this->assertSame('unconfigured', $captcha->check(Request::create('/', 'POST'), 'contact')['reason'] ?? null);
    }

    #[Test]
    public function the_settings_choose_the_provider_and_off_there_wins_over_the_config(): void
    {
        config()->set('webx-widgets.captcha.provider', 'turnstile');
        $captcha = app(Captcha::class);

        $this->assertSame('turnstile', $captcha->provider());
        $this->assertFalse($captcha->chosen());

        app(Settings::class)->save(['captcha.provider' => 'recaptcha']);
        $this->assertSame('recaptcha', $captcha->provider());
        $this->assertTrue($captcha->chosen());

        app(Settings::class)->save(['captcha.provider' => 'off']);
        $this->assertNull($captcha->provider());
    }

    #[Test]
    public function the_keys_of_the_inbox_of_before_are_read_when_the_sites_are_not_set(): void
    {
        config()->set('webx-widgets.captcha.turnstile', ['key' => null, 'secret' => null, 'mode' => null, 'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify', 'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js']);
        config()->set('webx-inbox.captcha.turnstile', ['key' => 'old-key', 'secret' => 'old-secret', 'mode' => 'invisible', 'script' => 'https://mirror.test/api.js']);
        $captcha = app(Captcha::class);

        $this->assertTrue($captcha->configured('turnstile'));
        $this->assertSame('old-key', $captcha->key('turnstile'));
        $this->assertSame('invisible', $captcha->type('turnstile'));
        // A mirror the site set for the inbox is kept.
        $this->assertSame('https://mirror.test/api.js', $captcha->script('turnstile'));
        // Once per page.
        $this->assertNull($captcha->script('turnstile'));

        // The site key of the tab wins; the secret has no place there.
        app(Settings::class)->save(['captcha.turnstile.key' => 'panel-key']);
        $this->assertSame('panel-key', $captcha->key('turnstile'));
    }

    #[Test]
    public function the_environment_names_of_the_inbox_are_the_fallback_of_the_sites(): void
    {
        putenv('WEBX_INBOX_RECAPTCHA_SECRET=old-secret');
        putenv('WEBX_CAPTCHA_RECAPTCHA_KEY=new-key');

        try {
            $config = (require Widgets::path().'/config/webx-widgets.php')['captcha'];
        } finally {
            putenv('WEBX_INBOX_RECAPTCHA_SECRET');
            putenv('WEBX_CAPTCHA_RECAPTCHA_KEY');
        }

        $this->assertSame('old-secret', $config['recaptcha']['secret']);
        $this->assertSame('new-key', $config['recaptcha']['key']);
        $this->assertNull($config['provider']);
    }

    #[Test]
    public function an_answer_is_verified_with_the_secret_and_a_v3_token_for_another_form_is_refused(): void
    {
        $captcha = app(Captcha::class);
        $request = static fn (array $input): Request => Request::create('/', 'POST', $input, server: ['REMOTE_ADDR' => '10.0.0.1']);

        $missing = $captcha->check($request([]), 'contact', 'recaptcha');
        $this->assertSame('missing', $missing['reason'] ?? null);
        $this->assertSame('Please confirm you are not a robot and send the form again.', $captcha->message($missing ?? []));

        Http::fake([self::RECAPTCHA => Http::sequence()
            ->push(['success' => true])
            ->push(['success' => true, 'score' => 0.9, 'action' => 'login'])]);
        $this->assertNull($captcha->check($request(['g-recaptcha-response' => 'token']), 'contact', 'recaptcha'));
        Http::assertSent(static fn (ClientRequest $sent): bool => $sent['secret'] === 'site-secret' && $sent['remoteip'] === '10.0.0.1');

        config()->set('webx-widgets.captcha.recaptcha.type', 'v3');
        $refused = $captcha->check($request(['g-recaptcha-response' => 'token']), 'checkout', 'recaptcha');

        $this->assertSame('failed', $refused['reason'] ?? null);
        $this->assertSame('checkout', $refused['expected_action'] ?? null);
        // A score is not something the visitor can do anything about.
        $this->assertNull($captcha->message($refused ?? []));
    }

    #[Test]
    public function the_component_draws_the_sites_widget_and_claims_its_script(): void
    {
        config()->set('webx-widgets.captcha.provider', 'recaptcha');

        $html = Blade::render('<x-webx-captcha action="checkout" id="place-captcha" />');

        $this->assertStringContainsString('data-webx-field="captcha"', $html);
        $this->assertStringContainsString('data-webx-captcha="recaptcha"', $html);
        $this->assertStringContainsString('data-webx-captcha-action="checkout"', $html);
        $this->assertStringContainsString('<div class="g-recaptcha" data-sitekey="site-key"></div>', $html);
        $this->assertStringContainsString('id="place-captcha-error"', $html);
        $this->assertStringContainsString('src="https://www.google.com/recaptcha/api.js"', $html);
        $this->assertStringNotContainsString('site-secret', $html);
        $this->assertContains('captcha', Widgets::claimed());

        // No provider — nothing at all.
        config()->set('webx-widgets.captcha.provider', null);
        $this->assertSame('', trim(Blade::render('<x-webx-captcha action="checkout" />')));
    }

    #[Test]
    public function the_tab_holds_no_secret_and_an_agent_learns_only_whether_it_is_set(): void
    {
        $names = array_column(app(ScreenRegistry::class)->fields(Settings::SCREEN), 'name');

        foreach (['captcha.provider', 'captcha.recaptcha.type', 'captcha.recaptcha.key', 'captcha.turnstile.mode', 'captcha.turnstile.key'] as $key) {
            $this->assertContains($key, $names);
        }

        $this->assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_contains($name, 'secret'))));
        $this->assertSame('Captcha', __('webx-widgets::panel.captcha.tab'));

        $get = app(ToolRegistry::class)->tool('settings_get')->tool->handler;
        $answer = $get(['key' => 'captcha.provider']);

        $this->assertTrue($answer['captcha']['providers']['recaptcha']['secret']);
        $this->assertFalse($answer['captcha']['providers']['turnstile']['secret']);
        $this->assertStringNotContainsString('site-secret', (string) json_encode($answer));
    }
}
