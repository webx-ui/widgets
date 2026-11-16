import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import PhotoSwipe from 'photoswipe'

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

  it('pages through hidden links of its group and gives the focus back to the opener', async () => {
    const { show } = await start(link(1) + link(2) + '<div hidden>' + link(3) + '</div>')
    const links = document.querySelectorAll('a')

    const pswp = await show(links[1])
    expect(pswp.getNumItems()).toBe(3)
    pswp.next()
    expect(pswp.currIndex).toBe(2)

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

  it('reads a frame as a width over a height, and anything else as none', async () => {
    const { ratio } = await start('')

    expect(ratio('4:3')).toBeCloseTo(4 / 3)
    expect(ratio('16 / 9')).toBeCloseTo(16 / 9)
    expect(ratio('1.5')).toBe(1.5)
    for (const none of [undefined, '', 'auto', '0:1', '4:0', 'wide']) expect(ratio(none)).toBeNull()
  })

  it('fits a picture whole into a frame of the asked shape, and leaves the rest as they are', async () => {
    const { show } = await start(
      link(1, 'g', 'data-webx-lightbox-ratio="4:3"') +
        // A tall picture: the frame grows sideways, not down.
        '<a href="/tall.jpg" data-webx-lightbox="g" data-width="600" data-height="900" data-webx-lightbox-ratio="1:1"></a>' +
        link(3),
    )
    const pswp = await show(document.querySelector('a'))
    const [wide, tall, plain] = pswp.options.dataSource

    // 1200×800 is 3:2, wider than 4:3: as wide, taller.
    expect(wide).toMatchObject({ width: 1200, height: 900 })
    expect(tall).toMatchObject({ width: 900, height: 900 })
    expect(plain).toMatchObject({ width: 1200, height: 800, ratio: null })
    const shown = () =>
      pswp.currSlide.container.querySelector('img.pswp__img:not(.pswp__img--placeholder)')
    await vi.waitFor(() => expect(shown()).not.toBeNull())
    expect(shown().classList.contains('webx-lightbox__framed')).toBe(true)
    // The thumbnail standing in until the picture loads is framed too (its shape — jsdom has no
    // aspect-ratio — is measured in a browser).
    const placeholder = pswp.currSlide.content.placeholder?.element
    expect(placeholder.classList.contains('webx-lightbox__framed')).toBe(true)
    // The picture paged to without a frame has no frame's background.
    pswp.prev()
    await vi.waitFor(() => expect(shown()).not.toBeNull())
    expect(shown().classList.contains('webx-lightbox__framed')).toBe(false)
  })

  it('gives the stand-in thumbnail the frame before the opening zoom, not after it', async () => {
    const { show } = await start(link(1, 'g', 'data-webx-lightbox-ratio="4:3"'))
    // In a browser the picture is appended only after the opening zoom, and the zoom animates the
    // thumbnail: framed only then, it would land in the thumbnail's shape and jump to the frame's.
    // jsdom appends at once, so the thumbnail is looked at the moment the picture comes in.
    const seen = []
    const dispatch = PhotoSwipe.prototype.dispatch
    const spy = vi
      .spyOn(PhotoSwipe.prototype, 'dispatch')
      .mockImplementation(function (name, details) {
        if (name === 'contentAppend') {
          const placeholder = details.content.placeholder?.element
          seen.push(placeholder?.classList.contains('webx-lightbox__framed'))
        }
        return dispatch.call(this, name, details)
      })
    try {
      await show(document.querySelector('a'))
    } finally {
      spy.mockRestore()
    }
    expect(seen.length).toBeGreaterThan(0)
    // The shape too — jsdom has no aspect-ratio, it is measured in a browser.
    expect(seen.every(Boolean)).toBe(true)
  })

  it('zooms up a thumbnail of the shape of the picture only, not a cropped one', async () => {
    const { thumbnail } = await start('')
    const img = (w, h, src = '/t.jpg') => ({
      currentSrc: src,
      naturalWidth: w,
      naturalHeight: h,
      getAttribute: () => src,
    })
    // The same shape, give or take a pixel of rounding: zoomed up while the picture loads.
    expect(thumbnail(img(360, 240), 1200, 800)).toBe('/t.jpg')
    expect(thumbnail(img(359, 240), 1200, 800)).toBe('/t.jpg')
    // A square crop of a 3:2 picture would grow square and jump to 3:2: a plain box instead.
    expect(thumbnail(img(180, 180), 1200, 800)).toBeUndefined()
    // Nothing to compare with: taken as it is.
    expect(thumbnail(img(0, 0), 1200, 800)).toBe('/t.jpg')
    expect(thumbnail(img(180, 180), 0, 0)).toBe('/t.jpg')
    expect(thumbnail(null, 1200, 800)).toBeUndefined()
  })

  it('frames a picture measured on opening once its sizes are known', async () => {
    const Image = globalThis.Image
    globalThis.Image = class {
      set src(value) {
        this.naturalWidth = 1000
        this.naturalHeight = 1000
        setTimeout(() => this.onload?.())
      }
    }

    try {
      const { show } = await start(
        '<a href="/bare.jpg" data-webx-lightbox="" data-webx-lightbox-ratio="16:9"></a>',
      )
      const pswp = await show(document.querySelector('a'))

      expect(pswp.options.dataSource[0]).toMatchObject({ width: 1778, height: 1000 })
    } finally {
      globalThis.Image = Image
    }
  })

  it('keeps the backdrop opaque unless the token or the link says otherwise', async () => {
    const { show } = await start(link(1) + link(2, 'h', 'data-webx-lightbox-opacity="0.85"'))
    const [plain, own] = document.querySelectorAll('a')

    expect((await show(plain)).options.bgOpacity).toBe(1)
    expect((await show(own)).options.bgOpacity).toBe(0.85)

    // What a theme sets on a section reaches its links (jsdom inherits nothing, so on the link).
    plain.style.setProperty('--webx-lightbox-backdrop-opacity', '0.6')
    expect((await show(plain)).options.bgOpacity).toBe(0.6)
  })

  it('fits the picture into its window: up to min(100vw − 200px, 1600px) × 84vh wide, 100vw − 24px × 75vh on a phone', async () => {
    const { padding, layout } = await start('')
    const frames = layout()

    expect(padding({ x: 1440, y: 900 }, frames)).toEqual({
      top: 72,
      bottom: 72,
      left: 100,
      right: 100,
    })
    // Wider than 1800: the picture stops at 1600.
    expect(padding({ x: 2400, y: 1000 }, frames)).toEqual({
      top: 80,
      bottom: 80,
      left: 400,
      right: 400,
    })
    expect(padding({ x: 390, y: 844 }, frames)).toEqual({
      top: 105.5,
      bottom: 105.5,
      left: 12,
      right: 12,
    })

    // The site's own margins, from the package's config; the rest stays the default.
    const own = layout({ wide: { side: 40, height: 1 }, narrow: 500 })
    expect(padding({ x: 1440, y: 900 }, own)).toEqual({ top: 0, bottom: 0, left: 40, right: 40 })
    expect(padding({ x: 600, y: 900 }, own).left).toBe(40)
  })

  it('stands the arrows as a pair under the picture on a narrow window, and leaves them be on a wide one', async () => {
    const { show } = await start(link(1) + link(2))
    const links = document.querySelectorAll('a')

    // jsdom: a window 600 wide, 768 high — a phone. 1200×800 fitted into 576 × 576 is 576 × 384,
    // in the middle of the 75% band: it ends at 96 + 288 + 192.
    const pswp = await show(links[0])
    const arrows = viewer().querySelectorAll('.pswp__button--arrow')
    expect(arrows).toHaveLength(2)
    arrows.forEach((arrow) => {
      expect(arrow.classList.contains('webx-lightbox__arrow')).toBe(true)
      expect(arrow.classList.contains('is-below')).toBe(true)
    })
    expect(viewer().querySelector('.webx-lightbox__arrow--prev')).not.toBeNull()
    expect(viewer().querySelector('.webx-lightbox__arrow--next')).not.toBeNull()
    expect(viewer().style.getPropertyValue('--webx-lightbox-arrows-top')).toBe('576px')
    pswp.close()
    await vi.waitFor(() => expect(viewer()).toBeNull())

    const width = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'clientWidth')
    Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
      configurable: true,
      get: () => 1440,
    })
    try {
      const wide = await show(links[0])
      viewer()
        .querySelectorAll('.pswp__button--arrow')
        .forEach((arrow) => expect(arrow.classList.contains('is-below')).toBe(false))
      expect(viewer().style.getPropertyValue('--webx-lightbox-arrows-top')).toBe('')
      wide.close()
      await vi.waitFor(() => expect(viewer()).toBeNull())
    } finally {
      Object.defineProperty(HTMLElement.prototype, 'clientWidth', width)
    }
  })

  it('leaves the page where it stood, whichever picture it closed on', async () => {
    const { show } = await start(link(1) + link(2) + link(3) + link(4))
    const links = document.querySelectorAll('a')
    const focus = vi.spyOn(HTMLElement.prototype, 'focus')
    const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
    let y = 500
    Object.defineProperty(window, 'scrollY', { configurable: true, get: () => y })

    try {
      const pswp = await show(links[0])
      pswp.next()
      pswp.next()
      pswp.next()
      // Something moved the page while it was covered.
      y = 1800
      pswp.close()
      await vi.waitFor(() => expect(viewer()).toBeNull())

      expect(document.activeElement).toBe(links[3])
      expect(focus).toHaveBeenLastCalledWith({ preventScroll: true })
      expect(scrollTo).toHaveBeenCalledWith({ left: 0, top: 500, behavior: 'instant' })

      // Where nothing moved, nothing is scrolled.
      scrollTo.mockClear()
      y = 500
      ;(await show(links[1])).close()
      await vi.waitFor(() => expect(viewer()).toBeNull())
      expect(scrollTo).not.toHaveBeenCalled()
    } finally {
      delete window.scrollY
      focus.mockRestore()
      scrollTo.mockRestore()
    }
  })

  it("shows the caption of a picture's figure under it, and no caption element for one without", async () => {
    const figure = (n, words) =>
      `<figure>${link(n)}${words ? `<figcaption> ${words}\n </figcaption>` : ''}</figure>`
    const { show, caption } = await start(
      figure(1, 'The terrace at sunset') + figure(2) + link(3, 'g', 'data-caption="Own words"'),
    )
    const links = document.querySelectorAll('a')
    expect(caption(links[0])).toBe('The terrace at sunset')
    expect(caption(links[1])).toBe('')
    expect(caption(links[2])).toBe('Own words')

    const pswp = await show(links[0])
    const root = viewer()
    const note = root.querySelector('.webx-lightbox__caption')
    expect(note.textContent).toBe('The terrace at sunset')
    expect(root.getAttribute('aria-describedby')).toBe(note.id)
    // Reduced motion: no opening zoom, the caption shows at once. Under the picture's left edge:
    // 576 × 384 in the middle of a window 600 wide, ending at 576 (see the arrows below).
    expect(note.classList.contains('is-shown')).toBe(true)
    expect(root.style.getPropertyValue('--webx-lightbox-caption-top')).toBe('576px')
    expect(root.style.getPropertyValue('--webx-lightbox-caption-left')).toBe('12px')

    pswp.next()
    expect(root.querySelector('.webx-lightbox__caption')).toBeNull()
    expect(root.hasAttribute('aria-describedby')).toBe(false)

    pswp.next()
    expect(root.querySelector('.webx-lightbox__caption').textContent).toBe('Own words')
    expect(root.getAttribute('aria-describedby')).toBe(note.id)

    pswp.close()
    // Hidden as the picture zooms back into its thumbnail.
    expect(
      root.querySelector('.webx-lightbox__caption')?.classList.contains('is-shown') ?? false,
    ).toBe(false)
    await vi.waitFor(() => expect(viewer()).toBeNull())
  })

  it('puts the arrows of a phone under the caption, not over it', async () => {
    const { show } = await start(
      `<figure>${link(1)}<figcaption>Words</figcaption></figure>` + link(2),
    )
    const height = vi
      .spyOn(HTMLElement.prototype, 'offsetHeight', 'get')
      .mockImplementation(function () {
        return this.classList.contains('webx-lightbox__caption') ? 30 : 0
      })
    try {
      const pswp = await show(document.querySelector('a'))
      expect(viewer().style.getPropertyValue('--webx-lightbox-arrows-top')).toBe('606px')
      pswp.next()
      expect(viewer().style.getPropertyValue('--webx-lightbox-arrows-top')).toBe('576px')
      pswp.close()
      await vi.waitFor(() => expect(viewer()).toBeNull())
    } finally {
      height.mockRestore()
    }
  })
})
