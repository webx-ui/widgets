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
      // What a real observer says of every element right after observe(): where it stands.
      look() {
        this.callback(
          [...this.els].map((target) => {
            const rect = target.getBoundingClientRect()
            return {
              target,
              isIntersecting: rect.top < window.innerHeight && rect.bottom > 0,
              boundingClientRect: rect,
            }
          }),
        )
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

  // The observers' first word, as the browser gives it once the page is laid out.
  const look = () => new Set(watched).forEach((observer) => observer.look())

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
    look()

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
    look()

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
    look()
    expect(document.querySelectorAll('.is-pending').length).toBe(0)

    reduced = false
    window.webx.unmount()
    window.webx.mount()
    look()
    expect(document.querySelectorAll('.is-pending').length).toBe(4)
    window.webx.unmount()
    expect(document.querySelectorAll('.webx-reveal, .is-pending').length).toBe(0)
  })

  it('brings in at once what holds the anchor of a link, before the page moves to it', async () => {
    document.body.innerHTML =
      '<a href="#deep">Deep</a><a href="#item">Item</a><a href="/other#far">Other</a>' +
      '<section id="holder" data-webx-reveal><p id="deep">deep</p></section>' +
      '<ul id="list" data-webx-reveal="stagger"><li><span id="item">1</span></li><li>2</li></ul>' +
      '<div id="far-holder" data-webx-reveal><p id="far">far</p></div>'
    at(document.getElementById('holder'), 2000)
    at(document.getElementById('far-holder'), 4000)
    document.querySelectorAll('#list li').forEach((li, i) => at(li, 3000 + i * 50))
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')
    look()
    expect(document.querySelectorAll('.is-pending').length).toBe(4)

    // Who measures the way — the browser, the glide — listens after the reveal, which captures.
    let seen = null
    const follow = (event) => {
      seen = document.getElementById('holder').className
      event.preventDefault()
    }
    document.addEventListener('click', follow)
    const [deep, item, other] = document.querySelectorAll('a')
    deep.click()
    expect(seen).toBe('')
    const holder = document.getElementById('holder')
    // Not brought in a second time when it scrolls into view.
    expect(watched.some((o) => o.els.has(holder))).toBe(false)

    item.click()
    const [first, second] = document.querySelectorAll('#list li')
    expect(first.className).toBe('')
    expect(second.classList.contains('is-pending')).toBe(true)

    // A link to another page leaves this one as it is.
    other.click()
    expect(document.getElementById('far-holder').classList.contains('is-pending')).toBe(true)
    document.removeEventListener('click', follow)
  })

  it('leaves alone what holds the anchor of the address the page opens with', async () => {
    history.replaceState(null, '', '#below')
    document.body.innerHTML = page
    place()
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')
    look()
    history.replaceState(null, '', location.pathname)

    // The browser goes to it after the first word: lowered, it would end up under the header.
    expect(document.getElementById('below').className).toBe('')
    expect(document.querySelectorAll('#list .is-pending').length).toBe(3)
  })

  it('shows everything before printing', async () => {
    document.body.innerHTML = page
    place()
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')
    look()
    expect(document.querySelectorAll('.is-pending').length).toBe(4)
    window.dispatchEvent(new Event('beforeprint'))
    expect(document.querySelectorAll('.is-pending').length).toBe(0)
  })
})

describe('parallax', () => {
  const page = `<section id="frame"><img id="picture" data-webx-parallax src="/a.jpg" alt=""></section>`
  let frames = []

  const run = () => {
    const queued = frames
    frames = []
    queued.forEach((callback) => callback())
  }

  async function load(supports) {
    frames = []
    vi.stubGlobal('CSS', { supports: (query) => supports && query.includes('view-timeline') })
    vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))
    vi.stubGlobal('cancelAnimationFrame', () => {})
    vi.stubGlobal('innerHeight', 800)
    await start(page)
    return document.getElementById('picture')
  }

  it('leaves the motion to the stylesheet where the browser has scroll-driven animations', async () => {
    const picture = await load(true)
    const frame = document.getElementById('frame')

    expect(picture.className).toBe('webx-parallax')
    // The timeline is the frame's: `view()` of the picture would follow the frame that crops it.
    expect(frame.className).toBe('webx-parallax-frame')
    // No observer, no frame asked for: the compositor does it.
    expect(watched.length).toBe(0)
    window.dispatchEvent(new Event('scroll'))
    expect(frames.length).toBe(0)

    window.webx.unmount()
    expect(picture.className).toBe('')
    expect(frame.className).toBe('')
  })

  it('makes a picture placed absolutely taller for its overscan, and leaves one in the flow to be scaled', async () => {
    vi.stubGlobal('CSS', { supports: () => false })
    vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))
    vi.stubGlobal('cancelAnimationFrame', () => {})
    await start(
      '<section><img id="placed" style="position: absolute" data-webx-parallax="strong" src="/a.jpg" alt=""></section><section><img id="flow" data-webx-parallax src="/b.jpg" alt=""></section>',
    )
    const placed = document.getElementById('placed')
    const flow = document.getElementById('flow')

    expect(placed.className).toBe('webx-parallax webx-parallax--grown webx-parallax--script')
    expect(flow.className).toBe('webx-parallax webx-parallax--script')
    // The strength is the stylesheet's to read: the script leaves the value as it was written.
    expect(placed.getAttribute('data-webx-parallax')).toBe('strong')

    window.webx.unmount()
    expect(placed.className).toBe('')
    expect(flow.className).toBe('')
  })

  it('names the timeline on the outermost frame that crops, and falls back where it is taller', async () => {
    vi.stubGlobal('CSS', { supports: () => true })
    vi.stubGlobal('innerHeight', 800)
    // jsdom reads the longhand only; a browser computes it from the shorthand as well.
    const slider =
      '<div id="viewport" style="overflow-y: hidden"><div id="slide" style="overflow-y: hidden"><img id="a" data-webx-parallax src="/a.jpg" alt=""></div><div id="other" style="overflow-y: hidden"><img id="b" data-webx-parallax src="/b.jpg" alt=""></div></div>'
    await start(slider)

    // Two slides, one viewport: it keeps the name until the last picture lets go.
    const viewport = document.getElementById('viewport')
    expect(viewport.className).toBe('webx-parallax-frame')
    expect(document.getElementById('slide').className).toBe('')
    window.webx.unmount(document.getElementById('slide'))
    expect(viewport.className).toBe('webx-parallax-frame')
    window.webx.unmount()
    expect(viewport.className).toBe('')

    // A cropping page wrapper taller than the frame: no timeline follows the frame, the script does.
    document.body.innerHTML =
      '<div id="page" style="overflow-y: hidden"><section id="frame" style="overflow-y: hidden"><img id="c" data-webx-parallax src="/c.jpg" alt=""></section></div>'
    at(document.getElementById('page'), 0, 3000)
    at(document.getElementById('frame'), 400, 500)
    window.webx.mount()
    expect(document.getElementById('c').classList.contains('webx-parallax--script')).toBe(true)
    expect(document.getElementById('page').className).toBe('')
  })

  it('without them, writes where the frame is on its way across the screen, only while it is seen', async () => {
    const picture = await load(false)
    const frame = document.getElementById('frame')

    expect(picture.classList.contains('webx-parallax--script')).toBe(true)
    // The frame is measured, not the picture: its box carries the shift.
    const observer = watched.find((o) => o.els.has(frame))
    expect(observer).toBeTruthy()

    // Off the screen: a scroll asks for nothing.
    observer.callback([{ target: frame, isIntersecting: false }])
    window.dispatchEvent(new Event('scroll'))
    expect(frames.length).toBe(0)

    // Its top at the bottom of the screen: the start of the way.
    at(frame, 800, 400)
    observer.callback([{ target: frame, isIntersecting: true }])
    run()
    expect(picture.style.getPropertyValue('--webx-parallax-progress')).toBe('0.0000')

    // Halfway: the frame's middle at the middle of the screen.
    at(frame, 200, 400)
    window.dispatchEvent(new Event('scroll'))
    window.dispatchEvent(new Event('scroll'))
    // Two scrolls in one frame are one measure.
    expect(frames.length).toBe(1)
    run()
    expect(picture.style.getPropertyValue('--webx-parallax-progress')).toBe('0.5000')

    // Gone over the top: the end, and no further.
    at(frame, -900, 400)
    window.dispatchEvent(new Event('scroll'))
    run()
    expect(picture.style.getPropertyValue('--webx-parallax-progress')).toBe('1.0000')

    window.webx.unmount()
    expect(picture.className).toBe('')
    expect(picture.style.getPropertyValue('--webx-parallax-progress')).toBe('')
    window.dispatchEvent(new Event('scroll'))
    expect(frames.length).toBe(0)
  })

  it('moves nothing with reduced motion', async () => {
    reduced = true
    const picture = await load(false)

    expect(picture.className).toBe('')
    expect(watched.length).toBe(0)
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

describe('overlap', () => {
  // A hero (the pinned section) of 500px with its words, then the section that slides over it.
  const page = `<main id="main"><style>.x{}</style><p id="gone" hidden>notice</p><section id="hero"><img src="/a.jpg" alt=""><div id="words" data-webx-overlap-fade><a id="button" href="#">Go</a></div></section><section id="over" data-webx-overlap><picture class="webx-overlap__edge" id="edge"><img class="webx-overlap__image" src="/fog.png" alt=""></picture><p>Words</p></section></main>`
  let frames = []

  const run = () => {
    const queued = frames
    frames = []
    queued.forEach((callback) => callback())
  }

  const tall = (el, height) =>
    Object.defineProperty(el, 'offsetHeight', { configurable: true, value: height })

  async function load(supports) {
    frames = []
    vi.stubGlobal('CSS', { supports: () => supports })
    vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))
    vi.stubGlobal('cancelAnimationFrame', () => {})
    vi.stubGlobal('innerHeight', 800)
    document.body.innerHTML = page
    tall(document.getElementById('hero'), 500)
    tall(document.getElementById('over'), 600)
    tall(document.getElementById('gone'), 40)
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')
    return {
      hero: document.getElementById('hero'),
      over: document.getElementById('over'),
      words: document.getElementById('words'),
      main: document.getElementById('main'),
      edge: document.getElementById('edge'),
    }
  }

  it('pins the section above, past what is not shown, and leaves the motion to the stylesheet', async () => {
    const { hero, over, words, main, edge } = await load(true)

    expect(over.className).toBe('webx-overlap')
    expect(edge.classList.contains('webx-overlap__edge--page')).toBe(true)
    expect(main.classList.contains('webx-overlap-scope')).toBe(true)
    // The <style> and the hidden notice are skipped: the hero is the section above.
    expect(hero.className).toBe('webx-overlap-pinned webx-overlap-pinned--held')
    expect(words.className).toBe('webx-overlap-fade')
    // A screen of 800, a hero of 500, a section of 600: it stops at the top, and is covered over 500.
    expect(hero.style.getPropertyValue('--webx-overlap-top')).toBe('0px')
    expect(hero.style.getPropertyValue('--webx-overlap-start')).toBe('300px')
    expect(hero.style.getPropertyValue('--webx-overlap-hold')).toBe('500px')
    expect(hero.style.getPropertyValue('--webx-overlap-leave')).toBe('500px')
    // One timeline, the section's, lifted to the parent the hero is in.
    const name = hero.style.getPropertyValue('--webx-overlap-timeline')
    expect(over.style.getPropertyValue('view-timeline')).toBe(`${name} block`)
    expect(main.style.getPropertyValue('timeline-scope')).toBe(name)
    expect(watched.length).toBe(0)

    window.webx.unmount()
    expect(hero.className).toBe('')
    expect(words.className).toBe('')
    expect(over.className).toBe('')
    expect(main.className).toBe('')
    expect(main.style.getPropertyValue('timeline-scope')).toBe('')
  })

  it('stops a section taller than the screen at its bottom, and covers no more than the section that comes', async () => {
    const { hero } = await load(true)
    tall(hero, 1200)
    tall(document.getElementById('over'), 300)
    resized.forEach((observer) => observer.callback([]))

    expect(hero.style.getPropertyValue('--webx-overlap-top')).toBe('-400px')
    expect(hero.style.getPropertyValue('--webx-overlap-start')).toBe('0px')
    expect(hero.style.getPropertyValue('--webx-overlap-hold')).toBe('300px')
    expect(hero.style.getPropertyValue('--webx-overlap-leave')).toBe('800px')
  })

  it('without scroll-driven animations, writes how far the words faded and the section went up', async () => {
    const { hero, over, words } = await load(false)
    expect(hero.classList.contains('webx-overlap-pinned--script')).toBe(true)
    expect(words.classList.contains('webx-overlap-fade--script')).toBe(true)
    const observer = watched.find((o) => o.els.has(over))
    observer.callback([{ target: over, isIntersecting: true }])

    // Its top at 500 (300 up from the bottom): the pin starts.
    at(over, 500, 600)
    run()
    expect(hero.style.getPropertyValue('--webx-overlap-progress')).toBe('0.0000')
    expect(hero.style.getPropertyValue('--webx-overlap-lift')).toBe('0.0px')

    // Halfway over the hero.
    at(over, 250, 600)
    window.dispatchEvent(new Event('scroll'))
    window.dispatchEvent(new Event('scroll'))
    expect(frames.length).toBe(1)
    run()
    expect(hero.style.getPropertyValue('--webx-overlap-progress')).toBe('0.5000')

    // Past the hold: the hero goes up with the page, as far as it is tall.
    at(over, -100, 600)
    window.dispatchEvent(new Event('scroll'))
    run()
    expect(hero.style.getPropertyValue('--webx-overlap-progress')).toBe('1.0000')
    expect(hero.style.getPropertyValue('--webx-overlap-lift')).toBe('100.0px')
    at(over, -900, 600)
    window.dispatchEvent(new Event('scroll'))
    run()
    expect(hero.style.getPropertyValue('--webx-overlap-lift')).toBe('500.0px')
  })

  it('brings the words back when focus comes into the section above', async () => {
    const { over } = await load(true)
    const scrolled = []
    vi.stubGlobal('scrollTo', (options) => scrolled.push(options.top))
    vi.stubGlobal('scrollY', 1000)

    // Before the pin: nothing to bring back.
    at(over, 600, 600)
    document.getElementById('button').dispatchEvent(new FocusEvent('focusin', { bubbles: true }))
    expect(scrolled).toEqual([])

    // 200 into the hold: the page goes back 200.
    at(over, 300, 600)
    document.getElementById('button').dispatchEvent(new FocusEvent('focusin', { bubbles: true }))
    expect(scrolled).toEqual([800])
  })

  it('under reduced motion pins nothing, and still lays the section over the one above', async () => {
    reduced = true
    const { hero, over, edge } = await load(true)

    expect(hero.className).toBe('')
    expect(over.className).toBe('webx-overlap')
    expect(edge.classList.contains('webx-overlap__edge--page')).toBe(true)
  })
})
