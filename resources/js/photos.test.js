import { afterEach, describe, expect, it, vi } from 'vitest'

// jsdom runs no CSS animation and lays nothing out: the end of a bar's fill is an event sent by
// hand, the frame says where it stands, a pointer is a plain event with the fields the script reads.
let reduced = false

afterEach(() => {
  reduced = false
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  vi.unstubAllGlobals()
})

const words = { pause: 'Pause', play: 'Play' }

const markup = ({ count = 3, autoplay = 5000, linked = false } = {}) => {
  const photos = Array.from(
    { length: count },
    (_, at) =>
      `<img class="webx-photos__photo${at === 0 ? ' is-active' : ''}" src="/p${at + 1}.jpg" alt="Photo ${at + 1}"${at ? ' data-webx-later' : ''}>`,
  ).join('')
  const bars = Array.from(
    { length: count },
    (_, at) =>
      `<button type="button" class="webx-photos__bar" aria-label="Photo ${at + 1} of ${count}"><span class="webx-photos__fill"></span></button>`,
  ).join('')
  const widget = `<div class="webx-photos" role="group" data-webx-photos='${JSON.stringify({ autoplay, words })}'>
    <div class="webx-photos__frame">${photos}</div>
    <div class="webx-photos__bars" hidden>${bars}</div>
    ${autoplay ? '<button type="button" class="webx-photos__pause" aria-label="Pause" hidden></button>' : ''}
  </div>`
  return linked
    ? `<ul class="slider"><li class="card" data-webx-photos-surface>${widget}<a class="link" href="#flat">Flat</a></li></ul>`
    : widget
}

async function start(html) {
  window.matchMedia = (query) => ({
    matches: query.includes('reduce') && reduced,
    addEventListener() {},
    removeEventListener() {},
  })
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./photos.js')
  const root = document.querySelector('.webx-photos')
  root.querySelector('.webx-photos__frame').getBoundingClientRect = () => ({
    top: 0,
    left: 0,
    width: 300,
    height: 200,
    bottom: 200,
    right: 300,
  })
  return root
}

const shown = (root) =>
  Array.from(root.querySelectorAll('.webx-photos__photo')).findIndex((photo) =>
    photo.classList.contains('is-active'),
  )

const fills = (root) => Array.from(root.querySelectorAll('.webx-photos__fill'))

const end = (root) => {
  const fill = root.querySelector('.webx-photos__fill.is-active')
  fill.dispatchEvent(new Event('animationend', { bubbles: true }))
}

const pointer = (el, type, { x = 0, y = 0, kind = 'touch' } = {}) => {
  const event = new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y })
  Object.defineProperty(event, 'pointerType', { value: kind })
  Object.defineProperty(event, 'pointerId', { value: 1 })
  el.dispatchEvent(event)
  return event
}

describe('photos', () => {
  it('shows the bars and the pause button, the first photo current', async () => {
    const root = await start(markup())

    expect(root.classList.contains('is-ready')).toBe(true)
    expect(root.querySelector('.webx-photos__bars').hidden).toBe(false)
    expect(root.querySelector('.webx-photos__pause').hidden).toBe(false)
    expect(shown(root)).toBe(0)
    expect(fills(root)[0].classList.contains('is-active')).toBe(true)
    expect(fills(root)[0].classList.contains('is-still')).toBe(false)
    expect(root.querySelectorAll('.webx-photos__photo')[1].getAttribute('aria-hidden')).toBe('true')
    expect(root.querySelectorAll('.webx-photos__bar')[0].getAttribute('aria-current')).toBe('true')
  })

  it('moves on when the bar of the shown photo is full, and round to the first', async () => {
    const root = await start(markup())

    end(root)
    expect(shown(root)).toBe(1)
    expect(fills(root)[0].classList.contains('is-done')).toBe(true)
    expect(fills(root)[1].classList.contains('is-active')).toBe(true)

    end(root)
    end(root)
    expect(shown(root)).toBe(0)
    expect(fills(root).some((fill) => fill.classList.contains('is-done'))).toBe(false)
  })

  it('stands under the pointer, with the focus inside and by the button', async () => {
    const root = await start(markup())

    root.dispatchEvent(new Event('pointerenter'))
    expect(root.classList.contains('is-paused')).toBe(true)
    root.dispatchEvent(new Event('pointerleave'))
    expect(root.classList.contains('is-paused')).toBe(false)

    const bar = root.querySelector('.webx-photos__bar')
    bar.dispatchEvent(new FocusEvent('focusin', { bubbles: true }))
    expect(root.classList.contains('is-paused')).toBe(true)
    bar.dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: document.body }))
    expect(root.classList.contains('is-paused')).toBe(false)

    const pause = root.querySelector('.webx-photos__pause')
    pause.click()
    expect(root.classList.contains('is-paused')).toBe(true)
    expect(pause.getAttribute('aria-label')).toBe('Play')
    // Stopped by the button, it stays stopped when the pointer leaves.
    root.dispatchEvent(new Event('pointerleave'))
    expect(root.classList.contains('is-paused')).toBe(true)
    pause.click()
    expect(root.classList.contains('is-paused')).toBe(false)
    expect(pause.getAttribute('aria-label')).toBe('Pause')
  })

  it('does not start with reduced motion; the button starts it', async () => {
    reduced = true
    const root = await start(markup())

    expect(root.classList.contains('is-paused')).toBe(true)
    expect(fills(root)[0].classList.contains('is-still')).toBe(true)
    const pause = root.querySelector('.webx-photos__pause')
    expect(pause.getAttribute('aria-label')).toBe('Play')

    pause.click()
    expect(root.classList.contains('is-paused')).toBe(false)
    expect(fills(root)[0].classList.contains('is-still')).toBe(false)
  })

  it('without autoplay changes only when asked', async () => {
    const root = await start(markup({ autoplay: 0 }))

    expect(root.querySelector('.webx-photos__pause')).toBeNull()
    expect(fills(root)[0].classList.contains('is-still')).toBe(true)
    end(root)
    expect(shown(root)).toBe(0)
  })

  it('a bar shows its photo, the arrows step between the bars', async () => {
    const root = await start(markup())
    const bars = root.querySelectorAll('.webx-photos__bar')

    bars[2].click()
    expect(shown(root)).toBe(2)
    expect(fills(root)[1].classList.contains('is-done')).toBe(true)

    bars[2].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))
    expect(shown(root)).toBe(0)
    expect(document.activeElement).toBe(bars[0])
    bars[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }))
    expect(shown(root)).toBe(2)
  })

  it('a swipe over the frame changes the photo; down the page it does not', async () => {
    const root = await start(markup())
    const frame = root.querySelector('.webx-photos__frame')

    pointer(frame, 'pointerdown', { x: 200, y: 100 })
    pointer(frame, 'pointerup', { x: 120, y: 110 })
    expect(shown(root)).toBe(1)

    pointer(frame, 'pointerdown', { x: 120, y: 100 })
    pointer(frame, 'pointerup', { x: 200, y: 95 })
    expect(shown(root)).toBe(0)

    pointer(frame, 'pointerdown', { x: 150, y: 20 })
    pointer(frame, 'pointerup', { x: 110, y: 180 })
    expect(shown(root)).toBe(0)
  })

  it('on a linked card: the swipe is read off the link over the frame, its click swallowed, a tap goes through', async () => {
    const root = await start(markup({ linked: true }))
    const card = document.querySelector('.card')
    const link = document.querySelector('.link')
    const outer = vi.fn()
    document.querySelector('.slider').addEventListener('pointerdown', outer)
    expect(card.style.touchAction).toBe('pan-y pinch-zoom')

    pointer(link, 'pointerdown', { x: 200, y: 100 })
    pointer(link, 'pointerup', { x: 100, y: 100 })
    const swiped = new MouseEvent('click', { bubbles: true, cancelable: true })
    link.dispatchEvent(swiped)
    expect(shown(root)).toBe(1)
    expect(swiped.defaultPrevented).toBe(true)
    // The slider of cards around it does not move with the photos.
    expect(outer).not.toHaveBeenCalled()

    pointer(link, 'pointerdown', { x: 200, y: 100 })
    pointer(link, 'pointerup', { x: 201, y: 100 })
    const tap = new MouseEvent('click', { bubbles: true, cancelable: true })
    link.dispatchEvent(tap)
    expect(tap.defaultPrevented).toBe(false)

    // Below the frame — the card's words — the press is the slider's.
    pointer(link, 'pointerdown', { x: 100, y: 260 })
    expect(outer).toHaveBeenCalledTimes(1)
  })

  it('leaves the markup as it found it', async () => {
    const root = await start(markup({ linked: true }))
    end(root)

    window.webx.unmount()
    expect(root.classList.contains('is-ready')).toBe(false)
    expect(root.querySelector('.webx-photos__bars').hidden).toBe(true)
    expect(root.querySelector('.webx-photos__pause').hidden).toBe(true)
    expect(shown(root)).toBe(0)
    expect(root.querySelector('[aria-hidden]')).toBeNull()
    expect(document.querySelector('.card').style.touchAction).toBe('')
    expect(fills(root).some((fill) => fill.className !== 'webx-photos__fill')).toBe(false)
  })
})
