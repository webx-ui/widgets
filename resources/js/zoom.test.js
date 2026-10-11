import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// jsdom has no layout, no pointer events and no Touch: each test says where things stand, a
// pointer is a plain event with the fields the script reads, a touch a plain object.
let fetched = []

beforeEach(() => {
  fetched = []
  vi.stubGlobal(
    'Image',
    class {
      set src(url) {
        fetched.push(url)
      }
    },
  )
})

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  vi.useRealTimers()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

async function start(html) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./zoom.js')
}

const box = (el, { top = 0, left = 0, width = 200, height = 100 } = {}) => {
  el.getBoundingClientRect = () => ({
    top,
    left,
    width,
    height,
    bottom: top + height,
    right: left + width,
  })
}

const pointer = (el, type, { x = 0, y = 0, kind = 'mouse' } = {}) => {
  const event = new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y })
  Object.defineProperty(event, 'pointerType', { value: kind })
  el.dispatchEvent(event)
  return event
}

const touch = (el, type, fingers, changed = fingers) => {
  const event = new Event(type, { bubbles: true, cancelable: true })
  const at = (list) => list.map(([x, y]) => ({ clientX: x, clientY: y }))
  Object.defineProperty(event, 'touches', { value: type === 'touchend' ? [] : at(fingers) })
  Object.defineProperty(event, 'changedTouches', { value: at(changed) })
  el.dispatchEvent(event)
  return event
}

const COMPONENT = `
  <div class="column">
    <a class="webx-zoom webx-zoom-link" href="/large.jpg" data-webx-zoom="/large.jpg" data-zoom-mode="inner" data-zoom-scale="2.5" data-webx-lightbox="">
      <img class="webx-zoom__picture" src="/small.jpg" alt="A chair">
      <span class="webx-zoom__badge" aria-hidden="true"></span>
    </a>
  </div>`

async function mounted(html = COMPONENT, frame = {}) {
  await start(html)
  const root = document.querySelector('[data-webx-zoom]')
  box(root, frame)
  box(root.querySelector('img'), frame)
  return root
}

const large = (root) => root.querySelector('.webx-zoom__view .webx-zoom__large')

describe('a mouse', () => {
  it('magnifies inside the frame and keeps the point under the cursor', async () => {
    const root = await mounted(undefined, { left: 100, top: 50 })
    expect(root.querySelector('.webx-zoom__view')).toBeNull()

    pointer(root, 'pointermove', { x: 150, y: 75 })
    const view = root.querySelector('.webx-zoom__view')
    expect(view.classList.contains('is-active')).toBe(true)
    expect(root.classList.contains('is-active')).toBe(true)
    expect(view.getAttribute('aria-hidden')).toBe('true')
    expect(view.style.width).toBe('200px')
    const img = large(root)
    expect(img.style.width).toBe('500px')
    expect(img.style.height).toBe('250px')
    // 50px into the frame: the picture's point 125px into the large one (50 × 2.5) is under it.
    expect(img.style.transform).toBe('translate(-75px, -37.5px)')
    expect(root.querySelector('.webx-zoom__badge').classList.contains('is-hidden')).toBe(true)

    pointer(root, 'pointerleave')
    expect(view.classList.contains('is-active')).toBe(false)
    expect(root.classList.contains('is-active')).toBe(false)
    expect(root.querySelector('.webx-zoom__badge').classList.contains('is-hidden')).toBe(false)
  })

  it('fetches the original on the first hover only, the shown picture standing in', async () => {
    const root = await mounted()
    expect(fetched).toEqual([])
    pointer(root, 'pointermove', { x: 10, y: 10 })
    expect(fetched).toEqual(['/large.jpg'])
    expect(large(root).getAttribute('src')).toBe(new URL('/small.jpg', location.href).href)
    pointer(root, 'pointerleave')
    pointer(root, 'pointermove', { x: 20, y: 10 })
    expect(fetched).toEqual(['/large.jpg'])
  })

  it('steps aside on a press — a click or a drag — until it moves again after the release', async () => {
    const root = await mounted()
    pointer(root, 'pointermove', { x: 10, y: 10 })
    pointer(root, 'pointerdown', { x: 10, y: 10 })
    expect(root.classList.contains('is-active')).toBe(false)
    pointer(root, 'pointermove', { x: 30, y: 10 })
    expect(root.classList.contains('is-active')).toBe(false)
    window.dispatchEvent(new Event('pointerup'))
    pointer(root, 'pointermove', { x: 40, y: 10 })
    expect(root.classList.contains('is-active')).toBe(true)
  })

  it('a touch pointer is not a hover', async () => {
    const root = await mounted()
    pointer(root, 'pointermove', { x: 10, y: 10, kind: 'touch' })
    expect(root.classList.contains('is-active')).toBe(false)
    expect(fetched).toEqual([])
  })

  it('beside: a lens over the picture, the panel right of the frame, when its container has room', async () => {
    const root = await mounted(
      COMPONENT.replace('data-zoom-mode="inner"', 'data-zoom-mode="side"'),
      {
        left: 0,
        top: 40,
      },
    )
    const column = document.querySelector('.column')
    box(column, { left: 0, width: 500 })
    const real = window.getComputedStyle
    vi.spyOn(window, 'getComputedStyle').mockImplementation((el, pseudo) => {
      const style = real(el, pseudo)
      if (el === column) return { ...style, containerType: 'inline-size', containerName: '' }
      return style
    })

    pointer(root, 'pointermove', { x: 100, y: 90 })
    const lens = root.querySelector('.webx-zoom__lens')
    const panel = document.body.querySelector(':scope > .webx-zoom__panel')
    expect(root.classList.contains('is-side')).toBe(true)
    expect(lens.classList.contains('is-active')).toBe(true)
    expect(panel.classList.contains('is-active')).toBe(true)
    expect(root.querySelector('.webx-zoom__view').classList.contains('is-active')).toBe(false)
    // The panel is the frame's size; the lens is the panel made smaller by the scale, centred.
    expect(panel.style.left).toBe('200px')
    expect(panel.style.top).toBe('40px')
    expect(panel.style.width).toBe('200px')
    expect(lens.style.width).toBe('80px')
    expect(lens.style.height).toBe('40px')
    expect(lens.style.left).toBe('60px')
    expect(lens.style.top).toBe('30px')
    expect(panel.firstChild.style.transform).toBe('translate(-150px, -75px)')

    // At the edge, the lens stays inside the picture.
    pointer(root, 'pointermove', { x: 195, y: 45 })
    expect(lens.style.left).toBe('120px')
    expect(lens.style.top).toBe('0px')

    // A narrow column: no room beside, so inside the frame.
    box(column, { left: 0, width: 260 })
    pointer(root, 'pointermove', { x: 100, y: 90 })
    expect(root.classList.contains('is-side')).toBe(false)
    expect(panel.classList.contains('is-active')).toBe(false)
    expect(root.querySelector('.webx-zoom__view').classList.contains('is-active')).toBe(true)
  })

  it('a slider is no room beside: its container is skipped', async () => {
    const root = await mounted(
      `<div class="page"><div class="webx-slider">${COMPONENT.replace('data-zoom-mode="inner"', 'data-zoom-mode="side"')}</div></div>`,
    )
    const page = document.querySelector('.page')
    const slider = document.querySelector('.webx-slider')
    box(page, { width: 600 })
    box(slider, { width: 200 })
    const real = window.getComputedStyle
    vi.spyOn(window, 'getComputedStyle').mockImplementation((el, pseudo) => {
      const style = real(el, pseudo)
      if (el === slider)
        return { ...style, containerType: 'inline-size', containerName: 'webx-slider' }
      if (el === page) return { ...style, containerType: 'inline-size', containerName: '' }
      return style
    })
    pointer(root, 'pointermove', { x: 100, y: 50 })
    expect(root.classList.contains('is-side')).toBe(true)
  })
})

describe('fingers', () => {
  it('two pinch and pan, the slider holds still, pinched back it is as it was', async () => {
    const root = await mounted(`<div class="webx-slider__viewport">${COMPONENT}</div>`)
    const swiper = { allowTouchMove: true, activeIndex: 2, destroyed: false, slideTo: vi.fn() }
    document.querySelector('.webx-slider__viewport').swiper = swiper

    const start = touch(root, 'touchstart', [
      [50, 50],
      [150, 50],
    ])
    expect(start.defaultPrevented).toBe(true)
    expect(swiper.allowTouchMove).toBe(false)
    expect(fetched).toEqual(['/large.jpg'])

    const move = touch(root, 'touchmove', [
      [0, 50],
      [200, 50],
    ])
    expect(move.defaultPrevented).toBe(true)
    expect(root.classList.contains('is-zoomed')).toBe(true)
    // Twice as far apart: 2×, the point between the fingers (100, 50) still between them.
    expect(large(root).style.width).toBe('400px')
    expect(large(root).style.transform).toBe('translate(-100px, -50px)')

    touch(root, 'touchend', [], [[0, 50]])
    expect(root.classList.contains('is-zoomed')).toBe(true)

    // One finger now pans, as far as the picture goes.
    touch(root, 'touchstart', [[100, 50]])
    const pan = touch(root, 'touchmove', [[400, 50]])
    expect(pan.defaultPrevented).toBe(true)
    expect(large(root).style.transform).toBe('translate(0px, -50px)')
    touch(root, 'touchend', [], [[400, 50]])

    // Pinched back to its own size.
    touch(root, 'touchstart', [
      [0, 50],
      [200, 50],
    ])
    touch(root, 'touchmove', [
      [90, 50],
      [110, 50],
    ])
    touch(root, 'touchend', [], [[90, 50]])
    expect(root.classList.contains('is-zoomed')).toBe(false)
    expect(root.querySelector('.webx-zoom__view').classList.contains('is-active')).toBe(false)
    expect(swiper.allowTouchMove).toBe(true)
    expect(swiper.slideTo).toHaveBeenCalledWith(2, 0)
  })

  it('two fingers lifted one after the other leave a picture pinched back as it was', async () => {
    const root = await mounted()
    const two = (type, spread) =>
      touch(root, type, [
        [100 - spread, 50],
        [100 + spread, 50],
      ])
    two('touchstart', 20)
    two('touchmove', 60)
    expect(root.classList.contains('is-zoomed')).toBe(true)
    two('touchmove', 10)
    // The first finger up: one is still down.
    const first = new Event('touchend', { bubbles: true, cancelable: true })
    Object.defineProperty(first, 'touches', { value: [{ clientX: 110, clientY: 50 }] })
    Object.defineProperty(first, 'changedTouches', { value: [{ clientX: 90, clientY: 50 }] })
    root.dispatchEvent(first)
    expect(root.classList.contains('is-zoomed')).toBe(true)
    touch(root, 'touchend', [], [[110, 50]])
    expect(root.classList.contains('is-zoomed')).toBe(false)
    expect(root.querySelector('.webx-zoom__view').classList.contains('is-active')).toBe(false)
  })

  it('one finger on a picture at its own size is the page’s and the slider’s', async () => {
    const root = await mounted()
    touch(root, 'touchstart', [[50, 50]])
    const move = touch(root, 'touchmove', [[50, 10]])
    expect(move.defaultPrevented).toBe(false)
    expect(root.classList.contains('is-zoomed')).toBe(false)
    expect(fetched).toEqual([])
  })

  it('a double tap is 2× around the point and back', async () => {
    const root = await mounted()
    const tap = (x) => {
      touch(root, 'touchstart', [[x, 40]])
      return touch(root, 'touchend', [], [[x, 40]])
    }
    tap(60)
    const second = tap(62)
    expect(second.defaultPrevented).toBe(true)
    expect(root.classList.contains('is-zoomed')).toBe(true)
    expect(large(root).classList.contains('is-easing')).toBe(true)
    expect(large(root).style.width).toBe('400px')
    expect(large(root).style.transform).toBe('translate(-62px, -40px)')

    tap(62)
    tap(62)
    expect(root.classList.contains('is-zoomed')).toBe(false)
  })

  it('a tap still opens the lightbox, once no second tap has come; a double tap does not', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    let clock = 0
    vi.spyOn(performance, 'now').mockImplementation(() => clock)
    const wait = (ms) => {
      clock += ms
      vi.advanceTimersByTime(ms)
    }
    const root = await mounted()
    const opened = vi.fn()
    root.addEventListener('click', (event) => {
      if (event.defaultPrevented) return
      opened()
      event.preventDefault()
    })

    touch(root, 'touchstart', [[60, 40]])
    touch(root, 'touchend', [], [[60, 40]])
    root.querySelector('img').click()
    expect(opened).not.toHaveBeenCalled()
    wait(300)
    expect(opened).toHaveBeenCalledTimes(1)

    // Two taps: the first click waits, the second tap takes it back.
    wait(1000)

    touch(root, 'touchstart', [[60, 40]])
    touch(root, 'touchend', [], [[60, 40]])
    root.querySelector('img').click()
    wait(100)
    touch(root, 'touchstart', [[60, 40]])
    touch(root, 'touchend', [], [[60, 40]])
    wait(600)
    expect(opened).toHaveBeenCalledTimes(1)
    expect(root.classList.contains('is-zoomed')).toBe(true)

    // A tap on a magnified picture opens nothing.
    wait(1000)
    touch(root, 'touchstart', [[60, 40]])
    touch(root, 'touchend', [], [[60, 40]])
    root.querySelector('img').click()
    wait(600)
    expect(opened).toHaveBeenCalledTimes(1)
  })

  it('a click of a mouse or a key is the lightbox’s at once', async () => {
    const root = await mounted()
    const opened = vi.fn()
    root.addEventListener('click', (event) => {
      if (event.defaultPrevented) return
      opened()
      event.preventDefault()
    })
    vi.spyOn(performance, 'now').mockReturnValue(1e6)
    root.click()
    expect(opened).toHaveBeenCalledTimes(1)
  })
})

describe('the attribute', () => {
  it('on a gallery’s link: the link’s address is the original, the class comes and goes', async () => {
    const root = await mounted(
      '<a href="/photo.jpg" data-webx-lightbox="g" data-webx-zoom><img src="/photo.jpg" alt=""></a>',
    )
    expect(root.classList.contains('webx-zoom')).toBe(true)
    pointer(root, 'pointermove', { x: 10, y: 10 })
    expect(fetched).toEqual(['http://localhost:3000/photo.jpg'])
    window.webx.unmount()
    expect(root.classList.contains('webx-zoom')).toBe(false)
    expect(root.querySelector('.webx-zoom__view')).toBeNull()
  })

  it('unmounting takes the panel off the page and lets the slider go', async () => {
    const root = await mounted(
      `<div class="webx-slider__viewport">${COMPONENT.replace('data-zoom-mode="inner"', 'data-zoom-mode="side"')}</div>`,
    )
    const swiper = { allowTouchMove: true, activeIndex: 0, destroyed: false, slideTo: vi.fn() }
    document.querySelector('.webx-slider__viewport').swiper = swiper
    pointer(root, 'pointermove', { x: 10, y: 10 })
    touch(root, 'touchstart', [
      [50, 50],
      [150, 50],
    ])
    expect(document.querySelector('.webx-zoom__panel')).not.toBeNull()
    window.webx.unmount()
    expect(document.querySelector('.webx-zoom__panel')).toBeNull()
    expect(swiper.allowTouchMove).toBe(true)
    expect(root.classList.contains('webx-zoom')).toBe(true)
    pointer(root, 'pointermove', { x: 10, y: 10 })
    expect(root.classList.contains('is-active')).toBe(false)
  })
})
