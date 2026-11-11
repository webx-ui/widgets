/*
 * The map (spec §11): Leaflet built in here, and only here — a page without a map never loads it.
 * Its own file, claimed by `<x-webx-map>`; it registers through `webx.widget`.
 *
 * The server prints the place — the address and "Open in maps" — in a frame of the map's height,
 * and before consent to `media` the notice of §9.4 with "Load" and "Always load maps". Tiles are
 * requests to a third party with the visitor's address, so the map goes in only on "Load" (this
 * one, no consent given), on "Always" (the consent, through `webx.consent.set()`: the banner is
 * answered and every map on the page loads, no reload) or at once when consent was there before.
 * Even then it waits until the frame is near the screen: a map at the foot of a long page asks
 * for nothing the visitor does not scroll to. A site whose owner lets maps load without consent
 * says so in the settings (`consent: false`): no notice was printed, and the map goes in at once.
 *
 * The map lies still under the page until it is clicked or focused: the wheel scrolls the page,
 * and on a touch screen one finger does too. A click or the focus wakes it — the wheel zooms, a
 * finger pans; a press anywhere else puts it back to sleep, and so does the mouse leaving it. A
 * wheel over the sleeping map says once, for a moment, how to wake it.
 *
 * The notice, the waits and the sleep are the shell's (map-shell.js), shared with Google Maps.
 *
 * Leaflet's own attribution control stays off: the server prints the provider's attribution
 * under every map, with JavaScript or without. The pin is the package's, drawn in currentColor —
 * Leaflet's default icon is a PNG the build leaves out.
 */

import 'leaflet/dist/leaflet.css'
import '../css/map.css'
import { DivIcon, Map as LeafletMap, Marker, TileLayer } from 'leaflet/dist/leaflet-src.esm.js'
import { PIN } from './map-pin.js'
import { coarse, settings, shell } from './map-shell.js'

const webx = (window.webx ??= {})

/** The Leaflet of each root, for whoever has to reach it: the tests, a theme's script. */
export const maps = new WeakMap()

function leaflet(root) {
  return (canvas, config, { wake }) => {
    const touch = coarse()
    const instance = new LeafletMap(canvas, {
      center: [config.lat, config.lng],
      zoom: config.zoom ?? 15,
      maxZoom: config.maxZoom ?? 19,
      attributionControl: false,
      scrollWheelZoom: false,
      dragging: !touch,
      touchZoom: !touch,
    })
    new TileLayer(config.tiles, { maxZoom: config.maxZoom ?? 19 }).addTo(instance)

    if (typeof config.marker === 'string') {
      new Marker([config.lat, config.lng], {
        icon: new DivIcon({
          className: 'webx-map__marker',
          html: PIN,
          iconSize: [32, 40],
          iconAnchor: [16, 40],
        }),
        title: config.marker,
        alt: config.marker,
        keyboard: false,
      }).addTo(instance)
    }

    instance.on('click focus', wake)
    maps.set(root, instance)

    // A finger pans the page until the map is woken; a mouse may drag it at once — dragging is no scroll.
    const handlers = () => [
      instance.scrollWheelZoom,
      ...(coarse() ? [instance.dragging, instance.touchZoom] : []),
    ]

    return {
      wake: () => handlers().forEach((handler) => handler.enable()),
      sleep: () => handlers().forEach((handler) => handler.disable()),
      resize: () => instance.invalidateSize(),
      remove: () => {
        // Leaflet's listeners on the window and the document go with its instance.
        instance.remove()
        maps.delete(root)
      },
    }
  }
}

export function map(root) {
  const config = settings(root)
  if (!config.tiles || typeof config.lat !== 'number' || typeof config.lng !== 'number') return

  return shell(root, config, leaflet(root))
}

webx.widget?.('map', '[data-webx-map]', map)
