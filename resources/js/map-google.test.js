import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  document.head.querySelectorAll('script').forEach((script) => script.remove())
  document.documentElement.removeAttribute('data-theme')
  document.cookie = 'webx_consent=; Max-Age=0; Path=/'
  delete window.google
  delete window.gm_authFailure
  delete window.__webxGoogleMaps
  vi.unstubAllGlobals()
})

// What `<x-webx-map>` prints when the site chose Google with a key: the frame of Leaflet's map,
// the notice before consent, and no attribution — Google draws its own.
const map = (
  n,
  { blocked = true, marker = '1 Example Street, Sample City', mapId = null } = {},
) => `
  <div class="webx-map${blocked ? ' is-blocked' : ''}" id="m${n}" role="region" aria-label="Map"
       data-webx-map='${JSON.stringify({ provider: 'google', key: 'test-key', mapId, language: 'de', lat: 12.34, lng: -34.56, zoom: 15, maxZoom: 21, marker, hint: 'Click the map', failed: 'The map could not be loaded.' })}'
       data-webx-consent="media">
    <div class="webx-map__place">
      <p class="webx-map__address">1 Example Street, Sample City</p>
      <a class="webx-map__open" href="https://www.google.com/maps/search/?api=1&amp;query=12.34,-34.56">Open in maps</a>
      ${
        blocked
          ? `<p class="webx-map__notice">The map loads from Google Maps</p>
      <div class="webx-map__actions"><button type="button" class="webx-map__button" data-webx-map-load>Load</button>
      <button type="button" class="webx-map__button webx-map__button--always" data-webx-map-always>Always load maps</button></div>`
          : ''
      }
    </div>
  </div>`

const banner = `<div data-webx-consent-root data-version="1"><div data-webx-consent-banner hidden></div></div>`

// The part of the Maps JavaScript API the script uses, recording what it was asked.
function fakeGoogle() {
  const made = { maps: [], markers: [] }
  class FakeMap {
    constructor(element, options) {
      this.element = element
      this.options = { ...options }
      this.listeners = {}
      made.maps.push(this)
    }
    setOptions(options) {
      Object.assign(this.options, options)
    }
    addListener(name, callback) {
      this.listeners[name] = callback
    }
  }
  class FakeMarker {
    constructor(options) {
      this.options = options
      this.kind = 'classic'
      made.markers.push(this)
    }
    setMap(map) {
      this.options.map = map
    }
  }
  class FakeAdvanced {
    constructor(options) {
      this.options = options
      this.kind = 'advanced'
      this.map = options.map
      made.markers.push(this)
    }
  }
  class Size {
    constructor(w, h) {
      Object.assign(this, { w, h })
    }
  }
  class Point {
    constructor(x, y) {
      Object.assign(this, { x, y })
    }
  }
  const maps = {
    ColorScheme: { LIGHT: 'LIGHT', DARK: 'DARK', FOLLOW_SYSTEM: 'FOLLOW_SYSTEM' },
    event: { clearInstanceListeners: vi.fn() },
    importLibrary: async (name) =>
      ({
        maps: { Map: FakeMap },
        core: { Size, Point },
        marker: { Marker: FakeMarker, AdvancedMarkerElement: FakeAdvanced },
      })[name],
  }
  return { maps, made }
}

async function start(html, { consent = false } = {}) {
  document.body.innerHTML = html + (consent ? banner : '')
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  const module = await import('./map-google.js')
  if (consent) await import('./consent.js')
  return module
}

const root = (n) => document.getElementById(`m${n}`)
const scripts = () =>
  Array.from(document.querySelectorAll('script')).filter((s) =>
    s.src.startsWith('https://maps.googleapis.com/'),
  )

// Google answers: the API is there and its callback is called.
async function answer() {
  const fake = fakeGoogle()
  window.google = { maps: fake.maps }
  window.__webxGoogleMaps()
  await vi.waitFor(() => expect(fake.made.maps.length).toBeGreaterThan(0))
  return fake.made
}

describe('map on Google', () => {
  it('before consent asks Google for nothing, and the place with its notice stays', async () => {
    await start(map(1))

    expect(root(1).classList.contains('is-ready')).toBe(true)
    expect(scripts()).toHaveLength(0)
    expect(root(1).querySelector('.webx-map__canvas')).toBeNull()
    expect(root(1).querySelector('.webx-map__notice')).not.toBeNull()
  })

  it('"Load" asks for the API once with the key and the language, and puts the map in asleep', async () => {
    const { maps } = await start(map(1) + map(2), { consent: true })

    root(1).querySelector('[data-webx-map-load]').click()

    expect(scripts()).toHaveLength(1)
    const url = new URL(scripts()[0].src)
    expect(url.origin + url.pathname).toBe('https://maps.googleapis.com/maps/api/js')
    expect(url.searchParams.get('key')).toBe('test-key')
    expect(url.searchParams.get('language')).toBe('de')
    expect(url.searchParams.get('loading')).toBe('async')
    expect(url.searchParams.get('callback')).toBe('__webxGoogleMaps')

    const made = await answer()
    const google = made.maps[0]
    expect(maps.get(root(1))).toBe(google)
    expect(google.element.classList.contains('webx-map__canvas')).toBe(true)
    expect(google.options).toMatchObject({
      center: { lat: 12.34, lng: -34.56 },
      zoom: 15,
      gestureHandling: 'cooperative',
      colorScheme: 'LIGHT',
      zoomControl: true,
    })
    expect(root(1).querySelector('.webx-map__place').hidden).toBe(true)
    expect(root(1).classList.contains('is-loaded')).toBe(true)

    // The pin is the package's picture, a classic marker without a Map ID.
    expect(made.markers).toHaveLength(1)
    expect(made.markers[0].kind).toBe('classic')
    expect(made.markers[0].options.title).toBe('1 Example Street, Sample City')
    expect(decodeURIComponent(made.markers[0].options.icon.url)).toContain('<svg')

    // No consent given: the second map waits.
    expect(maps.get(root(2))).toBeUndefined()
    expect(window.webx.consent.has('media')).toBe(false)
  })

  it('"Always load maps" agrees to media and every map of the page shares the one script', async () => {
    const { maps } = await start(map(1) + map(2), { consent: true })

    root(2).querySelector('[data-webx-map-always]').click()

    expect(window.webx.consent.has('media')).toBe(true)
    expect(scripts()).toHaveLength(1)
    const made = await answer()
    await vi.waitFor(() => expect(made.maps).toHaveLength(2))
    expect(maps.get(root(1))).toBeDefined()
    expect(maps.get(root(2))).toBeDefined()
  })

  it('a click wakes it — greedy — and a press elsewhere puts it back to sleep; no hint of its own', async () => {
    const { maps } = await start(map(1, { blocked: false }))
    await answer()
    const google = maps.get(root(1))

    root(1).dispatchEvent(new WheelEvent('wheel', { deltaY: 100, bubbles: true }))
    expect(root(1).querySelector('.webx-map__hint')).toBeNull()

    google.listeners.click()
    expect(google.options.gestureHandling).toBe('greedy')
    expect(root(1).classList.contains('is-active')).toBe(true)

    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    expect(google.options.gestureHandling).toBe('cooperative')
    expect(root(1).classList.contains('is-active')).toBe(false)
  })

  it('a Map ID makes the pin an advanced marker, and a dark site a dark map', async () => {
    document.documentElement.setAttribute('data-theme', 'dark')
    await start(map(1, { blocked: false, mapId: 'abc123' }))
    const made = await answer()

    expect(made.maps[0].options.mapId).toBe('abc123')
    expect(made.maps[0].options.colorScheme).toBe('DARK')
    expect(made.markers[0].kind).toBe('advanced')
    expect(made.markers[0].options.content.querySelector('svg')).not.toBeNull()
  })

  it('a key Google refuses is the place again, saying the map could not be loaded', async () => {
    await start(map(1, { blocked: false }))
    await answer()

    window.gm_authFailure()

    expect(root(1).querySelector('.webx-map__canvas')).toBeNull()
    expect(root(1).querySelector('.webx-map__place').hidden).toBe(false)
    expect(root(1).classList.contains('is-failed')).toBe(true)
    expect(root(1).classList.contains('is-loaded')).toBe(false)
    expect(root(1).querySelector('.webx-map__failed').textContent).toBe(
      'The map could not be loaded.',
    )
  })

  it('a script that never comes is the place again too', async () => {
    await start(map(1, { blocked: false }))

    scripts()[0].dispatchEvent(new Event('error'))

    await vi.waitFor(() => expect(root(1).classList.contains('is-failed')).toBe(true))
    expect(root(1).querySelector('.webx-map__place').hidden).toBe(false)
    expect(root(1).querySelector('.webx-map__failed')).not.toBeNull()
  })

  it('a map of Leaflet is not its own, and unmounting lets Google go', async () => {
    const leaflet = `<div class="webx-map" id="m9" data-webx-map='{"lat":1,"lng":2,"tiles":"https://tile.example.com/{z}/{x}/{y}.png"}'></div>`
    const { maps } = await start(map(1, { blocked: false }) + leaflet)
    const made = await answer()

    expect(root(9).classList.contains('is-ready')).toBe(false)

    window.webx.unmount(document)
    expect(maps.get(root(1))).toBeUndefined()
    expect(window.google.maps.event.clearInstanceListeners).toHaveBeenCalledWith(made.maps[0])
    expect(root(1).querySelector('.webx-map__canvas')).toBeNull()
    expect(root(1).classList.contains('is-ready')).toBe(false)
  })
})
