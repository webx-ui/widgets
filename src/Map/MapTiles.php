<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Map;

use InvalidArgumentException;

/**
 * The provider of the map's tiles (spec §11), read from `webx-widgets.map`: OpenStreetMap unless
 * the site names another entry. An entry is raster tiles by an address — `{z}/{x}/{y}`, and
 * `{key}` replaced with its own key — so MapTiler and its like need no code, only config.
 *
 * A provider named but not described, or one whose address wants a key it was not given, is a
 * mistake of the site's config and says so, rather than a map that silently asks for tiles it
 * will never get.
 */
final readonly class MapTiles
{
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
        public string $attribution,
        public int $maxZoom,
    ) {}

    public static function configured(): self
    {
        $name = (string) config('webx-widgets.map.provider', 'openstreetmap');
        $entry = config("webx-widgets.map.providers.{$name}");

        if (! is_array($entry) || ! is_string($entry['tiles'] ?? null) || trim($entry['tiles']) === '') {
            throw new InvalidArgumentException("<x-webx-map>: the map provider \"{$name}\" is not described in webx-widgets.map.providers — it needs at least `tiles`, the address of a tile with {z}, {x} and {y}.");
        }

        $url = trim($entry['tiles']);

        if (str_contains($url, '{key}')) {
            $key = is_string($entry['key'] ?? null) ? trim($entry['key']) : '';

            if ($key === '') {
                throw new InvalidArgumentException("<x-webx-map>: the map provider \"{$name}\" asks for a key — set webx-widgets.map.providers.{$name}.key (WEBX_MAP_KEY).");
            }

            $url = str_replace('{key}', rawurlencode($key), $url);
        }

        $label = is_string($entry['label'] ?? null) && $entry['label'] !== '' ? $entry['label'] : ucfirst($name);
        $zoom = is_numeric($entry['max_zoom'] ?? null) ? (int) $entry['max_zoom'] : 19;

        return new self($name, $label, $url, is_string($entry['attribution'] ?? null) ? $entry['attribution'] : '', max(1, min(22, $zoom)));
    }

    /** "Open in maps" at a point: the site's template with {lat}, {lng} and {zoom}. */
    public static function openAt(float $lat, float $lng, int $zoom): ?string
    {
        $template = config('webx-widgets.map.open');

        if (! is_string($template) || trim($template) === '') {
            return null;
        }

        return str_replace(['{lat}', '{lng}', '{zoom}'], [self::number($lat), self::number($lng), (string) $zoom], trim($template));
    }

    /** A coordinate as a URL wants it: a dot, no exponent, no trailing zeros. */
    public static function number(float $value): string
    {
        $text = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}
