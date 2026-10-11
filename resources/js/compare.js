/*
 * Before and after (spec §14). Its own file, claimed by `<x-webx-compare>`.
 *
 * The server printed the frame with the divider where it starts and a range input that is the
 * divider for a keyboard and a screen reader. Here the input and the frame are tied together:
 * whatever moves one moves the other, and `--webx-compare-position` is what the stylesheet cuts
 * the after picture at.
 *
 * A mouse moves the divider where it presses and while it drags. A finger has to move sideways
 * first: a page is scrolled by touching pictures too, and a tap that jumped the divider would
 * move it under a thumb that only meant to scroll. The arrows step by 5%, with Shift by 1%.
 */

import '../css/compare.css'

const webx = (window.webx ??= {})

const clamp = (value) => Math.min(100, Math.max(0, value))

export function compare(root) {
  const frame = root.querySelector('.webx-compare__frame')
  const range = root.querySelector('.webx-compare__range')
  const handle = root.querySelector('.webx-compare__handle')
  if (!frame || !range) return

  const lang = root.closest('[lang]')?.getAttribute('lang') || undefined
  let percent
  try {
    percent = new Intl.NumberFormat(lang, { style: 'percent', maximumFractionDigits: 0 })
  } catch {
    percent = new Intl.NumberFormat(undefined, { style: 'percent', maximumFractionDigits: 0 })
  }

  const set = (value) => {
    const position = Math.round(clamp(value) * 10) / 10
    root.style.setProperty('--webx-compare-position', `${position}%`)
    range.value = String(position)
    range.setAttribute('aria-valuetext', percent.format(Math.round(position) / 100))
  }

  const at = (event) => {
    const box = frame.getBoundingClientRect()
    return box.width > 0 ? ((event.clientX - box.left) / box.width) * 100 : Number(range.value)
  }

  // The arrows go on from where the pointer left the divider; the frame is no tab stop of its own.
  const grab = (event) => {
    range.focus({ preventScroll: true })
    try {
      // The drag goes on outside the frame; a pointer already gone cannot be captured.
      frame.setPointerCapture?.(event.pointerId)
    } catch {
      // Followed for as long as it stays over the frame.
    }
    root.classList.add('is-dragging')
  }

  let pointer = null
  let startX = 0
  let moved = false

  const down = (event) => {
    if (pointer !== null || (event.pointerType === 'mouse' && event.button !== 0)) return
    pointer = event.pointerId
    startX = event.clientX
    moved = false
    if (event.pointerType === 'mouse') {
      event.preventDefault()
      grab(event)
      set(at(event))
    }
  }

  const move = (event) => {
    if (event.pointerId !== pointer) return
    if (!moved && event.pointerType !== 'mouse') {
      // Sideways by a few pixels: the finger means the divider, and the frame takes it.
      if (Math.abs(event.clientX - startX) < 6) return
      grab(event)
    }
    moved = true
    set(at(event))
  }

  const up = (event) => {
    if (event.pointerId !== pointer) return
    // A tap without a drag puts the divider there too; a scroll ends in pointercancel instead.
    if (event.type === 'pointerup' && !moved && event.pointerType !== 'mouse') {
      range.focus({ preventScroll: true })
      set(at(event))
    }
    pointer = null
    root.classList.remove('is-dragging')
  }

  const key = (event) => {
    const step = event.shiftKey ? 1 : 5
    const value = Number(range.value)
    const next = {
      ArrowLeft: value - step,
      ArrowDown: value - step,
      ArrowRight: value + step,
      ArrowUp: value + step,
      PageDown: value - 25,
      PageUp: value + 25,
      Home: 0,
      End: 100,
    }[event.key]
    if (next === undefined) return
    event.preventDefault()
    set(next)
  }

  const input = () => set(Number(range.value))
  // The ring on the knob for a keyboard only: a click focuses the input too.
  const focus = () => {
    let keyboard = true
    try {
      keyboard = range.matches(':focus-visible')
    } catch {
      // A browser without the selector: the ring on any focus, as before it existed.
    }
    handle?.classList.toggle('is-focused', keyboard)
  }
  const blur = () => handle?.classList.remove('is-focused')

  frame.addEventListener('pointerdown', down)
  frame.addEventListener('pointermove', move)
  frame.addEventListener('pointerup', up)
  frame.addEventListener('pointercancel', up)
  range.addEventListener('keydown', key)
  range.addEventListener('input', input)
  range.addEventListener('focus', focus)
  range.addEventListener('blur', blur)

  return () => {
    frame.removeEventListener('pointerdown', down)
    frame.removeEventListener('pointermove', move)
    frame.removeEventListener('pointerup', up)
    frame.removeEventListener('pointercancel', up)
    range.removeEventListener('keydown', key)
    range.removeEventListener('input', input)
    range.removeEventListener('focus', focus)
    range.removeEventListener('blur', blur)
    root.classList.remove('is-dragging')
    handle?.classList.remove('is-focused')
  }
}

webx.widget?.('compare', '[data-webx-compare]', compare)
