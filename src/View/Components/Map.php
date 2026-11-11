<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Contacts\ContactsSource;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Map\GoogleMaps;
use WebxUi\Widgets\Map\GoogleMapsEmbed;
use WebxUi\Widgets\Map\MapTiles;

/**
 * `<x-webx-map :lat="12.34" :lng="-34.56" :zoom="15" marker="1 Example Street, Sample City" />`
 * and `<x-webx-map from="settings" />` — a map with a pin (spec §11).
 *
 * The server prints the place — the address and "Open in maps" — in a frame of the map's height,
 * and that is the map without JavaScript. Before the visitor agreed to `media` the notice of §9.4
 * stands with it, "Load" (this map) and "Always load maps" (the consent): tiles are requests to a
 * third party with the visitor's address. With consent the script puts Leaflet in the frame, over
 * the place; the wheel scrolls the page until the map is clicked or focused. The attribution of
 * the tiles is printed under every map, whatever the script does.
 *
 * Whether it waits is the site's: `Consent::mapsWait()` — the Cookie tab's "Maps without asking",
 * or `webx-widgets.map.consent`. An owner who decided the site needs no consent for the tiles
 * turns the wait off: no notice, no `data-webx-consent`, the script puts the map in at once, and
 * the audit notes it. `:consent="false"` (or true) decides for one map, whatever the site says.
 *
 * `from="settings"` reads the primary address of the "Contacts" tab (§12.1): its text, its
 * coordinates and its link to a map. An address without coordinates is the place alone, with no
 * map; no address, or no `module-settings`, prints nothing. `marker` is the pin's label — the
 * address unless told — or false for none; `height` is a CSS length, 24rem unless told; `title`
 * names the region for a screen reader, the address unless told.
 *
 * The provider is the site's (§11.1): Leaflet over the tiles of the config, or Google Maps chosen
 * on the "Map" tab — the same frame, notice and sleep, its own script. Google without a key is
 * the address (or the coordinates) and "Open in Google Maps".
 *
 * `embed` is Google Maps' "Embed a map" instead (§11.2): the code Google gives, or its address —
 * no key, no coordinates, Google's own frame. The same frame of the map's height, the same notice
 * and consent; the script puts the `<iframe>` in once the visitor agreed, or for "Load". `link`
 * is "Open in Google Maps", else read from the address when it says where the map is. A code
 * that is not Google's map is refused, as a bad coordinate is.
 *
 * `height-narrow` is the height in a column narrower than 36em — a phone — where a map as tall as
 * on a monitor would be a screen of map to scroll past; the height everywhere unless told.
 */
final class Map extends Component
{
    public const int ZOOM = 15;

    public const string HEIGHT = '24rem';

    /** The provider of Google's "Embed a map". */
    public const string EMBED = 'embed';

    public ?float $lat = null;

    public ?float $lng = null;

    public int $zoom;

    public string $height;

    /** The height in a column narrower than 36em, or null: the same. */
    public ?string $heightNarrow = null;

    /** Google Maps' "Embed a map": the address of its frame (§11.2), or null for a map of a point. */
    public ?string $embed = null;

    /** The text of the place, printed in the frame and the pin's label. */
    public ?string $address = null;

    /** "Open in maps". */
    public ?string $href = null;

    public string $name;

    /** Whether the map waits for consent to `media` at all — the site's setting, or `consent`. */
    public bool $waits = true;

    /**
     * Google's frame printed by the server: a map of `embed` that does not wait for consent — the
     * block's "Show the map without consent", `:consent="false"`, or the site's "Maps without asking".
     */
    public bool $printed = false;

    /** Before consent to `media`: the notice and its buttons. */
    public bool $blocked = false;

    public string $notice = '';

    public string $attribution = '';

    /** Whose map the script puts in: Leaflet over tiles, `google` — its own script (§11.1) — or `embed` (§11.2). */
    public string $provider = 'tiles';

    /** @var array<string, mixed> What the script is told, `data-webx-map`. */
    public array $config = [];

    public function __construct(
        ?string $from = null,
        float|int|string|null $lat = null,
        float|int|string|null $lng = null,
        int|string|null $zoom = null,
        bool|string|null $marker = true,
        ?string $address = null,
        float|int|string|null $height = null,
        ?string $title = null,
        ?string $link = null,
        bool|string|null $consent = null,
        ?string $embed = null,
        float|int|string|null $heightNarrow = null,
    ) {
        if ($from !== null && $from !== 'settings') {
            throw new InvalidArgumentException("<x-webx-map>: `from` is \"settings\" or nothing, not \"{$from}\".");
        }

        $this->zoom = self::zoom($zoom);
        $this->height = self::height($height);
        $this->heightNarrow = $heightNarrow === null || $heightNarrow === '' ? null : self::height($heightNarrow);
        $this->address = self::filled($address) ?? (is_string($marker) ? self::filled($marker) : null);
        $this->href = self::filled($link);

        if (self::filled($embed) !== null) {
            $this->embedded((string) self::filled($embed), $title, $consent);

            return;
        }

        if ($lat !== null || $lng !== null) {
            $this->lat = self::coordinate($lat, 90, 'lat');
            $this->lng = self::coordinate($lng, 180, 'lng');
        } elseif ($from === 'settings' && ($place = ContactsSource::get()?->primaryAddress()) !== null) {
            $this->address ??= $place->text;
            $this->lat = $place->latitude;
            $this->lng = $place->longitude;
            $this->href ??= $place->map;
        } elseif ($from === null) {
            throw new InvalidArgumentException('<x-webx-map>: either `lat` and `lng`, or from="settings" — the primary address of the Contacts tab.');
        }

        $this->name = self::filled($title) ?? $this->address ?? (string) __('webx-widgets::widgets.map.untitled');

        if (! $this->hasMap()) {
            // Google's place of an address: its search for the words, when the address has no link.
            if ($this->address !== null && GoogleMaps::configured() !== null) {
                $this->provider = GoogleMaps::NAME;
                $this->href ??= GoogleMaps::search($this->address);
            }

            return;
        }

        $label = $marker === false ? null : ($this->address ?? '');
        $google = GoogleMaps::configured();

        if ($google !== null) {
            $this->href ??= GoogleMaps::openAt((float) $this->lat, (float) $this->lng);

            // No key: the place, with its link — not the grey window Google draws without one.
            if (! $google->hasKey()) {
                $this->address ??= MapTiles::number((float) $this->lat).', '.MapTiles::number((float) $this->lng);
                $this->lat = $this->lng = null;
                $this->provider = GoogleMaps::NAME;

                return;
            }

            $this->provider = GoogleMaps::NAME;
            $this->zoom = min($this->zoom, GoogleMaps::MAX_ZOOM);
            $this->notice = (string) __('webx-widgets::widgets.map.notice', ['provider' => GoogleMaps::LABEL]);
            // Google draws its own attribution on the map, as its terms ask: nothing to print.
            $this->config = [
                'provider' => GoogleMaps::NAME,
                'key' => $google->key,
                'mapId' => $google->mapId,
                'language' => str_replace('_', '-', app()->getLocale()),
                'lat' => $this->lat,
                'lng' => $this->lng,
                'zoom' => $this->zoom,
                'maxZoom' => GoogleMaps::MAX_ZOOM,
                'marker' => $label,
                'hint' => (string) __('webx-widgets::widgets.map.hint'),
                'failed' => (string) __('webx-widgets::widgets.map.failed'),
            ];
        } else {
            $tiles = MapTiles::configured();
            $this->zoom = min($this->zoom, $tiles->maxZoom);
            $this->href ??= MapTiles::openAt((float) $this->lat, (float) $this->lng, $this->zoom);
            $this->notice = (string) __('webx-widgets::widgets.map.notice', ['provider' => $tiles->label]);
            $this->attribution = $tiles->attribution;
            $this->config = [
                'lat' => $this->lat,
                'lng' => $this->lng,
                'zoom' => $this->zoom,
                'tiles' => $tiles->url,
                'maxZoom' => $tiles->maxZoom,
                'marker' => $label,
                'hint' => (string) __('webx-widgets::widgets.map.hint'),
            ];
        }

        $this->waits = self::flag($consent) ?? app(Consent::class)->mapsWait();
        $this->blocked = $this->waits && ! app(Consent::class)->has('media');

        // Told, so the script does not wait for a consent the site does not ask for.
        if (! $this->waits) {
            $this->config['consent'] = false;
        }
    }

    public function hasMap(): bool
    {
        return $this->embed !== null || ($this->lat !== null && $this->lng !== null);
    }

    /** The style of the frame: its height, and the one of a narrow column — the same unless told. */
    public function frameStyle(): string
    {
        return "--webx-map-height: {$this->height}; --webx-map-height-narrow: ".($this->heightNarrow ?? $this->height);
    }

    public function shouldRender(): bool
    {
        return $this->hasMap() || $this->address !== null;
    }

    public function render(): View
    {
        // Google's place without a key wants the stylesheet alone, not Leaflet with it; Google's
        // frame wants a few lines that put an <iframe> in, not a library.
        Widgets::need(match ($this->provider) {
            GoogleMaps::NAME => 'map-google',
            self::EMBED => 'map-embed',
            default => 'map',
        });

        return view('webx-widgets::components.map');
    }

    /** Google Maps' "Embed a map" (§11.2): the frame Google draws, under the same notice and consent. */
    private function embedded(string $code, ?string $title, bool|string|null $consent): void
    {
        $this->embed = GoogleMapsEmbed::address($code)
            ?? throw new InvalidArgumentException('<x-webx-map>: `embed` is the code or the address of Google Maps\' "Embed a map" — https://www.google.com/maps/embed?pb=… — not "'.mb_strimwidth($code, 0, 80, '…').'".');

        $this->provider = self::EMBED;
        $this->href ??= GoogleMapsEmbed::open($this->embed);
        $this->name = self::filled($title) ?? $this->address ?? (string) __('webx-widgets::widgets.map.untitled');
        $this->notice = (string) __('webx-widgets::widgets.map.notice', ['provider' => GoogleMaps::LABEL]);
        // Google draws its own attribution in the frame.
        $this->config = [
            'provider' => self::EMBED,
            'src' => $this->embed,
            'title' => $this->name,
        ];

        $this->waits = self::flag($consent) ?? app(Consent::class)->mapsWait();
        $this->blocked = $this->waits && ! app(Consent::class)->has('media');

        // Nothing to wait for: the frame is in the HTML — without the script, and without a moment
        // of the place before it. A visitor's consent is not reason enough: the page may be cached.
        if (! $this->waits) {
            $this->config['consent'] = false;
            $this->printed = true;
        }
    }

    private static function coordinate(float|int|string|null $value, int $limit, string $name): float
    {
        if (! is_numeric($value) || abs((float) $value) > $limit) {
            throw new InvalidArgumentException('<x-webx-map>: `'.$name.'` is a number from -'.$limit.' to '.$limit.', not "'.(is_scalar($value) ? (string) $value : 'nothing').'".');
        }

        return (float) $value;
    }

    private static function zoom(int|string|null $zoom): int
    {
        if ($zoom === null || $zoom === '') {
            return self::ZOOM;
        }

        if (! is_numeric($zoom) || floor((float) $zoom) !== (float) $zoom || (int) $zoom < 1 || (int) $zoom > 22) {
            throw new InvalidArgumentException("<x-webx-map>: a zoom is a whole number from 1 to 22, not \"{$zoom}\".");
        }

        return (int) $zoom;
    }

    /** `24rem`, `400px`, `60svh` — or a number, in pixels. */
    private static function height(float|int|string|null $height): string
    {
        if ($height === null || $height === '') {
            return self::HEIGHT;
        }

        if (is_numeric($height) && (float) $height > 0) {
            return ((string) (float) $height).'px';
        }

        if (is_string($height) && preg_match('~^\s*(\d+(?:\.\d+)?)(px|rem|em|vh|svh|lvh|dvh)\s*$~', $height, $parts) === 1 && (float) $parts[1] > 0) {
            return $parts[1].$parts[2];
        }

        throw new InvalidArgumentException('<x-webx-map>: a height is a CSS length — 24rem, 400px, 60svh — or a number of pixels, not "'.$height.'".');
    }

    /** `:consent="false"`, or `consent="false"` written as text — which PHP would read as true. */
    private static function flag(bool|string|null $value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        if (trim($value) === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? throw new InvalidArgumentException("<x-webx-map>: `consent` is true or false, not \"{$value}\".");
    }

    private static function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
