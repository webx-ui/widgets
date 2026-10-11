import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// jsdom has no layout and no pointer events: each test says where things stand, and a pointer
// is a plain event with the fields the scripts read.
let frames = []
let resized = []

beforeEach(() => {
  frames = []
  resized = []
  vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))
  vi.stubGlobal('cancelAnimationFrame', () => {})
  vi.stubGlobal(
    'ResizeObserver',
    class {
      constructor(callback) {
        this.callback = callback
        resized.push(this)
      }
      observe() {}
      disconnect() {
        this.callback = () => {}
      }
    },
  )
})

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  document.documentElement.style.removeProperty('--webx-header-height')
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

async function start(html, file, before = () => {}) {
  document.body.innerHTML = html
  before()
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import(`./${file}.js`)
}

const box = (el, { top = 0, left = 0, width = 100, height = 100 } = {}) => {
  el.getBoundingClientRect = () => ({
    top,
    left,
    width,
    height,
    bottom: top + height,
    right: left + width,
  })
}

const pointer = (el, type, { x = 0, id = 1, kind = 'mouse', button = 0 } = {}) => {
  const event = new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, button })
  Object.defineProperty(event, 'pointerId', { value: id })
  Object.defineProperty(event, 'pointerType', { value: kind })
  el.dispatchEvent(event)
  return event
}

const key = (el, name, shiftKey = false) => {
  const event = new KeyboardEvent('keydown', {
    key: name,
    shiftKey,
    bubbles: true,
    cancelable: true,
  })
  el.dispatchEvent(event)
  return event
}

const runFrames = () => frames.splice(0).forEach((callback) => callback(0))

describe('compare', () => {
  const page = `
    <figure lang="en" style="--webx-compare-ratio: 3 / 2; --webx-compare-position: 50%" class="webx-compare" data-webx-compare>
      <div class="webx-compare__frame" dir="ltr">
        <div class="webx-compare__side webx-compare__side--before"><img class="webx-compare__picture" src="/a.webp" alt=""></div>
        <div class="webx-compare__side webx-compare__side--after"><img class="webx-compare__picture" src="/b.webp" alt=""></div>
        <span class="webx-compare__handle" aria-hidden="true"></span>
        <input class="webx-compare__range" type="range" min="0" max="100" step="any" value="50" aria-label="Divider" aria-valuetext="50%">
      </div>
    </figure>`

  const parts = () => ({
    root: document.querySelector('.webx-compare'),
    frame: document.querySelector('.webx-compare__frame'),
    range: document.querySelector('.webx-compare__range'),
    handle: document.querySelector('.webx-compare__handle'),
  })
  const position = () => parts().root.style.getPropertyValue('--webx-compare-position')

  it('steps by 5% with the arrows, by 1% with Shift, to the ends with Home and End', async () => {
    await start(page, 'compare')
    const { range } = parts()

    expect(key(range, 'ArrowRight').defaultPrevented).toBe(true)
    expect(position()).toBe('55%')
    expect(range.value).toBe('55')
    expect(range.getAttribute('aria-valuetext')).toBe('55%')
    key(range, 'ArrowLeft', true)
    expect(position()).toBe('54%')
    key(range, 'PageDown')
    expect(position()).toBe('29%')
    key(range, 'Home')
    key(range, 'ArrowLeft')
    expect(position()).toBe('0%')
    key(range, 'End')
    expect(position()).toBe('100%')
    expect(key(range, 'Tab').defaultPrevented).toBe(false)

    // What a screen reader's own slider gestures change arrives as input.
    range.value = '12.5'
    range.dispatchEvent(new Event('input'))
    expect(position()).toBe('12.5%')
    expect(range.getAttribute('aria-valuetext')).toBe('13%')
  })

  it('moves where a mouse presses and while it drags, and focuses the slider', async () => {
    await start(page, 'compare')
    const { root, frame, range } = parts()
    box(frame, { left: 100, width: 400 })

    expect(pointer(frame, 'pointerdown', { x: 200 }).defaultPrevented).toBe(true)
    expect(position()).toBe('25%')
    expect(document.activeElement).toBe(range)
    expect(root.classList.contains('is-dragging')).toBe(true)
    pointer(frame, 'pointermove', { x: 700 })
    expect(position()).toBe('100%')
    pointer(frame, 'pointerup', { x: 700 })
    expect(root.classList.contains('is-dragging')).toBe(false)
    pointer(frame, 'pointermove', { x: 300 })
    expect(position()).toBe('100%')

    // Another button than the main one does nothing.
    pointer(frame, 'pointerdown', { x: 300, button: 2 })
    expect(position()).toBe('100%')
  })

  it('leaves a finger that scrolls the page alone and follows one that moves sideways', async () => {
    await start(page, 'compare')
    const { frame } = parts()
    box(frame, { left: 0, width: 200 })

    pointer(frame, 'pointerdown', { x: 50, kind: 'touch' })
    expect(position()).toBe('50%')
    pointer(frame, 'pointermove', { x: 53, kind: 'touch' })
    expect(position()).toBe('50%')
    // The page scrolled: the browser takes the pointer.
    pointer(frame, 'pointercancel', { x: 53, kind: 'touch' })
    expect(position()).toBe('50%')

    pointer(frame, 'pointerdown', { x: 50, kind: 'touch', id: 2 })
    pointer(frame, 'pointermove', { x: 80, kind: 'touch', id: 2 })
    expect(position()).toBe('40%')
    pointer(frame, 'pointerup', { x: 80, kind: 'touch', id: 2 })

    // A tap puts the divider there.
    pointer(frame, 'pointerdown', { x: 150, kind: 'touch', id: 3 })
    pointer(frame, 'pointerup', { x: 150, kind: 'touch', id: 3 })
    expect(position()).toBe('75%')
  })

  it('lets everything go when unmounted', async () => {
    await start(page, 'compare')
    const { root, frame, range } = parts()
    box(frame, { left: 0, width: 200 })

    window.webx.unmount(root)
    pointer(frame, 'pointerdown', { x: 50 })
    key(range, 'End')
    expect(position()).toBe('50%')
  })
})

describe('table of contents', () => {
  // A page longer than the window: jsdom's is 0 high, which is always "the bottom".
  beforeEach(() => {
    Object.defineProperty(document.documentElement, 'scrollHeight', {
      configurable: true,
      value: 5000,
    })
  })
  afterEach(() => {
    delete document.documentElement.scrollHeight
  })

  const page = (attributes = '') => `
    <div class="webx-toc" data-webx-toc ${attributes}>
      <div class="webx-toc__layout webx-toc__layout--end">
        <nav class="webx-toc__nav webx-toc__nav--beside" id="t" aria-labelledby="t-title">
          <p class="webx-toc__title" id="t-title">On this page</p>
          <button type="button" class="webx-toc__toggle" aria-expanded="false" aria-controls="t-panel"><span class="webx-toc__toggle-title">On this page</span><span class="webx-toc__current"></span></button>
          <div class="webx-toc__panel" id="t-panel">
            <ol class="webx-toc__list">
              <li class="webx-toc__item"><a class="webx-toc__link" href="#one">One</a>
                <ol class="webx-toc__list webx-toc__list--sub"><li class="webx-toc__item"><a class="webx-toc__link" href="#one-one">One point one</a></li></ol>
              </li>
              <li class="webx-toc__item"><a class="webx-toc__link" href="#%D0%B4%D0%B2%D0%B0">Two</a></li>
            </ol>
          </div>
        </nav>
        <div class="webx-toc__content"><h2 id="one">One</h2><h3 id="one-one">One point one</h3><h2 id="два">Two</h2></div>
      </div>
    </div>
    <button type="button" id="elsewhere">Elsewhere</button>`

  const parts = () => ({
    nav: document.querySelector('.webx-toc__nav'),
    toggle: document.querySelector('.webx-toc__toggle'),
    panel: document.querySelector('.webx-toc__panel'),
    title: document.querySelector('.webx-toc__title'),
    current: document.querySelector('.webx-toc__current'),
    links: [...document.querySelectorAll('.webx-toc__link')],
    headings: ['one', 'one-one', 'два'].map((id) => document.getElementById(id)),
  })

  /** The headings where the page has scrolled them to, then a scroll and its frame. */
  const scrolled = (tops) => {
    parts().headings.forEach((heading, i) => box(heading, { top: tops[i], height: 30 }))
    window.dispatchEvent(new Event('scroll'))
    runFrames()
  }

  const marked = () =>
    parts()
      .links.filter((link) => link.classList.contains('is-current'))
      .map((link) => [link.textContent, link.getAttribute('aria-current')])

  it('marks the section being read as the page scrolls', async () => {
    await start(page(), 'toc', () => {
      document.documentElement.style.setProperty('--webx-header-height', '60px')
      parts().headings.forEach((heading, i) => box(heading, { top: 400 + i * 600, height: 30 }))
    })
    // A quarter of 768 under a header of 60: the line is at 252.
    expect(marked()).toEqual([])

    scrolled([250, 850, 1450])
    expect(marked()).toEqual([['One', 'true']])
    expect(parts().current.textContent).toBe('One')
    scrolled([-400, 200, 800])
    expect(marked()).toEqual([['One point one', 'true']])
    scrolled([-1000, -400, 255])
    expect(marked()).toEqual([['One point one', 'true']])
    scrolled([-1000, -400, 252])
    expect(marked()).toEqual([['Two', 'true']])
  })

  it('marks the last heading in view at the very bottom of the page', async () => {
    await start(page(), 'toc')
    Object.defineProperty(document.documentElement, 'scrollHeight', {
      configurable: true,
      value: 768,
    })

    scrolled([100, 300, 600])
    expect(marked()).toEqual([['Two', 'true']])
  })

  it('folds into a bar that opens the list, closed by a link, Esc and a click elsewhere', async () => {
    await start(page(), 'toc', () => box(document.querySelector('.webx-toc__nav'), { height: 52 }))
    const { nav, toggle, panel, title, links, headings } = parts()

    for (const el of [nav, toggle, panel, title])
      expect(el.classList.contains('is-ready')).toBe(true)
    // Folded, the bar sticks under the header: a heading reached by a link stops below it.
    expect(headings.map((heading) => heading.style.scrollMarginTop)).toEqual([
      '60px',
      '60px',
      '60px',
    ])

    toggle.click()
    expect(panel.classList.contains('is-open')).toBe(true)
    expect(toggle.getAttribute('aria-expanded')).toBe('true')
    links[1].click()
    expect(panel.classList.contains('is-open')).toBe(false)

    toggle.click()
    links[0].focus()
    key(links[0], 'Escape')
    expect(panel.classList.contains('is-open')).toBe(false)
    expect(document.activeElement).toBe(toggle)

    toggle.click()
    pointer(nav, 'pointerdown')
    expect(panel.classList.contains('is-open')).toBe(true)
    pointer(document.getElementById('elsewhere'), 'pointerdown')
    expect(panel.classList.contains('is-open')).toBe(false)
    expect(toggle.getAttribute('aria-expanded')).toBe('false')
  })

  it('stays a list where the stylesheet keeps it beside the text, and with fold="never"', async () => {
    await start(page(), 'toc', () => {
      document.querySelector('.webx-toc__toggle').style.display = 'none'
    })
    const { panel, links, headings } = parts()

    links[0].click()
    expect(panel.classList.contains('is-open')).toBe(false)
    expect(headings[0].style.scrollMarginTop).toBe('')

    await start(page('data-fold="never"'), 'toc')
    const never = parts()
    expect(never.toggle.classList.contains('is-ready')).toBe(false)
    never.toggle.click()
    expect(never.panel.classList.contains('is-open')).toBe(false)
  })

  it('folds and unfolds as its container changes, and lets everything go when unmounted', async () => {
    await start(page(), 'toc', () => box(document.querySelector('.webx-toc__nav'), { height: 40 }))
    const { toggle, panel, headings, nav } = parts()

    toggle.click()
    toggle.style.display = 'none'
    resized.at(-1).callback()
    expect(panel.classList.contains('is-open')).toBe(false)
    expect(headings[0].style.scrollMarginTop).toBe('')

    scrolled([100, 700, 1300])
    window.webx.unmount(document.querySelector('.webx-toc'))
    expect(nav.classList.contains('is-ready')).toBe(false)
    expect(marked()).toEqual([])
    expect(parts().current.textContent).toBe('')
  })
})
