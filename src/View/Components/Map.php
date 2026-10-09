<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Contacts\ContactsSource;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Map\MapTiles;

/**
 * `<x-webx-map :lat="51.5074" :lng="-0.1278" :zoom="15" marker="1 Example Street, London" />`
 * and `<x-webx-map from="settings" />` — a map with a pin (spec §11).
 *
 * The server prints the place — the address and "Open in maps" — in a frame of the map's height,
 * and that is the map without JavaScript. Before the visitor agreed to `media` the notice of §9.4
 * stands with it, "Load" (this map) and "Always load maps" (the consent): tiles are requests to a
 * third party with the visitor's address. With consent the script puts Leaflet in the frame, over
 * the place; the wheel scrolls the page until the map is clicked or focused. The attribution of
 * the tiles is printed under every map, whatever the script does.
 *
 * `from="settings"` reads the primary address of the "Contacts" tab (§12.1): its text, its
 * coordinates and its link to a map. An address without coordinates is the place alone, with no
 * map; no address, or no `module-settings`, prints nothing. `marker` is the pin's label — the
 * address unless told — or false for none; `height` is a CSS length, 24rem unless told; `title`
 * names the region for a screen reader, the address unless told.
 */
final class Map extends Component
{
    public const int ZOOM = 15;

    public const string HEIGHT = '24rem';

    public ?float $lat = null;

    public ?float $lng = null;

    public int $zoom;

    public string $height;

    /** The text of the place, printed in the frame and the pin's label. */
    public ?string $address = null;

    /** "Open in maps". */
    public ?string $href = null;

    public string $name;

    /** Before consent to `media`: the notice and its buttons. */
    public bool $blocked = false;

    public string $notice = '';

    public string $attribution = '';

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
    ) {
        if ($from !== null && $from !== 'settings') {
            throw new InvalidArgumentException("<x-webx-map>: `from` is \"settings\" or nothing, not \"{$from}\".");
        }

        $this->zoom = self::zoom($zoom);
        $this->height = self::height($height);
        $this->address = self::filled($address) ?? (is_string($marker) ? self::filled($marker) : null);
        $this->href = self::filled($link);

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
            return;
        }

        $tiles = MapTiles::configured();
        $this->zoom = min($this->zoom, $tiles->maxZoom);
        $this->href ??= MapTiles::openAt((float) $this->lat, (float) $this->lng, $this->zoom);
        $this->blocked = ! app(Consent::class)->has('media');
        $this->notice = (string) __('webx-widgets::widgets.map.notice', ['provider' => $tiles->label]);
        $this->attribution = $tiles->attribution;
        $label = $marker === false ? null : ($this->address ?? '');
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

    public function hasMap(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public function shouldRender(): bool
    {
        return $this->hasMap() || $this->address !== null;
    }

    public function render(): View
    {
        Widgets::need('map');

        return view('webx-widgets::components.map');
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

    private static function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
