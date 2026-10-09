<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

/**
 * The third parties the audit knows by address (spec §15.2): video and map players, counters and
 * advertising pixels — each with the consent category it waits for (§9.1). A site adds its own in
 * `webx-widgets.audit.third-party` (`host` or `host/path` → category).
 *
 * A host matches itself and its subdomains (`youtube.com` — `www.` and `m.` too); an entry with a
 * path matches addresses under that path only (`google.com/maps` is a map, `google.com` is not).
 */
final class ThirdParty
{
    /** @var array<string, string> */
    public const KNOWN = [
        'youtube.com' => 'media',
        'youtube-nocookie.com' => 'media',
        'youtu.be' => 'media',
        'vimeo.com' => 'media',
        'google.com/maps' => 'media',
        'maps.google.com' => 'media',
        'maps.googleapis.com' => 'media',
        'openstreetmap.org' => 'media',
        'googletagmanager.com' => 'statistics',
        'google-analytics.com' => 'statistics',
        'mc.yandex.ru' => 'statistics',
        'hotjar.com' => 'statistics',
        'clarity.ms' => 'statistics',
        'connect.facebook.net' => 'marketing',
        'facebook.com/tr' => 'marketing',
        'doubleclick.net' => 'marketing',
        'googleadservices.com' => 'marketing',
        'snap.licdn.com' => 'marketing',
        'analytics.tiktok.com' => 'marketing',
    ];

    /** The category an address waits for, or null for an address the audit does not know. */
    public static function category(string $url): ?string
    {
        $parts = parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');

        foreach (self::known() as $entry => $category) {
            [$known, $under] = array_pad(explode('/', strtolower($entry), 2), 2, null);

            if ($host !== $known && ! str_ends_with($host, '.'.$known)) {
                continue;
            }

            if ($under === null || $path === '/'.$under || str_starts_with($path, '/'.$under.'/')) {
                return $category;
            }
        }

        return null;
    }

    /**
     * The first known address in a piece of inline script — the snippet a counter is pasted as
     * creates its own `<script src>`, so the address is in the text.
     */
    public static function inText(string $text): ?string
    {
        if (preg_match_all('~(?:https?:)?//[a-z0-9.-]+\.[a-z]{2,}(?:/[^\s\'"`<>()]*)?~i', $text, $matches) === false) {
            return null;
        }

        foreach ($matches[0] as $url) {
            if (self::category($url) !== null) {
                return str_starts_with($url, '//') ? 'https:'.$url : $url;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private static function known(): array
    {
        $own = function_exists('config') ? config('webx-widgets.audit.third-party', []) : [];

        return (is_array($own) ? array_filter($own, 'is_string') : []) + self::KNOWN;
    }
}
