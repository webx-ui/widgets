/*
 * The photos of a card (spec §14). Its own file, claimed by `<x-webx-photos>` with two photos or
 * more; no library.
 *
 * The time is the stylesheet's: the bar of the shown photo fills by a CSS animation over the
 * interval, and its `animationend` is what moves on. A pause — under the pointer, with the focus
 * inside, off the screen, by the button — is `animation-play-state`, so a photo paused half way
 * goes on from half way. With `prefers-reduced-motion` nothing starts; the button offers to.
 *
 * A finger or a mouse drags the photo sideways over the frame, the next one coming in beside it;
 * let go, it pages past 15% of the frame or by a flick (`swipe.js`, the package's settings in
 * `swipe`), and past the first or the last photo it gives way and goes back. A touchpad's
 * sideways scroll pages a photo a gesture. A card that is a link has its link stretched over the
 * frame; it names itself the surface (`data-webx-photos-surface`), the drag is read there where
 * it lies over the frame, and the click a drag would end in is swallowed — a tap still follows
 * the link. The press that starts a drag goes no further: a slider of cards around the card
 * moves with the rest of the card, not with its photos.
 */

import '../css/photos.css'
import { unhold } from './held.js'
import { flick, swipeSettings, wheelSteps } from './swipe.js'

const webx = (window.webx ??= {})

/** Moved this many pixels, the drag is told sideways from down the page. */
const LOCK = 6

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
  const swipe = swipeSettings(config.swipe)

  let index = 0
  // Stopped by the button, or never started: with reduced motion, until the button starts it.
  let stopped = !autoplay || reduced
  let still = stopped
  let hovered = false
  let focused = false
  let seen = true

  const show = (next) => {
    index = (next + count) % count
    // A photo held back (§14.2) gets its address as it is shown, and the next one ahead of time.
    unhold(pictures[index])
    unhold(pictures[(index + 1) % count])
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

  // A drag over the frame, read where the press lands: on the frame, or on the card's link over it.
  let start = null
  let swallow = false
  const over = ({ clientX: x, clientY: y }) => {
    const box = frame.getBoundingClientRect()
    return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom
  }
  const rtl = () => getComputedStyle(root).direction === 'rtl'
  // The photo a step away, or none past the first and the last: a drag does not go round.
  const beside = (step) => {
    const at = index + step
    return at >= 0 && at < count ? at : -1
  }

  // The photos under the finger, out of the crossfade, where the drag put them: the shown one,
  // and the one coming in `side` pixels beside it.
  let moved = []
  let side = 0
  const slide = (at, x, state) => {
    pictures[at].classList.add(state)
    pictures[at].style.transform = `translateX(${x}px)`
  }
  const release = (at) => {
    const { style, classList } = pictures[at]
    // Back in the crossfade at once: a photo that slid out does not fade out over the one in.
    style.transition = 'none'
    classList.remove('is-dragged', 'is-gliding')
    style.transform = ''
    void pictures[at].offsetWidth
    style.transition = ''
  }
  const place = (dx, width) => {
    const next = beside((dx < 0 ? 1 : -1) * (rtl() ? -1 : 1))
    const shift = next === -1 ? dx * swipe.resistance : dx
    const now = next === -1 ? [index] : [index, next]
    moved.filter((at) => !now.includes(at)).forEach(release)
    moved = now
    side = dx < 0 ? width : -width
    slide(index, shift, 'is-dragged')
    if (next !== -1) slide(next, shift + side, 'is-dragged')
  }
  // Let go: the photos glide to where they rest — the one coming in to the middle and the shown
  // one out, or both back — then are the crossfade's again. With reduced motion, at once.
  const settle = (to) => {
    const from = index
    const done = moved
    moved = []
    if (to !== from) show(to)
    if (reduced || done.length === 0) {
      done.forEach(release)
      return
    }
    done.forEach((at) => slide(at, at === to ? 0 : to === from ? side : -side, 'is-gliding'))
    // Once there — the glide is the stylesheet's, its end the photo's `transitionend` — unless a
    // new drag took the photo meanwhile.
    const end = () => done.filter((at) => !moved.includes(at)).forEach(release)
    pictures[to].addEventListener('transitionend', end, { once: true })
    setTimeout(end, 1000)
  }

  listen(surface, 'pointerdown', (event) => {
    if (event.pointerType === 'mouse' && event.button !== 0) return
    if (event.target.closest('.webx-photos__bars, .webx-photos__pause') || !over(event)) return
    start = {
      id: event.pointerId,
      x: event.clientX,
      y: event.clientY,
      t: performance.now(),
      axis: null,
    }
    event.stopPropagation()
  })
  listen(surface, 'pointermove', (event) => {
    if (!start || event.pointerId !== start.id) return
    const dx = event.clientX - start.x
    const dy = event.clientY - start.y
    if (start.axis === null && (Math.abs(dx) > LOCK || Math.abs(dy) > LOCK)) {
      start.axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y'
      if (start.axis === 'x') {
        try {
          surface.setPointerCapture?.(event.pointerId)
        } catch {
          // A pointer already gone: the drag goes on without the capture.
        }
        root.classList.add('is-dragging')
      }
    }
    if (start.axis !== 'x') return
    event.preventDefault()
    place(dx, frame.getBoundingClientRect().width)
  })
  const up = (event, cancel) => {
    if (!start || event.pointerId !== start.id) return
    const dx = event.clientX - start.x
    const dy = event.clientY - start.y
    const ms = performance.now() - start.t
    const axis = start.axis ?? (Math.abs(dx) > LOCK && Math.abs(dx) > Math.abs(dy) ? 'x' : 'y')
    start = null
    root.classList.remove('is-dragging')
    if (cancel || axis !== 'x') {
      settle(index)
      return
    }
    // A drag is no tap: the click it ends in opens nothing, whether it paged or not.
    swallow = true
    setTimeout(() => {
      swallow = false
    })
    const step = flick(dx, ms, frame.getBoundingClientRect().width, swipe) * (rtl() ? -1 : 1)
    const to = step === 0 ? -1 : beside(step)
    settle(to === -1 ? index : to)
  }
  listen(surface, 'pointerup', up)
  // Taken by the browser (a scroll after all): back where it was.
  listen(surface, 'pointercancel', (event) => up(event, true))

  // A touchpad: a step a gesture. A gesture the photos take stops here, so a slider of cards
  // around the card does not page with it; one past the first or the last is not theirs and
  // goes on to that slider, as it would over the card's text.
  const steps = wheelSteps(
    (step) => {
      const to = beside(step * (rtl() ? -1 : 1))
      if (to !== -1) show(to)
    },
    swipe,
    undefined,
    (step) => beside(step * (rtl() ? -1 : 1)) !== -1,
  )
  listen(
    surface,
    'wheel',
    (event) => {
      if (over(event) && steps(event)) event.stopPropagation()
    },
    { passive: false },
  )
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
