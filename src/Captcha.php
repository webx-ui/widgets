<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The site's captcha: reCAPTCHA or Turnstile, one provider and one pair of keys for every form
 * that wants one (spec ORDERS §28.1, decision 72) — the inbox's forms, the checkout, the sign-in.
 * Those only say whether they want it; which provider is the site's.
 *
 * Where each value comes from, first found wins:
 *
 * - the provider, the kind of reCAPTCHA key, Turnstile's mode, the site key — the "Captcha" tab
 *   of `module-settings` (`captcha.*`), then `webx-widgets.captcha`, then a published
 *   `webx-inbox.captcha` (the keys were the inbox's before they were the site's);
 * - the secret — the environment only, through the same two configs: it never goes into the
 *   database, and an agent reading the settings learns only whether it is set.
 *
 * A form that wants a captcha the site has no secret for refuses submissions rather than waving
 * them through — a rubber stamp would be worse than no captcha, because nobody would know. The
 * refusal is written to the log where somebody may see it. A provider that cannot be reached is
 * the site's problem, not the visitor's: the submission is let through, and the log says so.
 */
final class Captcha
{
    public const string RECAPTCHA = 'recaptcha';

    public const string TURNSTILE = 'turnstile';

    public const array PROVIDERS = [self::RECAPTCHA, self::TURNSTILE];

    public const string CHECKBOX = 'checkbox';

    public const string INVISIBLE = 'invisible';

    public const string V3 = 'v3';

    public const array RECAPTCHA_TYPES = [self::CHECKBOX, self::INVISIBLE, self::V3];

    public const array TURNSTILE_MODES = ['managed', self::INVISIBLE];

    /** What a form posts its answer as: each provider's own name, its script writes it. */
    public const array RESPONSE_FIELDS = [
        self::RECAPTCHA => 'g-recaptcha-response',
        self::TURNSTILE => 'cf-turnstile-response',
    ];

    /** The settings of the "Captcha" tab: `off` there is the site saying "none", not "not set". */
    public const string SETTING_PROVIDER = 'captcha.provider';

    public const string OFF = 'off';

    /** Why a submission was refused: no secret to verify with, no answer, or a wrong one. */
    public const string UNCONFIGURED = 'unconfigured';

    public const string MISSING = 'missing';

    public const string FAILED = 'failed';

    /**
     * The class each provider's own script looks for when it goes hunting for widgets.
     *
     * Only for the widgets that script draws by itself. An invisible widget of either provider
     * drawn that way would want a global function to call with its token, so it gets a class
     * nobody hunts for and captcha.js draws it on submit; v3 has no widget at all.
     */
    private const array WIDGETS = [
        self::RECAPTCHA => 'g-recaptcha',
        self::TURNSTILE => 'cf-turnstile',
    ];

    /** The addresses this package ships, so a mirror set in a published inbox config is not lost. */
    private const array SHIPPED = [
        'recaptcha.verify' => 'https://www.google.com/recaptcha/api/siteverify',
        'recaptcha.script' => 'https://www.google.com/recaptcha/api.js',
        'turnstile.verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'turnstile.script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
    ];

    /** The settings class of `module-settings`, named rather than imported: it is not required. */
    private const string SETTINGS = 'WebxUi\Settings\Settings';

    /** @var array<string, true> the providers whose script this page already printed */
    private array $printed = [];

    public function __construct(
        private readonly Container $app,
        private readonly Config $config,
        private readonly Http $http,
        private readonly LoggerInterface $log,
    ) {}

    /** The site's provider, or null when it has none. */
    public function provider(): ?string
    {
        $chosen = $this->panel(self::SETTING_PROVIDER);

        if ($chosen === self::OFF) {
            return null;
        }

        $provider = $chosen ?? $this->config->get('webx-widgets.captcha.provider');

        return is_string($provider) && in_array($provider, self::PROVIDERS, true) ? $provider : null;
    }

    /** Whether the site chose a provider in its settings, rather than leaving it to the config. */
    public function chosen(): bool
    {
        return $this->panel(self::SETTING_PROVIDER) !== null;
    }

    /**
     * How the widget runs: one of {@see RECAPTCHA_TYPES} for reCAPTCHA, which is the kind of key the
     * site has; for Turnstile `invisible` when its mode says so — run on submit — and otherwise the
     * checkbox, drawn when the page opens. A value nobody recognises is the checkbox, because it is
     * what the widget used to be.
     */
    public function type(string $provider): string
    {
        if ($provider === self::TURNSTILE) {
            return $this->value(self::TURNSTILE, 'mode', panel: true) === self::INVISIBLE ? self::INVISIBLE : self::CHECKBOX;
        }

        $type = $this->value(self::RECAPTCHA, 'type', panel: true);

        return is_string($type) && in_array($type, self::RECAPTCHA_TYPES, true) ? $type : self::CHECKBOX;
    }

    /** The public half of a provider's keys, the one the widget is drawn with. */
    public function key(string $provider): ?string
    {
        $key = $this->value($provider, 'key', panel: true);

        return is_string($key) && $key !== '' ? $key : null;
    }

    /** Whether the site holds both halves of a provider's keys. */
    public function configured(string $provider): bool
    {
        return $this->missing($provider) === [];
    }

    /**
     * The halves of a provider's keys the site does not have: `key`, `secret`, both or none.
     *
     * @return list<'key'|'secret'>
     */
    public function missing(string $provider): array
    {
        $missing = [];

        if ($this->key($provider) === null) {
            $missing[] = 'key';
        }

        if ($this->secret($provider) === null) {
            $missing[] = 'secret';
        }

        return $missing;
    }

    /**
     * The providers the site has both halves of the keys for.
     *
     * @return list<string>
     */
    public function available(): array
    {
        return array_values(array_filter(self::PROVIDERS, $this->configured(...)));
    }

    /** The name of the hidden input that carries the answer. */
    public function responseField(string $provider): ?string
    {
        return self::RESPONSE_FIELDS[$provider] ?? null;
    }

    /**
     * The names of a provider's keys in the environment: the site's, and the inbox's of before
     * which are still read — for a finding or a help line to say what to set.
     *
     * @return array{0: string, 1: string}
     */
    public static function env(string $provider, string $half): array
    {
        return ['WEBX_CAPTCHA_'.strtoupper($provider).'_'.strtoupper($half), 'WEBX_INBOX_'.strtoupper($provider).'_'.strtoupper($half)];
    }

    /**
     * The v3 action a token is asked for and checked against: reCAPTCHA takes letters, digits,
     * slashes and underscores.
     */
    public static function action(string $name): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_\/]/', '_', $name);
    }

    /**
     * What the settings and an agent may see: the provider, and per provider how it runs, the
     * site key and whether the secret is set — never the secret.
     *
     * @return array{provider: string|null, providers: array<string, array{type: string, key: string|null, secret: bool}>}
     */
    public function status(): array
    {
        $providers = [];

        foreach (self::PROVIDERS as $provider) {
            $providers[$provider] = ['type' => $this->type($provider), 'key' => $this->key($provider), 'secret' => $this->secret($provider) !== null];
        }

        return ['provider' => $this->provider(), 'providers' => $providers];
    }

    /**
     * Null when the answer is good, otherwise why not: `reason` ({@see UNCONFIGURED}, {@see MISSING},
     * {@see FAILED}), `provider`, `type` and what the provider said.
     *
     * `$provider` — a form that keeps a provider of its own (the inbox's of before); null — the site's.
     * `$action` — what a v3 token must have been asked for: one minted for another form is a token
     * for something else.
     *
     * @return array<string, mixed>|null
     */
    public function check(Request $request, string $action, ?string $provider = null, string $form = ''): ?array
    {
        $provider ??= $this->provider();

        if ($provider === null || ! in_array($provider, self::PROVIDERS, true)) {
            $this->log->warning('webx-widgets: the form {form} asks for a captcha and the site has chosen none.', ['form' => $form]);

            return ['reason' => self::UNCONFIGURED, 'provider' => null, 'type' => self::CHECKBOX];
        }

        $type = $this->type($provider);
        $secret = $this->secret($provider);

        if ($secret === null) {
            $this->log->warning('webx-widgets: the form {form} asks for a {provider} captcha and the site has no secret for it.', [
                'form' => $form,
                'provider' => $provider,
            ]);

            return ['reason' => self::UNCONFIGURED, 'provider' => $provider, 'type' => $type];
        }

        $answer = $request->input(self::RESPONSE_FIELDS[$provider]);

        if (! is_string($answer) || $answer === '') {
            return ['reason' => self::MISSING, 'provider' => $provider, 'type' => $type];
        }

        $failure = $this->verify($provider, $type, $secret, $answer, (string) $request->ip(), self::action($action));

        return $failure === null ? null : ['reason' => self::FAILED, 'provider' => $provider, 'type' => $type, ...$failure];
    }

    /**
     * What the visitor is told about a refusal from {@see check()}, or null when nothing they can
     * do would help — no keys, a v3 score — and the form says what it says to every refusal.
     *
     * The captcha a person can answer is drawn on the page for anybody to see: saying what to do
     * with it gives a robot nothing new. An Invisible or v3 reCAPTCHA without an answer is a page
     * without JavaScript.
     *
     * @param  array<string, mixed>  $refusal
     */
    public function message(array $refusal, ?string $locale = null): ?string
    {
        $type = $refusal['type'] ?? self::CHECKBOX;
        $reason = $refusal['reason'] ?? null;

        $key = match (true) {
            $reason === self::MISSING && $type !== self::CHECKBOX => 'script',
            $reason === self::MISSING, $reason === self::FAILED && $type !== self::V3 => 'missing',
            default => null,
        };

        return $key === null ? null : (string) trans('webx-widgets::widgets.captcha.'.$key, [], $locale);
    }

    /**
     * What a form draws, or nothing at all.
     *
     * Nothing covers two cases that look the same on the page and read very differently in the
     * log: a site with no provider, and a provider the site has no site key for. Both make a form
     * that renders and then refuses every submission, so the second says so in the log.
     *
     * @return array{provider: string, type: string, key: string, widget: string, field: string, action: string}|null
     */
    public function widget(string $action, ?string $provider = null, string $form = '', string $invisibleClass = 'webx-captcha__widget'): ?array
    {
        $provider ??= $this->provider();

        if ($provider === null || ! in_array($provider, self::PROVIDERS, true)) {
            return null;
        }

        $key = $this->key($provider);

        if ($key === null) {
            $this->log->warning('webx-widgets: the form {form} asks for a {provider} captcha and the site has no site key for it.', [
                'form' => $form,
                'provider' => $provider,
            ]);

            return null;
        }

        $type = $this->type($provider);

        return [
            'provider' => $provider,
            'type' => $type,
            'key' => $key,
            'widget' => $type === self::CHECKBOX ? self::WIDGETS[$provider] : $invisibleClass,
            'field' => self::RESPONSE_FIELDS[$provider],
            'action' => self::action($action),
        ];
    }

    /**
     * The provider's own script, the first time a page asks for it; null after that, or when the
     * site loads it itself (`script` emptied). v3 is told the key to render with.
     */
    public function script(string $provider, ?string $render = null): ?string
    {
        $src = $this->value($provider, 'script');

        if (! is_string($src) || $src === '' || isset($this->printed[$provider])) {
            return null;
        }

        $this->printed[$provider] = true;

        if ($render !== null && $render !== '') {
            $src .= (str_contains($src, '?') ? '&' : '?').'render='.rawurlencode($render);
        }

        return $src;
    }

    /** The secret half of a provider's keys, from the environment only. */
    private function secret(string $provider): ?string
    {
        $secret = $this->value($provider, 'secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /**
     * A provider's value: the tab of the settings (when allowed), the site's config, the inbox's of
     * before. A shipped address in the site's config gives way to a mirror set in the inbox's.
     */
    private function value(string $provider, string $name, bool $panel = false): mixed
    {
        if ($panel && ($set = $this->panel("captcha.{$provider}.{$name}")) !== null) {
            return $set;
        }

        $own = $this->config->get("webx-widgets.captcha.{$provider}.{$name}");
        $before = $this->config->get("webx-inbox.captcha.{$provider}.{$name}");
        $shipped = self::SHIPPED["{$provider}.{$name}"] ?? null;

        if ($shipped !== null && $own === $shipped && is_string($before) && $before !== $shipped) {
            return $before;
        }

        return $own !== null && $own !== '' ? $own : $before;
    }

    /** A setting of the "Captcha" tab, or null when `module-settings` is not there or has none. */
    private function panel(string $key): mixed
    {
        if (class_exists(self::SETTINGS) && $this->app->bound(self::SETTINGS)) {
            try {
                $value = $this->app->make(self::SETTINGS)->get($key);
            } catch (Throwable) {
                // No table yet — a site between `composer update` and `migrate`: the config rules.
                return null;
            }

            if ($value !== null && $value !== [] && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Null when the provider vouched for the answer, otherwise what it said instead.
     *
     * @return array<string, mixed>|null
     */
    private function verify(string $provider, string $type, string $secret, string $answer, string $ip, string $action): ?array
    {
        $endpoint = (string) $this->value($provider, 'verify');

        try {
            $response = $this->http
                ->timeout((int) ($this->config->get('webx-widgets.captcha.timeout') ?? $this->config->get('webx-inbox.captcha.timeout', 5)))
                ->asForm()
                ->post($endpoint, [
                    'secret' => $secret,
                    'response' => $answer,
                    'remoteip' => $ip,
                ]);
        } catch (Throwable $exception) {
            // An enquiry or an order lost to somebody else's outage is worse than a robot getting through.
            $this->log->warning('webx-widgets: could not reach the {provider} captcha; the submission was let through.', [
                'provider' => $provider,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $said = array_filter([
            'error-codes' => $response->json('error-codes'),
            'score' => $response->json('score'),
            'action' => $response->json('action'),
            'hostname' => $response->json('hostname'),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        if (($response->json('success') ?? false) !== true) {
            return $said;
        }

        if ($type !== self::V3) {
            return null;
        }

        // A v3 answer is never a no, only a number: what makes it a robot is the site's line.
        $score = $response->json('score');
        $minimum = $this->value(self::RECAPTCHA, 'min_score');
        $minimum = is_numeric($minimum) ? (float) $minimum : 0.5;

        if (! is_numeric($score) || (float) $score < $minimum) {
            return [...$said, 'min_score' => $minimum];
        }

        $answered = $response->json('action');

        if (is_string($answered) && $answered !== '' && $answered !== $action) {
            return [...$said, 'expected_action' => $action];
        }

        return null;
    }
}
