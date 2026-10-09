import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// jsdom has <dialog> but not its modal half; enough of it for the behaviour to be observable.
if (!HTMLDialogElement.prototype.showModal) {
  HTMLDialogElement.prototype.showModal = function () {
    this.setAttribute('open', '')
  }
  HTMLDialogElement.prototype.close = function () {
    if (!this.hasAttribute('open')) return
    this.removeAttribute('open')
    this.dispatchEvent(new Event('close'))
  }
}

// The MutationObserver reports after a microtask.
const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

const row = (category, hidden = true) => `
  <li data-webx-consent-row="${category}"${hidden ? ' hidden' : ''}>
    <input type="checkbox" role="switch" name="${category}" data-webx-consent-category="${category}"${category === 'necessary' ? ' checked disabled' : ''}>
  </li>`

// What the server prints before </body>: see resources/views/consent.blade.php.
const banner = ({ version = '1', off = false, gpc = false } = {}) => `
  <div data-webx-consent-root data-version="${version}" data-lifetime="365"${off ? ' data-off' : ''}${gpc ? ' data-gpc' : ''}>
    <section data-webx-consent-banner hidden>
      <button type="button" data-webx-consent-reject>Reject all</button>
      <button type="button" data-webx-consent-accept>Accept all</button>
      <button type="button" data-webx-dialog="webx-consent">Customize</button>
    </section>
    <dialog id="webx-consent">
      <p data-webx-consent-gpc hidden></p>
      <ul>${['necessary', 'preferences', 'statistics', 'marketing', 'media'].map((c) => row(c)).join('')}</ul>
      <button type="button" data-webx-consent-reject>Reject all</button>
      <button type="button" data-webx-consent-save>Save</button>
      <button type="button" data-webx-consent-accept>Accept all</button>
    </dialog>
  </div>`

async function start(html) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./consent.js')
  return window.webx
}

const answer = () => {
  const raw = document.cookie
    .split('; ')
    .find((pair) => pair.startsWith('webx_consent='))
    ?.slice('webx_consent='.length)
  return raw ? JSON.parse(decodeURIComponent(raw)) : null
}

const click = (selector) => document.querySelector(selector).click()

beforeEach(() => {
  document.documentElement.className = ''
  document.cookie = 'webx_consent=; Path=/; Max-Age=0'
  delete window.dataLayer
  delete window.gtag
  delete window.ran
  history.replaceState(null, '', '/')
})

afterEach(() => {
  document.body.innerHTML = ''
  delete window.webx
  vi.restoreAllMocks()
})

describe('the consent banner', () => {
  it('shows until answered, and not again once the answer is to the current version', async () => {
    await start(banner())
    expect(document.querySelector('[data-webx-consent-banner]').hidden).toBe(false)

    click('[data-webx-consent-banner] [data-webx-consent-reject]')
    expect(document.querySelector('[data-webx-consent-banner]').hidden).toBe(true)
    expect(answer()).toMatchObject({ v: '1', c: [] })
    expect(answer().d).toMatch(/^\d{4}-\d{2}-\d{2}$/)

    await start(banner())
    expect(document.querySelector('[data-webx-consent-banner]').hidden).toBe(true)

    // The policy changed: everyone is asked again.
    await start(banner({ version: '2' }))
    expect(document.querySelector('[data-webx-consent-banner]').hidden).toBe(false)
  })

  it('accepts all and tells the page, with webx.consent.has and the webx:consent event', async () => {
    const webx = await start(banner())
    const heard = vi.fn()
    document.addEventListener('webx:consent', heard, { once: true })

    expect(webx.consent.has('necessary')).toBe(true)
    expect(webx.consent.has('media')).toBe(false)

    click('[data-webx-consent-banner] [data-webx-consent-accept]')

    expect(answer().c).toEqual(['preferences', 'statistics', 'marketing', 'media'])
    expect(webx.consent.has('media')).toBe(true)
    expect(heard.mock.calls[0][0].detail.categories).toEqual([
      'preferences',
      'statistics',
      'marketing',
      'media',
    ])
  })

  it('saves what was switched on in the dialog, opened from the banner', async () => {
    await start(banner())

    click('[data-webx-dialog="webx-consent"]')
    await settle()
    const dialog = document.getElementById('webx-consent')
    expect(dialog.open).toBe(true)

    dialog.querySelector('[name="statistics"]').checked = true
    click('[data-webx-consent-save]')

    expect(answer().c).toEqual(['statistics'])
    expect(dialog.open).toBe(false)
    expect(document.querySelector('[data-webx-consent-banner]').hidden).toBe(true)
  })

  it('offers a category the page asks for and one already granted, not the others', async () => {
    await start(
      banner() + '<iframe data-webx-consent="media" data-src="https://video.example/e"></iframe>',
    )
    const shown = () =>
      Array.from(document.querySelectorAll('[data-webx-consent-row]:not([hidden])')).map((el) =>
        el.getAttribute('data-webx-consent-row'),
      )
    expect(shown()).toEqual(['media'])

    window.webx.consent.set(['marketing'])
    click('[data-webx-dialog="webx-consent"]')
    await settle()
    expect(shown()).toEqual(['marketing', 'media'])
    expect(document.querySelector('[name="marketing"]').checked).toBe(true)
  })

  it('leaves marketing out of "Accept all" when the browser sends Global Privacy Control', async () => {
    await start(banner({ gpc: true }))
    expect(document.querySelector('[data-webx-consent-gpc]').hidden).toBe(false)

    click('[data-webx-consent-banner] [data-webx-consent-accept]')

    expect(answer().c).toEqual(['preferences', 'statistics', 'media'])
  })

  it('reads navigator.globalPrivacyControl as well as the header', async () => {
    Object.defineProperty(navigator, 'globalPrivacyControl', { value: true, configurable: true })
    try {
      await start(banner())
      click('[data-webx-consent-banner] [data-webx-consent-accept]')
      expect(answer().c).not.toContain('marketing')
    } finally {
      delete navigator.globalPrivacyControl
    }
  })
})

describe('what waits for consent', () => {
  const page = `
    <script type="text/plain" data-webx-consent="statistics" id="inline">window.ran = (window.ran ?? 0) + 1</script>
    <script type="text/plain" data-webx-consent="statistics" data-webx-type="module" src="https://counter.example/c.js" id="external"></script>
    <iframe data-webx-consent="media" data-src="https://video.example/e"></iframe>
    <template data-webx-consent="marketing"><p class="pixel">pixel</p><script>window.ran = 'template'</script></template>`

  it('stays inert until its category is agreed to', async () => {
    await start(banner() + page)

    expect(document.querySelectorAll('script[type="text/plain"]')).toHaveLength(2)
    expect(document.querySelector('iframe').hasAttribute('src')).toBe(false)
    expect(document.querySelector('.pixel')).toBeNull()
  })

  it('switches on a category at a time: scripts re-created, iframes given their src, templates unpacked', async () => {
    const webx = await start(banner() + page)

    webx.consent.set(['statistics'])
    const inline = document.getElementById('inline')
    expect(inline.getAttribute('type')).toBeNull()
    expect(inline.hasAttribute('data-webx-consent')).toBe(false)
    const external = document.getElementById('external')
    expect(external.type).toBe('module')
    expect(external.getAttribute('src')).toBe('https://counter.example/c.js')
    expect(document.querySelector('iframe').hasAttribute('src')).toBe(false)

    webx.consent.set(['statistics', 'media', 'marketing'])
    expect(document.querySelector('iframe').getAttribute('src')).toBe('https://video.example/e')
    expect(document.querySelector('iframe').hasAttribute('data-src')).toBe(false)
    expect(document.querySelector('.pixel')).not.toBeNull()
    expect(document.querySelector('template')).toBeNull()
  })

  it('starts at once what an earlier answer agreed to, and what arrives later through webx.mount', async () => {
    document.cookie = `webx_consent=${encodeURIComponent(JSON.stringify({ v: '1', c: ['media'] }))}; Path=/`
    const webx = await start(banner())

    document.body.insertAdjacentHTML(
      'beforeend',
      '<div id="late"><iframe data-webx-consent="media" data-src="https://video.example/l"></iframe></div>',
    )
    webx.mount(document.getElementById('late'))

    expect(document.querySelector('#late iframe').getAttribute('src')).toBe(
      'https://video.example/l',
    )
  })

  it('starts everything when the banner is off', async () => {
    await start(banner({ off: true }) + page)

    expect(document.querySelectorAll('script[type="text/plain"]')).toHaveLength(0)
    expect(document.querySelector('iframe').getAttribute('src')).toBe('https://video.example/e')
  })

  it('reloads the page when an answer is taken back: a script that ran cannot be unloaded', async () => {
    document.cookie = `webx_consent=${encodeURIComponent(JSON.stringify({ v: '1', c: ['statistics'] }))}; Path=/`
    const reload = vi.fn()
    vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, reload })
    const webx = await start(banner())

    webx.consent.set(['statistics', 'media'])
    expect(reload).not.toHaveBeenCalled()

    webx.consent.set(['media'])
    expect(reload).toHaveBeenCalledOnce()
    expect(answer().c).toEqual(['media'])
  })
})

describe('Google Consent Mode v2', () => {
  it('sends the update after the answer, through gtag when there is one', async () => {
    window.gtag = vi.fn()
    const webx = await start(banner())

    webx.consent.set(['statistics'])

    expect(window.gtag).toHaveBeenCalledWith(
      'consent',
      'update',
      expect.objectContaining({
        analytics_storage: 'granted',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
      }),
    )
  })

  it('pushes to the dataLayer the default was pushed to when gtag is not there yet', async () => {
    window.dataLayer = []
    const webx = await start(banner())

    webx.consent.set(['marketing'])

    const [command, action, modes] = Array.from(window.dataLayer.at(-1))
    expect([command, action, modes.ad_storage, modes.analytics_storage]).toEqual([
      'consent',
      'update',
      'granted',
      'denied',
    ])
  })
})
