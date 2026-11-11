import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  document.cookie = 'webx_consent=; Max-Age=0; Path=/'
  vi.unstubAllGlobals()
})

const SRC =
  'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d2483.0!2d-0.128!3d51.508!5e0!3m2!1sen!2suk'

// What `<x-webx-map embed>` prints: the frame of the map's height, the place, and the notice
// before consent — no <iframe> until the script puts one in.
const map = (n, { blocked = true, src = SRC } = {}) => `
  <div class="webx-map webx-map--embed${blocked ? ' is-blocked' : ''}" id="m${n}" role="region" aria-label="Map"
       style="--webx-map-height: 24rem; --webx-map-height-narrow: 16rem"
       data-webx-map='${JSON.stringify({ provider: 'embed', src, title: 'Map of the square' })}'
       data-webx-consent="media">
    <div class="webx-map__place">
      <a class="webx-map__open" href="https://www.google.com/maps/search/?api=1&amp;query=51.508,-0.128">Open in maps</a>
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

async function start(html, { consent = false } = {}) {
  document.body.innerHTML = html + (consent ? banner : '')
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  const module = await import('./map-embed.js')
  if (consent) await import('./consent.js')
  return module
}

describe('map-embed', () => {
  it('puts no frame in before consent, and Google’s frame for "Load"', async () => {
    await start(map(1))
    const root = document.getElementById('m1')

    expect(root.querySelector('iframe')).toBeNull()
    expect(root.classList.contains('is-ready')).toBe(true)

    root.querySelector('[data-webx-map-load]').click()

    const frame = root.querySelector('iframe')
    expect(frame).not.toBeNull()
    expect(frame.src).toBe(SRC)
    expect(frame.title).toBe('Map of the square')
    expect(frame.getAttribute('loading')).toBe('lazy')
    expect(frame.getAttribute('referrerpolicy')).toBe('no-referrer-when-downgrade')
    expect(frame.className).toBe('webx-map__frame')
    expect(root.classList.contains('is-blocked')).toBe(false)
    expect(root.querySelector('.webx-map__place').hidden).toBe(true)
    expect(root.querySelector('.webx-map__hint')).toBeNull()
  })

  it('puts it in at once where the server printed no notice', async () => {
    await start(map(2, { blocked: false }))

    expect(document.querySelector('#m2 iframe')?.src).toBe(SRC)
  })

  it('leaves alone the frame the server printed for a map that does not wait', async () => {
    const printed = map(6, { blocked: false }).replace(
      '<div class="webx-map__place">',
      `<div class="webx-map__canvas"><iframe class="webx-map__frame" src="${SRC}" title="Map of the square"></iframe></div>
    <div class="webx-map__place" hidden>`,
    )
    await start(printed)
    const root = document.getElementById('m6')

    expect(root.querySelectorAll('iframe')).toHaveLength(1)
    expect(root.classList.contains('is-ready')).toBe(false)
    expect(root.querySelector('.webx-map__place').hidden).toBe(true)
  })

  it('takes no address but Google’s, whatever the attribute says', async () => {
    await start(map(3, { blocked: false, src: 'https://evil.example.com/maps/embed?pb=1' }))

    expect(document.querySelector('#m3 iframe')).toBeNull()
    expect(document.getElementById('m3').classList.contains('is-ready')).toBe(false)
  })

  it('"Always load maps" gives the consent to media and loads every map of the page', async () => {
    await start(map(4) + map(5), { consent: true })

    document.querySelector('#m4 [data-webx-map-always]').click()

    expect(document.querySelector('#m4 iframe')).not.toBeNull()
    expect(document.querySelector('#m5 iframe')).not.toBeNull()
    expect(document.cookie).toContain('media')
  })
})
