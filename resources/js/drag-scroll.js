/*
 * A strip that scrolls sideways, dragged with the mouse (spec §14): a gallery's ribbon, any
 * element with `data-webx-drag-scroll` and `overflow-x: auto`. Its own file, claimed by the
 * attribute wherever it was written: a page without such a strip never loads it.
 *
 * A finger, a touchpad and the wheel scroll the strip natively, as without this script; only a
 * mouse is taken (`pointerType: mouse`). A press moves nothing until the mouse has gone THRESHOLD
 * pixels: a click on a frame's link stays a click. Past it the strip follows the mouse with its
 * snap off — mandatory snap would pull every pixel back to a frame — the click that ends the drag
 * is swallowed, and on release the frame nearest the strip's start comes to it, the way the snap
 * would have put it: smoothly, or at once with `prefers-reduced-motion`.
 */

import '../css/drag-scroll.css'

const webx = (window.webx ??= {})

/** How far the mouse goes, in pixels, before a press is a drag and no longer a click. */
export const THRESHOLD = 5

/** How long a smooth settle may take before the snap comes back anyway, in milliseconds. */
const SETTLE = 600

const px = (value) => Number.parseFloat(value) || 0

/**
 * How far the strip has to scroll for its nearest frame to stand at its start — the start of its
 * scroll padding, where `scroll-snap-align: start` puts a frame. Right to left the start is the
 * right edge; `scrollLeft` is physical either way, so one subtraction serves both.
 */
export function nearest(strip) {
  const box = strip.getBoundingClientRect()
  const style = getComputedStyle(strip)
  const rtl = style.direction === 'rtl'
  const pad = px(
    style.scrollPaddingInlineStart || (rtl ? style.scrollPaddingRight : style.scrollPaddingLeft),
  )
  const start = rtl ? box.right - pad : box.left + pad
  let best = null
  for (const frame of strip.children) {
    const own = frame.getBoundingClientRect()
    const offset = (rtl ? own.right : own.left) - start
    if (best === null || Math.abs(offset) < Math.abs(best)) best = offset
  }
  return best ?? 0
}

export function dragScroll(strip) {
  const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
  let drag = null
  let swallow = false
  let settling = null

  const settled = () => {
    clearTimeout(settling)
    settling = null
    strip.removeEventListener('scrollend', settled)
    strip.classList.remove('is-dragging')
  }

  const settle = () => {
    const by = nearest(strip)
    if (Math.abs(by) < 1 || reduced()) {
      if (Math.abs(by) >= 1) strip.scrollTo({ left: strip.scrollLeft + by, behavior: 'instant' })
      settled()
      return
    }
    // The snap stays off until the glide ends: switched back on mid-way it would jump.
    strip.addEventListener('scrollend', settled)
    settling = setTimeout(settled, SETTLE)
    strip.scrollTo({ left: strip.scrollLeft + by, behavior: 'smooth' })
  }

  const down = (event) => {
    swallow = false
    if (event.pointerType !== 'mouse' || event.button !== 0) return
    if (strip.scrollWidth <= strip.clientWidth) return
    if (settling) settled()
    drag = { id: event.pointerId, x: event.clientX, left: strip.scrollLeft, moved: false }
  }

  const move = (event) => {
    if (!drag || event.pointerId !== drag.id) return
    const dx = event.clientX - drag.x
    if (!drag.moved) {
      if (Math.abs(dx) < THRESHOLD) return
      drag.moved = true
      strip.classList.add('is-dragging')
      strip.setPointerCapture?.(event.pointerId)
    }
    event.preventDefault()
    strip.scrollLeft = drag.left - dx
  }

  const up = (event) => {
    if (!drag || event.pointerId !== drag.id) return
    const { moved } = drag
    drag = null
    strip.releasePointerCapture?.(event.pointerId)
    if (!moved) return
    // The click the browser sends after the release is the drag's end, not a choice of a frame.
    swallow = event.type === 'pointerup'
    settle()
  }

  const click = (event) => {
    if (!swallow) return
    swallow = false
    event.preventDefault()
    event.stopPropagation()
  }

  // A link and a picture are dragged out of the page by the browser on their own: not here.
  const dragstart = (event) => event.preventDefault()

  const listeners = [
    ['pointerdown', down],
    ['pointermove', move],
    ['pointerup', up],
    ['pointercancel', up],
    ['click', click, true],
    ['dragstart', dragstart],
  ]
  for (const [type, listener, options] of listeners) strip.addEventListener(type, listener, options)
  strip.classList.add('webx-drag-scroll', 'is-ready')

  return () => {
    for (const [type, listener, options] of listeners)
      strip.removeEventListener(type, listener, options)
    if (settling) settled()
    strip.classList.remove('webx-drag-scroll', 'is-ready', 'is-dragging')
  }
}

webx.widget?.('drag-scroll', '[data-webx-drag-scroll]', dragScroll)
