import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// jsdom has no layout and no observers: each test says where things stand and fires them itself.
let watched = []
let resized = []
let reduced = false
let coarse = false

beforeEach(() => {
  watched = []
  resized = []
  reduced = false
  coarse = false
  vi.stubGlobal(
    'IntersectionObserver',
    class {
      constructor(callback) {
        this.callback = callback
        this.els = new Set()
      }
      observe(el) {
        this.els.add(el)
        watched.push(this)
      }
      unobserve(el) {
        this.els.delete(el)
      }
      disconnect() {
        this.els.clear()
      }
      show(...els) {
        this.callback(els.map((target) => ({ target, isIntersecting: true })))
      }
    },
  )
  vi.stubGlobal(
    'ResizeObserver',
    class {
      constructor(callback) {
        this.callback = callback
        resized.push(this)
      }
      observe() {}
      disconnect() {}
    },
  )
  vi.stubGlobal('matchMedia', (query) => ({
    matches: (query.includes('reduce') && reduced) || (query.includes('coarse') && coarse),
    media: query,
  }))
})

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  vi.unstubAllGlobals()
})

async function start(html, ...files) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  for (const file of files) await import(`./${file}.js`)
}

/** Where an element stands on the screen, for a test with no layout. */
const at = (el, top, height = 100) => {
  el.getBoundingClientRect = () => ({
    top,
    bottom: top + height,
    height,
    left: 0,
    right: 0,
    width: 0,
  })
}

describe('reveal', () => {
  const page = `
    <p id="above" data-webx-reveal>above</p>
    <p id="below" data-webx-reveal="scale">below</p>
    <ul id="list" data-webx-reveal="stagger" data-webx-reveal-effect="fade"><li>1</li><li>2</li><li>3</li></ul>`

  const place = () => {
    at(document.getElementById('above'), 100)
    at(document.getElementById('below'), 2000)
    document.querySelectorAll('#list li').forEach((li, i) => at(li, 3000 + i * 50))
  }

  it('hides only what is below the fold, and shows it once it comes in', async () => {
    document.body.innerHTML = page
    place()
    // The runtime mounts what is on the page as it loads: placed first, then started.
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')

    const above = document.getElementById('above')
    const below = document.getElementById('below')
    expect(above.className).toBe('')
    expect(below.classList.contains('webx-reveal')).toBe(true)
    expect(below.classList.contains('webx-reveal--scale')).toBe(true)
    expect(below.classList.contains('is-pending')).toBe(true)

    const observer = watched.find((o) => o.els.has(below))
    observer.show(below)
    expect(below.classList.contains('is-pending')).toBe(false)

    // Its own transitions back once this one is over.
    below.dispatchEvent(Object.assign(new Event('transitionend'), { propertyName: 'opacity' }))
    expect(below.className).toBe('')
  })

  it('brings the children of a stagger in one after another, in their order', async () => {
    document.body.innerHTML = page
    place()
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')

    const items = Array.from(document.querySelectorAll('#list li'))
    expect(document.getElementById('list').className).toBe('')
    for (const li of items) {
      expect(li.classList.contains('webx-reveal--fade')).toBe(true)
      expect(li.classList.contains('is-pending')).toBe(true)
    }

    const observer = watched.find((o) => o.els.has(items[0]))
    // Entries arrive in any order; the delay follows the list's.
    observer.show(items[2], items[0], items[1])
    expect(items[0].style.getPropertyValue('--webx-reveal-delay')).toBe('')
    expect(items[1].style.getPropertyValue('--webx-reveal-delay')).toBe(
      'calc(1 * var(--webx-reveal-stagger))',
    )
    expect(items[2].style.getPropertyValue('--webx-reveal-delay')).toBe(
      'calc(2 * var(--webx-reveal-stagger))',
    )
  })

  it('hides nothing with reduced motion, and shows everything again when unmounted', async () => {
    reduced = true
    document.body.innerHTML = page
    place()
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')
    expect(document.querySelectorAll('.is-pending').length).toBe(0)

    reduced = false
    window.webx.unmount()
    window.webx.mount()
    expect(document.querySelectorAll('.is-pending').length).toBe(4)
    window.webx.unmount()
    expect(document.querySelectorAll('.webx-reveal, .is-pending').length).toBe(0)
  })

  it('shows everything before printing', async () => {
    document.body.innerHTML = page
    place()
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')
    expect(document.querySelectorAll('.is-pending').length).toBe(4)
    window.dispatchEvent(new Event('beforeprint'))
    expect(document.querySelectorAll('.is-pending').length).toBe(0)
  })
})

describe('table', () => {
  // What `Prose\Tables` prints around a table of prose.
  const table = `<div class="webx-table" data-webx-table><div class="webx-table__frame">
    <div class="webx-table__scroller" tabindex="0" role="region" aria-label="Table"><table class="webx-table__table"></table></div></div></div>`

  const size = (scroller, scrollWidth, clientWidth, scrollLeft = 0) => {
    Object.defineProperty(scroller, 'scrollWidth', { configurable: true, value: scrollWidth })
    Object.defineProperty(scroller, 'clientWidth', { configurable: true, value: clientWidth })
    scroller.scrollLeft = scrollLeft
  }

  it('draws the shadow on the side there is more behind, and takes the tab stop off a table that fits', async () => {
    await start(table, 'table')
    const frame = document.querySelector('.webx-table__frame')
    const scroller = document.querySelector('.webx-table__scroller')

    // Fits: nothing to scroll, nothing to focus.
    expect(scroller.hasAttribute('tabindex')).toBe(false)
    expect(frame.className).toBe('webx-table__frame')

    size(scroller, 900, 300)
    resized.forEach((o) => o.callback())
    expect(scroller.getAttribute('tabindex')).toBe('0')
    expect(frame.classList.contains('is-more-right')).toBe(true)
    expect(frame.classList.contains('is-more-left')).toBe(false)

    size(scroller, 900, 300, 300)
    scroller.dispatchEvent(new Event('scroll'))
    expect(frame.classList.contains('is-more-left')).toBe(true)
    expect(frame.classList.contains('is-more-right')).toBe(true)

    size(scroller, 900, 300, 600)
    scroller.dispatchEvent(new Event('scroll'))
    expect(frame.classList.contains('is-more-left')).toBe(true)
    expect(frame.classList.contains('is-more-right')).toBe(false)
  })

  it('reads right to left the other way round', async () => {
    await start(`<div dir="rtl">${table}</div>`, 'table')
    const frame = document.querySelector('.webx-table__frame')
    const scroller = document.querySelector('.webx-table__scroller')
    scroller.style.direction = 'rtl'

    size(scroller, 900, 300, 0)
    scroller.dispatchEvent(new Event('scroll'))
    expect(frame.classList.contains('is-more-left')).toBe(true)
    expect(frame.classList.contains('is-more-right')).toBe(false)

    size(scroller, 900, 300, -600)
    scroller.dispatchEvent(new Event('scroll'))
    expect(frame.classList.contains('is-more-left')).toBe(false)
    expect(frame.classList.contains('is-more-right')).toBe(true)
  })

  it('leaves the table scrollable by the keyboard when unmounted', async () => {
    await start(table, 'table')
    window.webx.unmount()
    expect(document.querySelector('.webx-table__scroller').getAttribute('tabindex')).toBe('0')
    expect(document.querySelector('.webx-table__frame').className).toBe('webx-table__frame')
  })
})

describe('back to top', () => {
  const button = (corner = 'bottom-end') =>
    `<a class="webx-back-to-top webx-back-to-top--${corner}" href="#top" data-webx-back-to-top='{"after":2}' aria-label="Back to top"></a>`

  const scrollTo = (y) => {
    window.scrollY = y
    window.dispatchEvent(new Event('scroll'))
  }

  beforeEach(() => {
    // At once, and no frame left pending: the next scroll asks for one again.
    vi.stubGlobal('requestAnimationFrame', (callback) => {
      callback()
      return 0
    })
    window.innerHeight = 800
    window.scrollY = 0
  })

  it('comes after two screens, and goes back when the page is up again', async () => {
    await start(button(), 'back-to-top')
    const root = document.querySelector('.webx-back-to-top')
    expect(root.classList.contains('is-ready')).toBe(true)
    expect(root.classList.contains('is-shown')).toBe(false)

    scrollTo(1600)
    expect(root.classList.contains('is-shown')).toBe(false)
    scrollTo(1601)
    expect(root.classList.contains('is-shown')).toBe(true)
    scrollTo(10)
    expect(root.classList.contains('is-shown')).toBe(false)
  })

  it('scrolls up smoothly, or at once with reduced motion, and leaves the focus at the top', async () => {
    const scroll = vi.fn()
    vi.stubGlobal('scrollTo', scroll)
    await start(`<a href="#skip">Skip</a>${button()}`, 'back-to-top')
    const root = document.querySelector('.webx-back-to-top')
    root.focus()

    root.click()
    expect(scroll).toHaveBeenLastCalledWith({ top: 0, behavior: 'smooth' })
    expect(document.activeElement).toBe(document.body)
    expect(document.body.getAttribute('tabindex')).toBe('-1')
    document.body.dispatchEvent(new Event('blur'))
    expect(document.body.hasAttribute('tabindex')).toBe(false)

    reduced = true
    root.click()
    expect(scroll).toHaveBeenLastCalledWith({ top: 0, behavior: 'auto' })
  })

  it('stands above the banner, the contact bar and the quick contact of its own corner', async () => {
    await start(
      `${button()}<div data-webx-consent-banner id="banner"></div>
       <div class="webx-contact-button webx-contact-button--bottom-start" data-webx-contact-button id="other"></div>`,
      'back-to-top',
    )
    const root = document.querySelector('.webx-back-to-top')
    at(document.getElementById('banner'), 650, 150)
    at(document.getElementById('other'), 500, 56)
    scrollTo(5)
    expect(root.style.getPropertyValue('--webx-back-to-top-lift')).toBe('150px')

    document.getElementById('banner').hidden = true
    scrollTo(6)
    // The quick contact in the other corner does not lift it.
    expect(root.style.getPropertyValue('--webx-back-to-top-lift')).toBe('0px')

    document.getElementById('other').className =
      'webx-contact-button webx-contact-button--bottom-end'
    scrollTo(7)
    expect(root.style.getPropertyValue('--webx-back-to-top-lift')).toBe('300px')

    window.webx.unmount()
    expect(root.style.getPropertyValue('--webx-back-to-top-lift')).toBe('')
    expect(root.classList.contains('is-ready')).toBe(false)
  })
})

describe('share', () => {
  const share = `<div class="webx-share" role="group" aria-label="Share"
      data-webx-share='{"url":"https://example.test/a","title":"A page","copied":"Link copied","failed":"No luck"}'>
    <ul class="webx-share__list"><li><a class="webx-share__link" href="https://t.me/share/url?url=x">t</a></li>
    <li><button type="button" class="webx-share__link webx-share__link--copy" data-webx-share-copy>Copy</button></li></ul>
    <button type="button" class="webx-share__native" data-webx-share-native hidden>Share</button>
    <span class="webx-share__status" role="status"></span></div>`

  it('copies the address and says so', async () => {
    const writeText = vi.fn(() => Promise.resolve())
    vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } })
    await start(share, 'share')

    document.querySelector('[data-webx-share-copy]').click()
    await vi.waitFor(() =>
      expect(document.querySelector('.webx-share__status').textContent).toBe('Link copied'),
    )
    expect(writeText).toHaveBeenCalledWith('https://example.test/a')
  })

  it('says when the address could not be copied', async () => {
    vi.stubGlobal('navigator', {
      ...navigator,
      clipboard: { writeText: () => Promise.reject(new Error('denied')) },
    })
    document.execCommand = () => false
    await start(share, 'share')

    document.querySelector('[data-webx-share-copy]').click()
    await vi.waitFor(() =>
      expect(document.querySelector('.webx-share__status').textContent).toBe('No luck'),
    )
  })

  it('on a phone opens the system share sheet instead of the row of links', async () => {
    coarse = true
    const sheet = vi.fn(() => Promise.resolve())
    vi.stubGlobal('navigator', { ...navigator, share: sheet })
    await start(share, 'share')

    const root = document.querySelector('.webx-share')
    const native = root.querySelector('[data-webx-share-native]')
    expect(root.classList.contains('is-native')).toBe(true)
    expect(native.hidden).toBe(false)
    expect(root.querySelector('.webx-share__list').hidden).toBe(true)

    native.click()
    expect(sheet).toHaveBeenCalledWith({ url: 'https://example.test/a', title: 'A page' })

    window.webx.unmount()
    expect(native.hidden).toBe(true)
    expect(root.querySelector('.webx-share__list').hidden).toBe(false)
  })

  it('keeps the links on a desktop browser that has a share sheet too', async () => {
    vi.stubGlobal('navigator', { ...navigator, share: vi.fn() })
    await start(share, 'share')

    expect(document.querySelector('[data-webx-share-native]').hidden).toBe(true)
    expect(document.querySelector('.webx-share__list').hidden).toBe(false)
  })
})
