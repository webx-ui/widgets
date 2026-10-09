import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'

// jsdom lays nothing out; PhotoSwipe and Swiper read sizes and watch them.
globalThis.ResizeObserver = class {
  observe() {}
  disconnect() {}
}

beforeAll(() => {
  Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
    configurable: true,
    get: () => 600,
  })
  Object.defineProperty(HTMLElement.prototype, 'clientHeight', {
    configurable: true,
    get: () => 400,
  })
  window.matchMedia = (query) => ({
    matches: query.includes('reduce'),
    addEventListener() {},
    removeEventListener() {},
  })
})

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
})

// What the server prints before </body> on a page with a lightbox.
const settings = `<script type="application/json" id="webx-lightbox">${JSON.stringify({
  words: {
    dialog: 'Picture viewer',
    carousel: 'carousel',
    slide: 'slide',
    close: 'Close',
    zoom: 'Zoom',
    prev: 'Previous picture',
    next: 'Next picture',
    error: 'The picture cannot be loaded',
  },
  icons: {
    prev: '<svg class="webx-icon webx-lightbox__icon" data-icon="prev"></svg>',
    next: '<svg class="webx-icon webx-lightbox__icon" data-icon="next"></svg>',
    close: '<svg class="webx-icon webx-lightbox__icon" data-icon="close"></svg>',
    zoom: '<svg class="webx-icon webx-lightbox__icon" data-icon="zoom"></svg>',
  },
})}</script>`

const link = (n, group = 'g', extra = '') =>
  `<a href="/p${n}.jpg" data-webx-lightbox="${group}" data-width="1200" data-height="800" ${extra}><img src="/t${n}.jpg" alt="Picture ${n}"></a>`

async function start(html) {
  document.body.innerHTML = html + settings
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  return import('./lightbox.js')
}

const viewer = () => document.querySelector('.pswp')

/** Whether the lightbox took the click; the browser never follows it (jsdom cannot). */
const click = (el, init = {}) => {
  let taken = false
  const record = (event) => {
    taken = event.defaultPrevented
    event.preventDefault()
  }
  document.addEventListener('click', record, { once: true })
  el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...init }))
  return taken
}

describe('lightbox', () => {
  it('pages through the links of one group in the order of the page; an empty group is alone', async () => {
    const { members } = await start(
      link(1) + '<p>' + link(2, 'other') + link(3) + '</p>' + link(4, '') + link(5, ''),
    )
    const links = document.querySelectorAll('a')

    expect(members(links[2])).toEqual([links[0], links[2]])
    expect(members(links[1])).toEqual([links[1]])
    expect(members(links[3])).toEqual([links[3]])
  })

  it("follows a looping slider's own order and leaves its copies out", async () => {
    // Swiper's loop has moved the last slide first; a running strip added a copy.
    const { members } = await start(`
      <div class="track">
        <div data-swiper-slide-index="2">${link(3)}</div>
        <div data-swiper-slide-index="0">${link(1)}</div>
        <div data-swiper-slide-index="1">${link(2)}</div>
        <div data-swiper-slide-index="0" data-webx-copy>${link(1)}</div>
      </div>${link(9)}`)
    const order = members(document.querySelector('a[href="/p2.jpg"]')).map((a) =>
      a.getAttribute('href'),
    )

    expect(order).toEqual(['/p1.jpg', '/p2.jpg', '/p3.jpg', '/p9.jpg'])
  })

  it('opens over the page, named in the words of the page, with the icons of the chain', async () => {
    await start(link(1) + link(2))
    const first = document.querySelector('a')

    expect(first.getAttribute('aria-haspopup')).toBe('dialog')
    expect(first.classList.contains('is-ready')).toBe(true)

    expect(click(document.querySelectorAll('a')[1])).toBe(true)
    await vi.waitFor(() => expect(viewer()).not.toBeNull())

    const root = viewer()
    expect(root.classList.contains('webx-lightbox')).toBe(true)
    expect(root.getAttribute('role')).toBe('dialog')
    expect(root.getAttribute('aria-modal')).toBe('true')
    expect(root.getAttribute('aria-label')).toBe('Picture viewer')
    expect(root.querySelector('.pswp__button--close').getAttribute('title')).toBe('Close')
    expect(root.querySelector('.pswp__button--close [data-icon="close"]')).not.toBeNull()
    expect(root.querySelector('.pswp__button--arrow--next').getAttribute('title')).toBe(
      'Next picture',
    )
    expect(root.querySelector('.pswp__item').getAttribute('aria-roledescription')).toBe('slide')
  })

  it('leaves a new tab, a modifier and a swipe to the browser', async () => {
    await start(link(1))
    const a = document.querySelector('a')

    expect(click(a, { ctrlKey: true })).toBe(false)
    expect(click(a, { button: 1 })).toBe(false)

    // Swiper prevents the click that ends a drag.
    a.addEventListener('click', (event) => event.preventDefault(), { capture: true, once: true })
    click(a)
    await new Promise((resolve) => setTimeout(resolve, 20))
    expect(viewer()).toBeNull()
  })

  it('closes on Esc and gives the focus to the link of the picture last shown', async () => {
    const { show } = await start(link(1) + link(2) + link(3))
    const links = document.querySelectorAll('a')

    const pswp = await show(links[0])
    pswp.next()
    expect(pswp.currIndex).toBe(1)

    document.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true }),
    )
    await vi.waitFor(() => expect(viewer()).toBeNull())
    expect(document.activeElement).toBe(links[1])
  })

  it('lets PhotoSwipe go when its links are unmounted, and starts again when mounted', async () => {
    const { show } = await start('<div id="block">' + link(1) + link(2) + '</div>')
    const block = document.getElementById('block')

    const pswp = await show(block.querySelector('a'))
    expect(viewer()).not.toBeNull()

    window.webx.unmount(block)
    expect(pswp.isDestroying).toBe(true)
    await vi.waitFor(() => expect(viewer()).toBeNull())
    expect(block.querySelector('a').hasAttribute('aria-haspopup')).toBe(false)
    expect(click(block.querySelector('a'))).toBe(false)

    window.webx.mount(block)
    expect(click(block.querySelector('a'))).toBe(true)
    await vi.waitFor(() => expect(viewer()).not.toBeNull())
  })

  it('measures a picture that came without its sizes before it opens', async () => {
    const loads = []
    const Image = globalThis.Image
    globalThis.Image = class {
      set src(value) {
        loads.push(value)
        this.naturalWidth = 900
        this.naturalHeight = 600
        setTimeout(() => this.onload?.())
      }
    }

    try {
      const { show } = await start(
        '<a href="/bare.jpg" data-webx-lightbox=""><img src="/t.jpg" alt=""></a>',
      )
      const pswp = await show(document.querySelector('a'))

      expect(loads).toEqual(['http://localhost:3000/bare.jpg'])
      expect(pswp.options.dataSource[0]).toMatchObject({ width: 900, height: 600 })
    } finally {
      globalThis.Image = Image
    }
  })
})
