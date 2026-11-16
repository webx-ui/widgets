import { afterEach, describe, expect, it, vi } from 'vitest'

// jsdom lays nothing out and scrolls nothing: the strip says how wide it is and where its frames
// stand for the scroll it has; a pointer is a plain event with the fields the script reads.

let reduced = false

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  reduced = false
})

const FRAME = 300
const GAP = 20

async function start(frames = 10) {
  document.body.innerHTML = `<ul class="b-gallery__ribbon" data-webx-drag-scroll>${Array.from(
    { length: frames },
    (_, i) => `<li><a href="/post/${i}">Post ${i}</a></li>`,
  ).join('')}</ul>`
  const strip = document.querySelector('ul')
  let left = 0
  const most = frames * (FRAME + GAP) - GAP - 1000
  Object.defineProperties(strip, {
    clientWidth: { configurable: true, get: () => 1000 },
    scrollWidth: { configurable: true, get: () => most + 1000 },
    scrollLeft: {
      configurable: true,
      get: () => left,
      set: (value) => {
        left = Math.max(0, Math.min(most, value))
      },
    },
  })
  strip.getBoundingClientRect = () => ({ left: 0, right: 1000, top: 0, bottom: 400 })
  Array.from(strip.children).forEach((frame, i) => {
    frame.getBoundingClientRect = () => {
      const at = i * (FRAME + GAP) - left
      return { left: at, right: at + FRAME, top: 0, bottom: 400 }
    }
  })
  strip.scrollTo = vi.fn(({ left: to }) => (strip.scrollLeft = to))
  window.matchMedia = (query) => ({
    matches: query.includes('reduce') && reduced,
    addEventListener() {},
    removeEventListener() {},
  })

  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  const module = await import('./drag-scroll.js')
  return { strip, ...module }
}

const pointer = (el, type, x, kind = 'mouse') => {
  const event = new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, button: 0 })
  Object.defineProperty(event, 'pointerType', { value: kind })
  Object.defineProperty(event, 'pointerId', { value: 1 })
  el.dispatchEvent(event)
  return event
}

/** A press, a move by `by` pixels in steps and a release, the way a mouse drags. */
const drag = (el, from, by, kind = 'mouse') => {
  pointer(el, 'pointerdown', from, kind)
  for (let step = 1; step <= 4; step++) pointer(el, 'pointermove', from + (by * step) / 4, kind)
  pointer(el, 'pointerup', from + by, kind)
}

/** Whether a click on the element would be followed. */
const followed = (el) =>
  el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }))

describe('drag-scroll', () => {
  it('drags the strip with the mouse and brings the nearest frame to its start on release', async () => {
    reduced = true
    const { strip } = await start()
    expect(strip.classList.contains('is-ready')).toBe(true)

    // 40% of the strip's width to the left: the strip scrolls 400 px with the mouse.
    pointer(strip, 'pointerdown', 800)
    pointer(strip, 'pointermove', 600)
    pointer(strip, 'pointermove', 400)
    expect(strip.scrollLeft).toBe(400)
    expect(strip.classList.contains('is-dragging')).toBe(true)

    // Frames stand every 320 px: at 400 the second one (320) is the nearest to the start.
    pointer(strip, 'pointerup', 400)
    expect(strip.scrollTo).toHaveBeenLastCalledWith({ left: 320, behavior: 'instant' })
    expect(strip.scrollLeft).toBe(320)
    expect(strip.scrollLeft % (FRAME + GAP)).toBe(0)
    expect(strip.classList.contains('is-dragging')).toBe(false)
  })

  it('glides to the frame with the snap off until the glide ends, at once with reduced motion', async () => {
    const { strip } = await start()

    drag(strip, 800, -500)
    // 500 → the frame at 640 is nearer than the one at 320.
    expect(strip.scrollTo).toHaveBeenLastCalledWith({ left: 640, behavior: 'smooth' })
    expect(strip.classList.contains('is-dragging')).toBe(true)

    strip.dispatchEvent(new Event('scrollend'))
    expect(strip.classList.contains('is-dragging')).toBe(false)
  })

  it('swallows the click that ends a drag and follows a click that moved nothing', async () => {
    reduced = true
    const { strip } = await start()
    const link = strip.querySelector('a[href="/post/2"]')

    drag(link, 800, -400)
    expect(strip.scrollLeft).toBe(320)
    expect(followed(link)).toBe(false)

    // The next click is a click again.
    pointer(link, 'pointerdown', 500)
    pointer(link, 'pointermove', 502)
    pointer(link, 'pointerup', 502)
    expect(strip.scrollLeft).toBe(320)
    expect(followed(link)).toBe(true)
  })

  it('leaves a finger and a pen to the browser, and a strip with nothing to scroll alone', async () => {
    const { strip } = await start()

    drag(strip, 800, -400, 'touch')
    drag(strip, 800, -400, 'pen')
    expect(strip.scrollLeft).toBe(0)
    expect(strip.classList.contains('is-dragging')).toBe(false)
    expect(strip.scrollTo).not.toHaveBeenCalled()
    expect(followed(strip.querySelector('a'))).toBe(true)

    // Three frames fit: there is nowhere to drag them.
    const short = await start(3)
    Object.defineProperty(short.strip, 'scrollWidth', { configurable: true, get: () => 1000 })
    drag(short.strip, 800, -400)
    expect(short.strip.scrollLeft).toBe(0)
    expect(short.strip.classList.contains('is-dragging')).toBe(false)
  })

  it('keeps the browser from dragging a link or a picture out of the strip', async () => {
    const { strip } = await start()
    const event = new Event('dragstart', { bubbles: true, cancelable: true })
    strip.querySelector('a').dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
  })

  it('lets the strip go when unmounted', async () => {
    const { strip } = await start()
    window.webx.unmount()
    expect(strip.classList.contains('is-ready')).toBe(false)
    drag(strip, 800, -400)
    expect(strip.scrollLeft).toBe(0)
  })

  it('finds the nearest frame from the right edge of a strip right to left', async () => {
    const { strip, nearest } = await start()
    strip.style.direction = 'rtl'
    // Frames from the right edge: 0 → 1000, the next ends 320 px further left.
    Array.from(strip.children).forEach((frame, i) => {
      frame.getBoundingClientRect = () => {
        const right = 1000 - i * (FRAME + GAP) + 100
        return { left: right - FRAME, right, top: 0, bottom: 400 }
      }
    })
    expect(nearest(strip)).toBe(100)
  })
})
