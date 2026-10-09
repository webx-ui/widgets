<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The visitor's answer about cookies, and the banner that asks for it (spec §9).
 *
 * The answer lives in the visitor's browser — the first-party cookie `webx_consent`, written by
 * the banner's script: `{"v": <policy version>, "d": "<date>", "c": [<categories>]}`. The server
 * reads it too, so a placeholder instead of a video is drawn at once rather than swapped in after
 * the page has shown the video's place empty (`Consent::has('media')`). An answer given to an
 * older version of the policy is no answer: the banner asks again.
 *
 * What the banner says and does is a setting: the "Cookie" tab of `module-settings` when it is
 * installed (`consent.*`), the `webx-widgets.consent` config when it is not.
 */
final class Consent
{
    public const string COOKIE = 'webx_consent';

    /** The id of the dialog: `#webx-consent` opens it, as any link to a dialog does. */
    public const string DIALOG = 'webx-consent';

    public const array CATEGORIES = ['necessary', 'preferences', 'statistics', 'marketing', 'media'];

    /** What a visitor may decline: everything but what the site cannot work without. */
    public const array OPTIONAL = ['preferences', 'statistics', 'marketing', 'media'];

    /** The words the panel may replace: the banner's two and a line per category. */
    public const array TEXTS = ['title', 'text', ...self::CATEGORIES];

    /** The categories something on the site has asked for, remembered across pages. */
    private const string SEEN = 'webx-widgets.consent.seen';

    /** The settings class of `module-settings`, named rather than imported: it is not required. */
    private const string SETTINGS = 'WebxUi\Settings\Settings';

    /** @var array{v: string, d: string, c: list<string>}|false|null */
    private array|false|null $answer = null;

    public function __construct(
        private readonly Container $app,
        private readonly Config $config,
        private readonly Cache $cache,
        private readonly ViewFactory $views,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->setting('enabled', true);
    }

    /** The version of the policy an answer must have been given to. */
    public function version(): string
    {
        $version = $this->setting('version', 1);

        return is_scalar($version) && (string) $version !== '' ? (string) $version : '1';
    }

    /**
     * Whether the visitor agreed to a category. Necessary always; everything with the banner
     * off — a site that turned it off says there is nothing to agree to.
     */
    public function has(string $category): bool
    {
        self::check($category);

        if ($category === 'necessary' || ! $this->enabled()) {
            return true;
        }

        return in_array($category, $this->answer()['c'] ?? [], true);
    }

    /** Whether the visitor answered the current version of the policy. */
    public function answered(): bool
    {
        return $this->answer() !== null;
    }

    /**
     * The answer to the current version, as the cookie keeps it; null when there is none.
     *
     * @return array{v: string, d: string, c: list<string>}|null
     */
    public function answer(): ?array
    {
        if ($this->answer === null) {
            $this->answer = $this->read() ?? false;
        }

        return $this->answer === false ? null : $this->answer;
    }

    /**
     * Global Privacy Control (`Sec-GPC: 1`): the browser asks not to be tracked, so marketing is
     * never part of "Accept all" and is never offered switched on.
     */
    public function gpc(): bool
    {
        return $this->request()?->headers->get('Sec-GPC') === '1';
    }

    /**
     * The optional categories the dialog offers on this page: the ones chosen in the settings,
     * or — when none are — those something on the site has asked for, this page included.
     *
     * @return list<string>
     */
    public function categories(string $html = ''): array
    {
        $chosen = $this->setting('categories', []);
        $chosen = is_array($chosen) ? array_values(array_intersect(self::OPTIONAL, $chosen)) : [];

        if ($chosen !== []) {
            return $chosen;
        }

        preg_match_all('/data-webx-consent=["\']?([a-z]+)/', $html, $found);
        $seen = $this->cache->get(self::SEEN, []);
        $seen = is_array($seen) ? $seen : [];
        $all = array_values(array_intersect(self::OPTIONAL, [...$seen, ...$found[1]]));

        if (array_diff($all, $seen) !== []) {
            $this->cache->forever(self::SEEN, $all);
        }

        return array_values(array_intersect(self::OPTIONAL, [...$all, ...($this->answer()['c'] ?? [])]));
    }

    /** One of the banner's words: the setting in the current language, or the dictionary's. */
    public function text(string $key): string
    {
        if (! in_array($key, self::TEXTS, true)) {
            throw new InvalidArgumentException("The consent banner has no text \"{$key}\".");
        }

        $value = $this->setting("text-{$key}");

        if ($value === null) {
            $texts = $this->config->get('webx-widgets.consent.texts', []);
            $value = is_array($texts) ? ($texts[$key] ?? null) : null;
            $value = is_array($value) ? ($value[$this->app->getLocale()] ?? null) : $value;
        }

        return is_string($value) && trim($value) !== '' ? $value : (string) __("webx-widgets::widgets.consent.texts.{$key}");
    }

    /** The address of the cookie policy, or null when the site gave none. */
    public function policy(): ?string
    {
        $policy = $this->setting('policy');

        // A `wx-link` field reads back as the link resolved; the config holds an address.
        if (is_array($policy)) {
            return ($policy['available'] ?? true) && is_string($policy['url'] ?? null) && $policy['url'] !== '' ? $policy['url'] : null;
        }

        return is_string($policy) && $policy !== '' ? $policy : null;
    }

    public function lifetime(): int
    {
        return max(1, (int) $this->config->get('webx-widgets.consent.lifetime', 365));
    }

    /**
     * Google Consent Mode v2 (§9.3): the default, before any tag of Google's reads it — denied
     * for what the visitor has not agreed to. Pushed to `dataLayer` the way `gtag()` does it, so
     * it is there for whichever loads, and harmless where nothing of Google's does.
     */
    public function googleDefault(): string
    {
        $state = static fn (bool $granted): string => $granted ? 'granted' : 'denied';
        $modes = [
            'ad_storage' => $state($this->has('marketing')),
            'ad_user_data' => $state($this->has('marketing')),
            'ad_personalization' => $state($this->has('marketing')),
            'analytics_storage' => $state($this->has('statistics')),
            'functionality_storage' => $state($this->has('preferences')),
            'personalization_storage' => $state($this->has('preferences')),
            'security_storage' => 'granted',
            'wait_for_update' => 500,
        ];

        return '<script>window.dataLayer=window.dataLayer||[];(function(){dataLayer.push(arguments)})("consent","default",'
            .json_encode($modes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).');</script>';
    }

    /**
     * The banner and its dialog, before `</body>` of a page: hidden until the script has read
     * the cookie, so a visitor who answered never sees it flash, and a page without JavaScript —
     * where nothing third-party starts anyway — never shows buttons that could not work.
     */
    public function render(string $html = ''): string
    {
        $shown = $this->enabled() ? $this->categories($html) : [];

        return $this->views->make('webx-widgets::consent', [
            'consent' => $this,
            'enabled' => $this->enabled(),
            'shown' => $shown,
            'granted' => $this->answer()['c'] ?? [],
        ])->render();
    }

    /** Forget the answer read: the next request of a long-lived worker brings its own cookie. */
    public function flush(): void
    {
        $this->answer = null;
    }

    /** A category name the banner does not know is a typo that would block something forever. */
    public static function check(string $category): void
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException("There is no consent category \"{$category}\": ".implode(', ', self::CATEGORIES).'.');
        }
    }

    /** @return array{v: string, d: string, c: list<string>}|null */
    private function read(): ?array
    {
        $raw = $this->request()?->cookies->get(self::COOKIE);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $answer = json_decode($raw, true);

        if (! is_array($answer) || ! isset($answer['v'], $answer['c']) || ! is_array($answer['c']) || (string) $answer['v'] !== $this->version()) {
            return null;
        }

        return [
            'v' => (string) $answer['v'],
            'd' => is_string($answer['d'] ?? null) ? $answer['d'] : '',
            'c' => array_values(array_intersect(self::OPTIONAL, $answer['c'])),
        ];
    }

    private function request(): ?Request
    {
        return $this->app->bound('request') ? $this->app->make('request') : null;
    }

    /**
     * A setting of the "Cookie" tab when `module-settings` is there and has one, the config's
     * otherwise. Words are never in the config under `consent.text-*`: they are `texts`.
     */
    private function setting(string $key, mixed $default = null): mixed
    {
        if (class_exists(self::SETTINGS) && $this->app->bound(self::SETTINGS)) {
            $value = $this->app->make(self::SETTINGS)->get("consent.{$key}");

            if ($value !== null && $value !== [] && $value !== '') {
                return $value;
            }
        }

        return str_starts_with($key, 'text-') ? null : $this->config->get("webx-widgets.consent.{$key}", $default);
    }
}
