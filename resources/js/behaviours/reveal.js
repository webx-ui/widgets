/*
 * `data-webx-reveal` (spec §14): an element comes up, fades or grows in as it scrolls into view,
 * once. `up` (the default), `fade`, `scale`; `stagger` on a container brings its children in one
 * after another, each with `data-webx-reveal-effect` of the container (`up` by default).
 *
 * Only this script hides anything, and only what it will show: without JavaScript, with reduced
 * motion, without IntersectionObserver every element is simply there. What is on the screen or
 * above it is left alone — hiding it would make it blink. Where an element stands is the
 * observer's first word about it, not a measure taken as the script runs: the runtime mounts
 * while the page is still settling (a header that shortens once its widgets start), and an
 * element measured below the fold then is on the first screen a frame later. Once shown, the
 * element loses the classes and the delay again: its own transitions (a card's hover) are its own.
 *
 * A link to an anchor of the page brings in at once whatever holds the anchor, before the page
 * moves: the browser (and the glide of `data-webx-smooth-scroll`) measures where to stop from
 * where the anchor stands, and an anchor still lowered by `up` would come up afterwards — under
 * the sticky header, by the distance of the effect. For the same reason the anchor of the address
 * a page opens with is never hidden: the browser may go to it after the observer's first word.
 */

import { listeners } from '../core.js'

const EFFECTS = ['up', 'fade', 'scale']

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

/** The element of this page a hash names, null for none. */
function named(hash) {
  if (hash.length < 2) return null
  try {
    return document.getElementById(decodeURIComponent(hash.slice(1)))
  } catch {
    return null
  }
}

/** The element a link to this very page points to, null for any other link. */
function anchorOf(link) {
  let url
  try {
    url = new URL(link.href, location.href)
  } catch {
    return null
  }
  if (url.origin !== location.origin || url.pathname !== location.pathname) return null
  return url.search === location.search ? named(url.hash) : null
}

export function reveal(root) {
  if (reduced() || typeof IntersectionObserver !== 'function') return

  const mode = root.getAttribute('data-webx-reveal') || 'up'
  const stagger = mode === 'stagger'
  const effectOf = stagger ? root.getAttribute('data-webx-reveal-effect') || 'up' : mode
  const effect = EFFECTS.includes(effectOf) ? effectOf : 'up'
  const targets = stagger ? Array.from(root.children) : [root]
  const on = listeners()
  const timers = new Set()

  // Hidden, waiting to come in.
  const pending = []
  const placed = new Set()

  const settle = (el) => {
    el.classList.remove('webx-reveal', `webx-reveal--${effect}`, 'is-pending')
    el.style.removeProperty('--webx-reveal-delay')
  }

  const show = (el, index) => {
    if (index)
      el.style.setProperty('--webx-reveal-delay', `calc(${index} * var(--webx-reveal-stagger))`)
    el.classList.remove('is-pending')
    // Its own transitions back once this one ends; the timer for an element that never runs one.
    const done = (event) => {
      if (event && (event.target !== el || event.propertyName !== 'opacity')) return
      el.removeEventListener('transitionend', done)
      settle(el)
    }
    el.addEventListener('transitionend', done)
    const timer = setTimeout(() => {
      timers.delete(timer)
      done()
    }, 3000)
    timers.add(timer)
  }

  const observer = new IntersectionObserver(
    (entries) => {
      const shown = []
      const anchor = named(location.hash)
      for (const entry of entries) {
        const el = entry.target
        if (!placed.has(el)) {
          // The first word, once the page is laid out. Below the fold only: what the visitor
          // already sees, or scrolled past, stays as it is. Off the screen, the hiding is unseen.
          placed.add(el)
          if (entry.boundingClientRect.top >= window.innerHeight && !el.contains(anchor)) {
            el.classList.add('webx-reveal', `webx-reveal--${effect}`, 'is-pending')
            pending.push(el)
          } else {
            observer.unobserve(el)
          }
          continue
        }
        if (entry.isIntersecting) shown.push(el)
      }
      // Those that came into view together come one after another: the container's order.
      shown.sort((a, b) => targets.indexOf(a) - targets.indexOf(b))
      shown.forEach((el, index) => {
        observer.unobserve(el)
        show(el, stagger ? index : 0)
      })
    },
    { rootMargin: '0px 0px -8% 0px' },
  )

  for (const el of targets) observer.observe(el)

  // Captured on the window: before the browser follows the link and before the glide's own handler.
  const arrive = (event) => {
    const link = event.target instanceof Element ? event.target.closest('a[href*="#"]') : null
    const anchor = link ? anchorOf(link) : null
    if (!anchor) return
    for (const el of targets) {
      if (!el.contains(anchor) || !el.classList.contains('webx-reveal')) continue
      observer.unobserve(el)
      settle(el)
    }
  }
  on(window, 'click', arrive, { capture: true })

  // A printed page has no scrolling to bring anything in.
  on(window, 'beforeprint', () => pending.forEach(settle))

  return () => {
    observer.disconnect()
    timers.forEach(clearTimeout)
    on.off()
    pending.forEach(settle)
  }
}
