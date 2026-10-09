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
 * for nothing the visitor does not scroll to.
 *
 * The map lies still under the page until it is clicked or focused: the wheel scrolls the page,
 * and on a touch screen one finger does too. A click or the focus wakes it — the wheel zooms, a
 * finger pans; a press anywhere else puts it back to sleep, and so does the mouse leaving it. A
 * wheel over the sleeping map says once, for a moment, how to wake it.
 *
 * Leaflet's own attribution control stays off: the server prints the provider's attribution
 * under every map, with JavaScript or without. The pin is the package's, drawn in currentColor —
 * Leaflet's default icon is a PNG the build leaves out.
 */

import 'leaflet/dist/leaflet.css'
import '../css/map.css'
import { DivIcon, Map as LeafletMap, Marker, TileLayer } from 'leaflet/dist/leaflet-src.esm.js'

const webx = (window.webx ??= {})

const PIN =
  '<svg viewBox="0 0 32 40" width="32" height="40" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" d="M16 0C7.2 0 0 7 0 15.7 0 27.5 16 40 16 40s16-12.5 16-24.3C32 7 24.8 0 16 0Zm0 9.5a6 6 0 1 0 0 12 6 6 0 1 0 0-12Z"/></svg>'

/** The Leaflet of each root, for whoever has to reach it: the tests, a theme's script. */
export const maps = new WeakMap()

function settings(root) {
  try {
    return JSON.parse(root.getAttribute('data-webx-map') || '{}')
  } catch {
    return {}
  }
}

const coarse = () => typeof matchMedia === 'function' && matchMedia('(pointer: coarse)').matches

export function map(root) {
  const config = settings(root)
  if (!config.tiles || typeof config.lat !== 'number' || typeof config.lng !== 'number') return

  const place = root.querySelector('.webx-map__place')
  let instance = null
  let canvas = null
  let hint = null
  let hintTimer = 0
  let active = false
  let watcher = null
  let resizer = null

  // A finger pans the page until the map is woken; a mouse may drag it at once — dragging is no scroll.
  const handlers = () =>
    instance
      ? [instance.scrollWheelZoom, ...(coarse() ? [instance.dragging, instance.touchZoom] : [])]
      : []

  const wake = () => {
    if (!instance || active) return
    active = true
    handlers().forEach((handler) => handler.enable())
    root.classList.add('is-active')
    hint?.classList.remove('is-visible')
  }

  const sleep = () => {
    if (!instance || !active) return
    active = false
    handlers().forEach((handler) => handler.disable())
    root.classList.remove('is-active')
  }

  const build = () => {
    if (instance) return
    canvas = document.createElement('div')
    canvas.className = 'webx-map__canvas'
    root.prepend(canvas)

    const touch = coarse()
    instance = new LeafletMap(canvas, {
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
    if (place) place.hidden = true
    root.classList.add('is-loaded')
    maps.set(root, instance)

    // Leaflet follows the window; the frame may change with the column alone.
    if (typeof ResizeObserver === 'function') {
      resizer = new ResizeObserver(() => instance?.invalidateSize())
      resizer.observe(root)
    }
  }

  // Near the screen, or at once where nothing tells when it is.
  const load = () => {
    if (instance || watcher) return
    if (typeof IntersectionObserver !== 'function') return build()
    watcher = new IntersectionObserver(
      (entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return
        watcher.disconnect()
        watcher = null
        build()
      },
      { rootMargin: '200px' },
    )
    watcher.observe(root)
  }

  const unblock = () => {
    root.classList.remove('is-blocked')
    root.querySelector('.webx-map__notice')?.remove()
    root.querySelector('.webx-map__actions')?.remove()
  }

  const onClick = (event) => {
    const target = event.target instanceof Element ? event.target : null
    if (target?.closest('[data-webx-map-load]')) {
      unblock()
      build()
    } else if (target?.closest('[data-webx-map-always]')) {
      // Without the banner's script there is no consent to give: this one loads all the same.
      if (webx.consent?.set) webx.consent.set([...webx.consent.categories, 'media'])
      unblock()
      build()
    }
  }

  const onConsent = (event) => {
    if (!event.detail?.categories?.includes('media')) return
    unblock()
    load()
  }

  const onWheel = () => {
    if (!instance || active) return
    if (!hint) {
      hint = document.createElement('p')
      hint.className = 'webx-map__hint'
      hint.setAttribute('aria-hidden', 'true')
      hint.textContent = config.hint || ''
      root.append(hint)
    }
    hint.classList.add('is-visible')
    clearTimeout(hintTimer)
    hintTimer = setTimeout(() => hint?.classList.remove('is-visible'), 1500)
  }

  const onPress = (event) => {
    if (!(event.target instanceof Node) || !root.contains(event.target)) sleep()
  }

  root.addEventListener('click', onClick)
  root.addEventListener('wheel', onWheel, { passive: true })
  root.addEventListener('mouseleave', sleep)
  document.addEventListener('pointerdown', onPress)
  document.addEventListener('webx:consent', onConsent)
  root.classList.add('is-ready')

  if (!root.classList.contains('is-blocked') || webx.consent?.has?.('media')) {
    unblock()
    load()
  }

  return () => {
    root.removeEventListener('click', onClick)
    root.removeEventListener('wheel', onWheel)
    root.removeEventListener('mouseleave', sleep)
    document.removeEventListener('pointerdown', onPress)
    document.removeEventListener('webx:consent', onConsent)
    watcher?.disconnect()
    resizer?.disconnect()
    clearTimeout(hintTimer)
    // Leaflet's listeners on the window and the document go with its instance.
    instance?.remove()
    canvas?.remove()
    hint?.remove()
    maps.delete(root)
    instance = canvas = hint = watcher = resizer = null
    active = false
    if (place) place.hidden = false
    root.classList.remove('is-ready', 'is-loaded', 'is-active')
  }
}

webx.widget?.('map', '[data-webx-map]', map)
