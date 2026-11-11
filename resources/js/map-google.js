/*
 * The map on Google Maps (spec §11.1): the Maps JavaScript API in the frame of `<x-webx-map>`,
 * claimed instead of map.js when the site chose Google — so a page has Leaflet or Google, never
 * both. The notice, the waits and the sleep are the shell's (map-shell.js), the same as Leaflet's:
 * Google's script is not even asked for before consent to `media` (or "Load"), and then only when
 * the frame comes near the screen. One script for every map of the page.
 *
 * Asleep, the map is Google's "cooperative": the wheel and one finger scroll the page, and Google
 * itself says how to zoom — so the shell's own hint stays away. Clicked or focused, it is
 * "greedy"; a press elsewhere puts it back to sleep. The +/− buttons work always.
 *
 * A key Google refuses (`gm_authFailure`) or a script that never comes is the place again — the
 * address and "Open in Google Maps" with a line saying the map could not be loaded — instead of
 * Google's grey window. The pin is the package's SVG: an AdvancedMarkerElement with a Map ID, a
 * classic Marker of the same picture without one. The colours follow the site's scheme: dark
 * under `data-theme="dark"` or a dark `color-scheme`, the system's where the site follows it —
 * read once, when the map is made: Google sets it only then.
 */

import '../css/map-google.css'
// The query makes them this file's own copies: a chunk shared with map.js would be one more request.
import { PIN } from './map-pin.js?google'
import { settings, shell } from './map-shell.js?google'

const webx = (window.webx ??= {})

const CALLBACK = '__webxGoogleMaps'

/** Where the API is asked for: a test and a site's CSP can see it. */
export const SCRIPT = 'https://maps.googleapis.com/maps/api/js'

/** The Google map of each root, for whoever has to reach it: the tests, a theme's script. */
export const maps = new WeakMap()

let loading = null
let refused = false
const refusals = new Set()

/** The API, loaded once for the page: the first map that asks for it puts the script in. */
export function api(config) {
  if (window.google?.maps?.importLibrary) return Promise.resolve(window.google.maps)
  if (loading) return loading

  loading = new Promise((resolve, reject) => {
    window[CALLBACK] = () => resolve(window.google.maps)

    // Google calls it, once, when it refuses the key: every map of the page goes back to the place.
    const before = window.gm_authFailure
    window.gm_authFailure = () => {
      refused = true
      refusals.forEach((fail) => fail())
      if (typeof before === 'function') before()
    }

    const query = new URLSearchParams({
      key: config.key,
      v: 'weekly',
      loading: 'async',
      callback: CALLBACK,
    })
    if (config.language) query.set('language', config.language)

    const script = document.createElement('script')
    script.src = `${SCRIPT}?${query}`
    script.async = true
    script.onerror = () => {
      // Another map may try again later: an offline moment is not a refused key.
      loading = null
      script.remove()
      reject(new Error('Google Maps did not load'))
    }
    document.head.append(script)
  })

  return loading
}

/** The site's scheme, as Google names it: the switch on <html>, else its `color-scheme`. */
export function scheme() {
  const html = document.documentElement
  const chosen = html.getAttribute('data-theme')
  if (chosen === 'dark') return 'DARK'
  if (chosen === 'light') return 'LIGHT'

  const declared =
    (typeof getComputedStyle === 'function' && getComputedStyle(html).colorScheme) || ''
  const dark = /\bdark\b/.test(declared)
  const light = /\blight\b/.test(declared)
  if (dark && light) return 'FOLLOW_SYSTEM'
  return dark ? 'DARK' : 'LIGHT'
}

// The pin's colour as the site's stylesheet has it: a classic Marker is a picture, not markup.
function pinColor(root) {
  const probe = document.createElement('span')
  probe.className = 'webx-map__marker'
  probe.hidden = true
  root.append(probe)
  const color = getComputedStyle(probe).color || '#d33'
  probe.remove()
  return color
}

function pin(google, map, root, config) {
  const position = { lat: config.lat, lng: config.lng }

  if (config.mapId && google.marker.AdvancedMarkerElement) {
    const content = document.createElement('div')
    content.className = 'webx-map__marker'
    content.innerHTML = PIN
    return new google.marker.AdvancedMarkerElement({ map, position, content, title: config.marker })
  }

  const svg = PIN.replace('fill="currentColor"', `fill="${pinColor(root)}"`)
  const Marker = google.marker.Marker ?? google.maps.Marker
  return new Marker({
    map,
    position,
    title: config.marker,
    icon: {
      url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
      scaledSize: new google.core.Size(32, 40),
      anchor: new google.core.Point(16, 40),
    },
  })
}

function driver(root) {
  return (canvas, config, { wake, fail }) => {
    if (refused) {
      fail()
      return null
    }

    let map = null
    let marker = null
    let awake = false
    let gone = false
    const onFocus = () => wake()

    refusals.add(fail)
    canvas.addEventListener('focusin', onFocus)

    api(config)
      .then(async (googleMaps) => {
        const [{ Map }, core, markers] = await Promise.all([
          googleMaps.importLibrary('maps'),
          googleMaps.importLibrary('core'),
          typeof config.marker === 'string' ? googleMaps.importLibrary('marker') : null,
        ])
        if (gone) return

        const google = { maps: googleMaps, core, marker: markers }
        const name = scheme()
        map = new Map(canvas, {
          center: { lat: config.lat, lng: config.lng },
          zoom: config.zoom ?? 15,
          maxZoom: config.maxZoom ?? 21,
          gestureHandling: awake ? 'greedy' : 'cooperative',
          ...(config.mapId ? { mapId: config.mapId } : {}),
          colorScheme: googleMaps.ColorScheme?.[name] ?? name,
          clickableIcons: false,
          disableDefaultUI: true,
          zoomControl: true,
          keyboardShortcuts: true,
        })
        if (typeof config.marker === 'string') marker = pin(google, map, root, config)
        map.addListener('click', wake)
        maps.set(root, map)
      })
      .catch(() => {
        if (!gone) fail()
      })

    return {
      // The shell's hint stays away: asleep, Google says itself how to zoom.
      ownHint: true,
      wake: () => {
        awake = true
        map?.setOptions({ gestureHandling: 'greedy' })
      },
      sleep: () => {
        awake = false
        map?.setOptions({ gestureHandling: 'cooperative' })
      },
      // Google follows the size of its element by itself.
      resize: () => {},
      remove: () => {
        gone = true
        refusals.delete(fail)
        canvas.removeEventListener('focusin', onFocus)
        if (marker) marker.map = null
        marker?.setMap?.(null)
        if (map) window.google?.maps?.event?.clearInstanceListeners(map)
        maps.delete(root)
        map = marker = null
      },
    }
  }
}

export function map(root) {
  const config = settings(root)
  if (config.provider !== 'google' || !config.key) return
  if (typeof config.lat !== 'number' || typeof config.lng !== 'number') return

  return shell(root, config, driver(root))
}

webx.widget?.('map-google', '[data-webx-map]', map)
