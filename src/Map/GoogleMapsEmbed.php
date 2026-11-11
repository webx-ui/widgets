<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Map;

/**
 * Google Maps' "Share → Embed a map" (spec §11.2): a frame Google draws itself, with no key — what
 * a site whose owner cannot get a key for the Maps JavaScript API puts on its contacts page.
 *
 * What is pasted is Google's whole `<iframe …>` or the address alone; what is kept and printed is
 * the address, and only one of two shapes: `https://www.google.com/maps/embed?pb=…` — the code
 * Google gives today — and the older `https://maps.google.com/maps?…&output=embed` (or on
 * `www.google.com`), which the code of an address search still is. Anything else — another
 * domain, another path (`/maps/embed/v1/…` is the keyed Embed API), `http:`, a port, HTML with more
 * in it than the one frame — is not a map of Google's, and {@see self::address()} answers null.
 */
final class GoogleMapsEmbed
{
    /** The source of `wx-embed` that takes these frames ({@see GoogleMapsEmbedSource}). */
    public const string SOURCE = 'google-maps';

    /** Google's own frame and nothing around it: the code of "Embed a map" as it is copied. */
    private const string IFRAME = '~^\s*<iframe\b([^<>]*)>\s*</iframe>\s*$~i';

    private const string SRC = '~(?:^|\s)src\s*=\s*(?:"([^"]*)"|\'([^\']*)\')~i';

    /** The address of the frame in what was pasted, in one spelling; null when it is not Google's map. */
    public static function address(string $pasted): ?string
    {
        $text = trim($pasted);

        if ($text === '') {
            return null;
        }

        if (str_starts_with($text, '<')) {
            if (preg_match(self::IFRAME, $text, $frame) !== 1 || preg_match(self::SRC, $frame[1], $src) !== 1) {
                return null;
            }

            $text = html_entity_decode($src[1] !== '' ? $src[1] : ($src[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } else {
            // An address copied out of the code still carries its `&amp;`.
            $text = str_replace('&amp;', '&', $text);
        }

        // Nothing that could close an attribute or start a tag, no spaces: a URL as Google writes it.
        if ($text === '' || preg_match('~[\s"\'<>\\\\`]~', $text) === 1) {
            return null;
        }

        $parts = parse_url($text);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';

        if ($query === '') {
            return null;
        }

        parse_str($query, $values);

        $embed = $host === 'www.google.com' && $path === '/maps/embed' && is_string($values['pb'] ?? null) && $values['pb'] !== '';
        $old = in_array($host, ['www.google.com', 'maps.google.com'], true) && $path === '/maps' && ($values['output'] ?? null) === 'embed';

        return $embed || $old ? "https://{$host}{$path}?{$query}" : null;
    }

    /**
     * "Open in Google Maps" for a frame, when its address says where it is: the words of the old
     * shape's `q`, or the centre a `pb` carries (`!2d` the longitude, `!3d` the latitude). A `pb`
     * without them says nothing reliable, and the answer is null — the field's own link, or none.
     */
    public static function open(string $address): ?string
    {
        $query = parse_url($address, PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $values);

        if (is_string($values['q'] ?? null) && trim($values['q']) !== '') {
            return GoogleMaps::search(trim($values['q']));
        }

        $pb = $values['pb'] ?? null;

        if (is_string($pb)
            && preg_match('~!2d(-?\d{1,3}(?:\.\d+)?)~', $pb, $lng) === 1
            && preg_match('~!3d(-?\d{1,2}(?:\.\d+)?)~', $pb, $lat) === 1
            && abs((float) $lat[1]) <= 90 && abs((float) $lng[1]) <= 180) {
            return GoogleMaps::openAt((float) $lat[1], (float) $lng[1]);
        }

        return null;
    }
}
