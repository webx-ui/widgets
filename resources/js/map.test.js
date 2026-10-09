import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  document.cookie = 'webx_consent=; Max-Age=0; Path=/'
  vi.unstubAllGlobals()
})

const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'

// What `<x-webx-map>` prints: the place, and before consent the notice of §9.4 in it.
const map = (n, { blocked = true, marker = '1 Example Street, London' } = {}) => `
  <div class="webx-map${blocked ? ' is-blocked' : ''}" id="m${n}" role="region" aria-label="Map"
       data-webx-map='${JSON.stringify({ lat: 51.5074, lng: -0.1278, zoom: 15, tiles: TILES, maxZoom: 19, marker, hint: 'Click the map to zoom with the wheel' })}'
       data-webx-consent="media">
    <div class="webx-map__place">
      <p class="webx-map__address">1 Example Street, London</p>
      <a class="webx-map__open" href="https://www.openstreetmap.org/?mlat=51.5074&amp;mlon=-0.1278">Open in maps</a>
      ${
        blocked
          ? `<p class="webx-map__notice">The map loads from OpenStreetMap</p>
      <div class="webx-map__actions"><button type="button" class="webx-map__button" data-webx-map-load>Load</button>
      <button type="button" class="webx-map__button webx-map__button--always" data-webx-map-always>Always load maps</button></div>`
          : ''
      }
    </div>
    <p class="webx-map__attribution">© OpenStreetMap contributors</p>
  </div>`

// The banner's root, as the server prints it when the visitor has not answered yet.
const banner = `<div data-webx-consent-root data-version="1"><div data-webx-consent-banner hidden></div></div>`

async function start(html, { consent = false } = {}) {
  document.body.innerHTML = html + (consent ? banner : '')
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  const module = await import('./map.js')
  if (consent) await import('./consent.js')
  return module
}

const root = (n) => document.getElementById(`m${n}`)
const tiles = (n) => Array.from(root(n).querySelectorAll('img.leaflet-tile')).map((img) => img.src)

describe('map', () => {
  it('before consent asks for no tile, and the place with its notice stays', async () => {
    const { maps } = await start(map(1))

    expect(root(1).classList.contains('is-ready')).toBe(true)
    expect(maps.get(root(1))).toBeUndefined()
    expect(root(1).querySelector('.leaflet-container')).toBeNull()
    expect(document.querySelector('img')).toBeNull()
    expect(root(1).querySelector('.webx-map__place').hidden).toBe(false)
    expect(root(1).querySelector('.webx-map__notice')).not.toBeNull()
  })

  it('"Load" puts this map in, with its tiles and the pin, and gives no consent', async () => {
    const { maps } = await start(map(1) + map(2), { consent: true })

    root(1).querySelector('[data-webx-map-load]').click()

    const leaflet = maps.get(root(1))
    expect(leaflet).toBeDefined()
    expect(root(1).querySelector('.webx-map__canvas.leaflet-container')).not.toBeNull()
    expect(tiles(1).length).toBeGreaterThan(0)
    expect(tiles(1).every((src) => src.startsWith('https://tile.openstreetmap.org/15/'))).toBe(true)
    expect(leaflet.getCenter().lat).toBeCloseTo(51.5074)
    expect(root(1).classList.contains('is-loaded')).toBe(true)
    expect(root(1).classList.contains('is-blocked')).toBe(false)
    expect(root(1).querySelector('.webx-map__place').hidden).toBe(true)
    expect(root(1).querySelector('.webx-map__notice')).toBeNull()

    const pin = root(1).querySelector('.webx-map__marker')
    expect(pin.getAttribute('title')).toBe('1 Example Street, London')
    expect(pin.querySelector('svg')).not.toBeNull()
    // Leaflet's own attribution stays off: the server's is the one.
    expect(root(1).querySelector('.leaflet-control-attribution')).toBeNull()

    expect(maps.get(root(2))).toBeUndefined()
    expect(window.webx.consent.has('media')).toBe(false)
    expect(document.cookie).not.toContain('webx_consent')
  })

  it('"Always load maps" agrees to media: the cookie, the event, every map on the page', async () => {
    const { maps } = await start(map(1) + map(2), { consent: true })
    const heard = vi.fn()
    document.addEventListener('webx:consent', heard)

    root(2).querySelector('[data-webx-map-always]').click()

    expect(window.webx.consent.has('media')).toBe(true)
    expect(decodeURIComponent(document.cookie)).toContain('"media"')
    expect(heard).toHaveBeenCalled()
    expect(maps.get(root(1))).toBeDefined()
    expect(maps.get(root(2))).toBeDefined()
    expect(document.querySelector('.webx-map__notice')).toBeNull()
    document.removeEventListener('webx:consent', heard)
  })

  it('with consent it loads at once — when the frame comes near the screen, where that is known', async () => {
    const watched = []
    vi.stubGlobal(
      'IntersectionObserver',
      class {
        constructor(callback) {
          this.callback = callback
        }
        observe(el) {
          watched.push({ el, observer: this })
        }
        disconnect() {}
      },
    )
    const { maps } = await start(map(1, { blocked: false }))

    expect(maps.get(root(1))).toBeUndefined()
    expect(watched).toHaveLength(1)

    watched[0].observer.callback([{ isIntersecting: true, target: root(1) }])
    expect(maps.get(root(1))).toBeDefined()
  })

  it('a map mounted after the answer reads it itself', async () => {
    document.cookie = `webx_consent=${encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['media'] }))}; Path=/`
    const { maps } = await start('', { consent: true })
    // Printed blocked — a block the panel swapped in, a piece fetched later — and mounted then.
    document.body.insertAdjacentHTML('beforeend', map(1))
    window.webx.mount()

    expect(maps.get(root(1))).toBeDefined()
    expect(root(1).querySelector('.webx-map__notice')).toBeNull()
  })

  it('the wheel scrolls the page until the map is clicked, and a press elsewhere puts it back to sleep', async () => {
    const { maps } = await start(map(1, { blocked: false }))
    const leaflet = maps.get(root(1))

    expect(leaflet.scrollWheelZoom.enabled()).toBe(false)
    expect(leaflet.dragging.enabled()).toBe(true)

    // A wheel over the sleeping map says how to wake it, for a moment.
    root(1).dispatchEvent(new WheelEvent('wheel', { deltaY: 100, bubbles: true }))
    expect(root(1).querySelector('.webx-map__hint').classList.contains('is-visible')).toBe(true)
    expect(root(1).querySelector('.webx-map__hint').textContent).toBe(
      'Click the map to zoom with the wheel',
    )

    leaflet.fire('click')
    expect(leaflet.scrollWheelZoom.enabled()).toBe(true)
    expect(root(1).classList.contains('is-active')).toBe(true)
    expect(root(1).querySelector('.webx-map__hint').classList.contains('is-visible')).toBe(false)

    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    expect(leaflet.scrollWheelZoom.enabled()).toBe(false)
    expect(root(1).classList.contains('is-active')).toBe(false)

    // The focus wakes it too: the keyboard's way in.
    leaflet.fire('focus')
    expect(leaflet.scrollWheelZoom.enabled()).toBe(true)
    root(1).dispatchEvent(new MouseEvent('mouseleave'))
    expect(leaflet.scrollWheelZoom.enabled()).toBe(false)
  })

  it('on a touch screen one finger scrolls the page until the map is tapped', async () => {
    vi.stubGlobal('matchMedia', (query) => ({
      matches: query === '(pointer: coarse)',
      media: query,
    }))
    const { maps } = await start(map(1, { blocked: false }))
    const leaflet = maps.get(root(1))

    expect(leaflet.dragging.enabled()).toBe(false)
    expect(leaflet.touchZoom.enabled()).toBe(false)

    leaflet.fire('click')
    expect(leaflet.dragging.enabled()).toBe(true)
    expect(leaflet.touchZoom.enabled()).toBe(true)
  })

  it('no pin without a label to give it', async () => {
    const { maps } = await start(map(1, { blocked: false, marker: null }))

    expect(maps.get(root(1))).toBeDefined()
    expect(root(1).querySelector('.webx-map__marker')).toBeNull()
  })

  it('mounts once, and unmounting lets Leaflet go and listens no more', async () => {
    const { maps } = await start(map(1, { blocked: false }))
    const leaflet = maps.get(root(1))
    const remove = vi.spyOn(leaflet, 'remove')

    window.webx.mount(document)
    expect(root(1).querySelectorAll('.webx-map__canvas')).toHaveLength(1)

    window.webx.unmount(document)

    expect(remove).toHaveBeenCalledOnce()
    expect(maps.get(root(1))).toBeUndefined()
    expect(root(1).querySelector('.webx-map__canvas')).toBeNull()
    expect(root(1).querySelector('.webx-map__place').hidden).toBe(false)
    expect(root(1).classList.contains('is-ready')).toBe(false)
    expect(root(1).classList.contains('is-loaded')).toBe(false)

    // An answer after the map went: nothing comes back.
    document.dispatchEvent(new CustomEvent('webx:consent', { detail: { categories: ['media'] } }))
    expect(root(1).querySelector('.webx-map__canvas')).toBeNull()

    // And mounted again, it is a map again.
    window.webx.mount(document)
    expect(maps.get(root(1))).toBeDefined()
    expect(maps.get(root(1))).not.toBe(leaflet)
  })

  it('the place blocked, then unmounted and mounted again before any answer, waits as before', async () => {
    const { maps } = await start(map(1))

    window.webx.unmount(document)
    window.webx.mount(document)

    expect(maps.get(root(1))).toBeUndefined()
    expect(root(1).querySelector('[data-webx-map-load]')).not.toBeNull()
  })
})
