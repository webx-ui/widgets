/*
 * The photos of a card (spec §14). Its own file, claimed by `<x-webx-photos>` with two photos or
 * more; no library.
 *
 * The time is the stylesheet's: the bar of the shown photo fills by a CSS animation over the
 * interval, and its `animationend` is what moves on. A pause — under the pointer, with the focus
 * inside, off the screen, by the button — is `animation-play-state`, so a photo paused half way
 * goes on from half way. With `prefers-reduced-motion` nothing starts; the button offers to.
 *
 * A finger swipes sideways over the frame. A card that is a link has its link stretched over the
 * frame; it names itself the surface (`data-webx-photos-surface`), the swipe is read there where
 * it lies over the frame, and the click a swipe would end in is swallowed — a tap still follows
 * the link. The press that starts a swipe goes no further: a slider of cards around the card
 * moves with the rest of the card, not with its photos.
 */

import '../css/photos.css'

const webx = (window.webx ??= {})

/** Sideways by this many pixels, and more sideways than down: the finger means the photos. */
const SWIPE = 32

export function photos(root) {
  const frame = root.querySelector('.webx-photos__frame')
  const pictures = frame ? Array.from(frame.querySelectorAll('.webx-photos__photo')) : []
  const bars = root.querySelector('.webx-photos__bars')
  const buttons = bars ? Array.from(bars.querySelectorAll('.webx-photos__bar')) : []
  const fills = buttons.map((button) => button.querySelector('.webx-photos__fill'))
  const pause = root.querySelector('.webx-photos__pause')
  if (pictures.length < 2) return

  let config
  try {
    config = JSON.parse(root.dataset.webxPhotos || '{}')
  } catch {
    return
  }

  const text = config.words ?? {}
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
  const autoplay = config.autoplay > 0
  const surface = root.closest('[data-webx-photos-surface]') ?? root
  const count = pictures.length

  let index = 0
  // Stopped by the button, or never started: with reduced motion, until the button starts it.
  let stopped = !autoplay || reduced
  let still = stopped
  let hovered = false
  let focused = false
  let seen = true

  const show = (next) => {
    index = (next + count) % count
    pictures.forEach((picture, at) => {
      picture.classList.toggle('is-active', at === index)
      if (at === index) picture.removeAttribute('aria-hidden')
      else picture.setAttribute('aria-hidden', 'true')
    })
    buttons.forEach((button, at) => {
      if (at === index) button.setAttribute('aria-current', 'true')
      else button.removeAttribute('aria-current')
      const fill = fills[at]
      if (!fill) return
      fill.classList.toggle('is-active', at === index)
      fill.classList.toggle('is-done', at < index)
      fill.classList.toggle('is-still', at === index && still)
    })
  }

  const hold = () => {
    root.classList.toggle('is-paused', stopped || hovered || focused || !seen)
  }

  const label = () => {
    if (!pause) return
    pause.classList.toggle('is-paused', stopped)
    pause.setAttribute('aria-label', stopped ? text.play : text.pause)
  }

  const on = []
  const listen = (target, type, listener, options) => {
    target.addEventListener(type, listener, options)
    on.push(() => target.removeEventListener(type, listener, options))
  }

  // The bar of the shown photo is full: the next one.
  listen(root, 'animationend', (event) => {
    if (event.target === fills[index] && !stopped) show(index + 1)
  })

  buttons.forEach((button, at) => listen(button, 'click', () => show(at)))

  if (pause) {
    listen(pause, 'click', () => {
      stopped = !stopped
      if (still && !stopped) {
        still = false
        show(index)
      }
      label()
      hold()
    })
  }

  listen(root, 'pointerenter', () => {
    hovered = true
    hold()
  })
  listen(root, 'pointerleave', () => {
    hovered = false
    hold()
  })
  listen(root, 'focusin', () => {
    focused = true
    hold()
  })
  listen(root, 'focusout', (event) => {
    if (root.contains(event.relatedTarget)) return
    focused = false
    hold()
  })

  // The arrows move between the bars while the focus is on them.
  listen(root, 'keydown', (event) => {
    const step = { ArrowLeft: -1, ArrowRight: 1 }[event.key]
    if (!step || !event.target.closest('.webx-photos__bars')) return
    event.preventDefault()
    const rtl = getComputedStyle(root).direction === 'rtl'
    show(index + (rtl ? -step : step))
    buttons[index]?.focus()
  })

  // A swipe over the frame, read where the press lands: on the frame, or on the card's link over it.
  let start = null
  let swallow = false
  const over = (event) => {
    const box = frame.getBoundingClientRect()
    return (
      event.clientX >= box.left &&
      event.clientX <= box.right &&
      event.clientY >= box.top &&
      event.clientY <= box.bottom
    )
  }
  listen(surface, 'pointerdown', (event) => {
    if (event.pointerType === 'mouse' && event.button !== 0) return
    if (event.target.closest('.webx-photos__bars, .webx-photos__pause') || !over(event)) return
    start = { id: event.pointerId, x: event.clientX, y: event.clientY }
    event.stopPropagation()
  })
  listen(surface, 'pointerup', (event) => {
    if (!start || event.pointerId !== start.id) return
    const dx = event.clientX - start.x
    const dy = event.clientY - start.y
    start = null
    if (Math.abs(dx) < SWIPE || Math.abs(dx) < Math.abs(dy)) return
    const rtl = getComputedStyle(root).direction === 'rtl'
    show(index + (dx < 0 !== rtl ? 1 : -1))
    swallow = true
    setTimeout(() => {
      swallow = false
    })
  })
  listen(surface, 'pointercancel', () => {
    start = null
  })
  // The click that ends a swipe opens nothing; a link dragged out of a card is no swipe either.
  listen(
    surface,
    'click',
    (event) => {
      if (!swallow) return
      swallow = false
      event.preventDefault()
      event.stopPropagation()
    },
    true,
  )
  listen(surface, 'dragstart', (event) => {
    if (over(event)) event.preventDefault()
  })

  // Off the screen the time stands: a card scrolled past does not come back three photos on.
  let seer = null
  if (typeof IntersectionObserver === 'function') {
    seer = new IntersectionObserver(([entry]) => {
      seen = entry?.isIntersecting ?? true
      hold()
    })
    seer.observe(root)
  }

  if (surface !== root) surface.style.touchAction = 'pan-y pinch-zoom'
  root.classList.add('is-ready')
  if (bars) bars.hidden = false
  if (pause) pause.hidden = false
  show(0)
  label()
  hold()

  return () => {
    seer?.disconnect()
    on.splice(0).forEach((remove) => remove())
    if (surface !== root) surface.style.touchAction = ''
    root.classList.remove('is-ready', 'is-paused')
    if (bars) bars.hidden = true
    if (pause) pause.hidden = true
    pictures.forEach((picture, at) => {
      picture.classList.toggle('is-active', at === 0)
      picture.removeAttribute('aria-hidden')
    })
    buttons.forEach((button) => button.removeAttribute('aria-current'))
    fills.forEach((fill) => fill?.classList.remove('is-active', 'is-done', 'is-still'))
  }
}

webx.widget?.('photos', '[data-webx-photos]', photos)
