<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Map;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Google Maps as the provider of `<x-webx-map>` (spec §11.1): the Maps JavaScript API in the same
 * frame, behind the same notice of §9.4, instead of Leaflet over tiles — Google's terms forbid
 * taking its tiles into another library, so it is its own script, not one more entry of
 * `webx-widgets.map.providers`.
 *
 * Where each value comes from, first found wins: the "Map" tab of `module-settings` (`map.*`),
 * then `webx-widgets.map` (`provider` = `google`, `google.key`, `google.map_id`). The key is a
 * browser key — every visitor sees it in the page — so it lives with the settings, and what
 * protects it is the list of the site's domains it is restricted to in Google's console.
 *
 * Chosen without a key, the provider is no map at all, but the place: the address and "Open in
 * Google Maps" — never the grey window Google draws for a missing key.
 */
final readonly class GoogleMaps
{
    public const string NAME = 'google';

    public const string LABEL = 'Google Maps';

    /** The setting of the "Map" tab: `tiles` — the config's provider of tiles — or `google`. */
    public const string SETTING_PROVIDER = 'map.provider';

    public const string TILES = 'tiles';

    /** "Open in Google Maps": Google's own address of a point, for any device and any app. */
    public const string OPEN = 'https://www.google.com/maps/search/?api=1&query={lat},{lng}';

    /** The deepest zoom of Google's roadmap. */
    public const int MAX_ZOOM = 21;

    /** The settings class of `module-settings`, named rather than imported: it is not required. */
    private const string SETTINGS = 'WebxUi\Settings\Settings';

    public function __construct(
        public ?string $key,
        public ?string $mapId,
    ) {}

    /** Google, when the site chose it; null when its maps are Leaflet's over tiles. */
    public static function configured(): ?self
    {
        $app = app();
        $config = $app->make(Config::class);

        $chosen = self::panel($app, self::SETTING_PROVIDER);
        $provider = $chosen ?? $config->get('webx-widgets.map.provider');

        if ($provider !== self::NAME) {
            return null;
        }

        $key = self::panel($app, 'map.google-key') ?? $config->get('webx-widgets.map.google.key');
        $mapId = self::panel($app, 'map.google-map-id') ?? $config->get('webx-widgets.map.google.map_id');

        return new self(self::filled($key), self::filled($mapId));
    }

    public function hasKey(): bool
    {
        return $this->key !== null;
    }

    /** "Open in Google Maps" at a point. */
    public static function openAt(float $lat, float $lng): string
    {
        return str_replace(['{lat}', '{lng}'], [MapTiles::number($lat), MapTiles::number($lng)], self::OPEN);
    }

    /** "Open in Google Maps" for an address with no coordinates: Google's search for its words. */
    public static function search(string $address): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);
    }

    /** A setting of the "Map" tab, or null when `module-settings` is not there or has none. */
    private static function panel(Container $app, string $key): mixed
    {
        if (! class_exists(self::SETTINGS) || ! $app->bound(self::SETTINGS)) {
            return null;
        }

        try {
            $value = $app->make(self::SETTINGS)->get($key);
        } catch (Throwable) {
            // No table yet — a site between `composer update` and `migrate`: the config rules.
            return null;
        }

        return $value === null || $value === '' || $value === [] ? null : $value;
    }

    private static function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
