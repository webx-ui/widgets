/*
 * `data-webx-reveal` (spec §14): an element comes up, fades or grows in as it scrolls into view,
 * once. `up` (the default), `fade`, `scale`; `stagger` on a container brings its children in one
 * after another, each with `data-webx-reveal-effect` of the container (`up` by default).
 *
 * Only this script hides anything, and only what it will show: without JavaScript, with reduced
 * motion, without IntersectionObserver every element is simply there. What is on the screen or
 * above it when the script runs is left alone — hiding it would make it blink. Once shown, the
 * element loses the classes and the delay again: its own transitions (a card's hover) are its own.
 */

import { listeners } from '../core.js'

const EFFECTS = ['up', 'fade', 'scale']

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

export function reveal(root) {
  if (reduced() || typeof IntersectionObserver !== 'function') return

  const mode = root.getAttribute('data-webx-reveal') || 'up'
  const stagger = mode === 'stagger'
  const effectOf = stagger ? root.getAttribute('data-webx-reveal-effect') || 'up' : mode
  const effect = EFFECTS.includes(effectOf) ? effectOf : 'up'
  const targets = stagger ? Array.from(root.children) : [root]
  const on = listeners()
  const timers = new Set()

  // Below the fold only: what the visitor already sees, or scrolled past, stays as it is.
  const pending = targets.filter((el) => el.getBoundingClientRect().top >= window.innerHeight)
  if (!pending.length) return

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
      // Those that came into view together come one after another: the container's order.
      const shown = entries
        .filter((entry) => entry.isIntersecting)
        .map((entry) => entry.target)
        .sort((a, b) => targets.indexOf(a) - targets.indexOf(b))
      shown.forEach((el, index) => {
        observer.unobserve(el)
        show(el, stagger ? index : 0)
      })
    },
    { rootMargin: '0px 0px -8% 0px' },
  )

  for (const el of pending) {
    el.classList.add('webx-reveal', `webx-reveal--${effect}`, 'is-pending')
    observer.observe(el)
  }

  // A printed page has no scrolling to bring anything in.
  on(window, 'beforeprint', () => pending.forEach(settle))

  return () => {
    observer.disconnect()
    timers.forEach(clearTimeout)
    on.off()
    pending.forEach(settle)
  }
}
