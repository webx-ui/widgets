/*
 * Smooth scrolling (spec §14): `data-webx-smooth-scroll` on <html> or <body> turns a notch of the
 * wheel into one stretched glide of the page instead of a jump — Lenis, built in here and only
 * here: the server claims this file where the attribute stands, a page without it never loads it.
 *
 * Lenis moves the window's own scroll, so `position: sticky`, the header's `--webx-header-height`
 * and `data-webx-reveal` see what they always see. It stays out of the way:
 *
 * - with reduced motion, on a touch screen (no hover, a coarse pointer: the finger has its own
 *   inertia), and without JavaScript the page scrolls natively — with the theme's own
 *   `scroll-behavior: smooth` for anchors, which `.webx-smooth-scroll` sets aside while it glides;
 * - a modal of the runtime (`html.webx-scroll-locked`: a dialog, the mobile menu, a form in a
 *   dialog) pauses it, closing it resumes it;
 * - whatever scrolls inside the page scrolls itself: any element that can, dialogs, the lightbox,
 *   the map, the panels of the widgets, and `[data-webx-scroll-native]` written by the theme.
 *
 * A link to an anchor of the same page glides to it, over the sticky header (`scroll-margin-top`,
 * `scroll-padding-top` — the header keeps the latter), and puts the anchor into the address as
 * a click would, so Back returns. A link activated from the keyboard keeps the browser's jump:
 * the focus has to go where the link points.
 *
 * Settings, each an attribute beside the switch or the widget's token on :root, the attribute
 * winning: `data-webx-smooth-scroll-duration` / `--webx-smooth-scroll-duration` (1.1s),
 * `-easing` (`quart`, `cubic`, `expo`, `sine`), `-wheel` (0.9: the distance of a notch),
 * `-anchor-duration` (1.4s, eased in and out).
 */

import '../css/smooth-scroll.css'
import Lenis from 'lenis'

const webx = (window.webx ??= {})

/** Out-easings for the wheel, by name; `quart` is the one the designs are made with. */
export const EASINGS = {
  quart: (t) => 1 - (1 - t) ** 4,
  cubic: (t) => 1 - (1 - t) ** 3,
  expo: (t) => (t >= 1 ? 1 : 1 - 2 ** (-10 * t)),
  sine: (t) => Math.sin((t * Math.PI) / 2),
}

/** A long way to an anchor starts slowly as well: in and out. */
const ANCHOR_EASING = (t) => (t < 0.5 ? 4 * t ** 3 : 1 - (-2 * t + 2) ** 3 / 2)

export const DEFAULTS = { duration: 1.1, easing: 'quart', wheel: 0.9, anchorDuration: 1.4 }

/**
 * What keeps its own scroll: Lenis lets the wheel through to it. Dialogs (and the mobile menu,
 * one of them), the lightbox, the map's zoom, the panels of the dropdown and the suggestions.
 */
export const NATIVE =
  '[data-webx-scroll-native], dialog, .pswp, .webx-map, .webx-dropdown__panel, .webx-suggest__panel, .webx-consent-root'

const LOCKED = 'webx-scroll-locked'

// One page, one glide: the switch written on both <html> and <body> starts it once.
let active = false

const media = (query) => window.matchMedia?.(query).matches ?? false

/** Whether this visitor gets the glide: a mouse or a touchpad, and motion not reduced. */
export function wanted() {
  if (media('(prefers-reduced-motion: reduce)')) return false
  return media('(hover: hover) and (pointer: fine)')
}

/** Seconds out of `1.1`, `1.1s` or `1100ms`; null for anything else. */
export function seconds(value) {
  const match = /^\s*(\d*\.?\d+)\s*(ms|s)?\s*$/.exec(value ?? '')
  if (!match) return null
  const number = parseFloat(match[1])
  return match[2] === 'ms' ? number / 1000 : number
}

/** The settings of the page: the attribute on the switch, else the token, else the default. */
export function settings(el) {
  const tokens = getComputedStyle(document.documentElement)
  const read = (name) =>
    el.getAttribute(`data-webx-smooth-scroll-${name}`) ??
    tokens.getPropertyValue(`--webx-smooth-scroll-${name}`).trim()

  const positive = (number, fallback) => (number > 0 && number < 10 ? number : fallback)
  const easing = read('easing')

  return {
    duration: positive(seconds(read('duration')), DEFAULTS.duration),
    easing: easing in EASINGS ? easing : DEFAULTS.easing,
    wheel: positive(parseFloat(read('wheel')), DEFAULTS.wheel),
    anchorDuration: positive(seconds(read('anchor-duration')), DEFAULTS.anchorDuration),
  }
}

/** The element a same-page link points to, `0` for the top of the page, null for any other link. */
function anchorOf(link) {
  if (link.target && link.target !== '_self') return null
  if (link.hasAttribute('download')) return null
  // Openers of the runtime's dialogs and of the lightbox: theirs to handle.
  if (link.matches('[data-webx-dialog], [data-webx-form], [data-webx-lightbox]')) return null

  const url = new URL(link.href, location.href)
  if (url.origin !== location.origin || url.pathname !== location.pathname) return null
  if (url.search !== location.search || !url.hash) return null

  const id = decodeURIComponent(url.hash.slice(1))
  if (id === '' || id === 'top') return document.getElementById(id) ?? 0
  const target = document.getElementById(id)
  return target && !(target instanceof HTMLDialogElement) ? target : null
}

export function smoothScroll(el) {
  if (active || !wanted()) return
  active = true

  const root = document.documentElement
  const options = settings(el)
  const lenis = new Lenis({
    autoRaf: true,
    duration: options.duration,
    easing: EASINGS[options.easing],
    wheelMultiplier: options.wheel,
    // The finger keeps the browser's own inertia, should a touch reach here at all.
    syncTouch: false,
    allowNestedScroll: true,
    prevent: (node) => node.matches(NATIVE),
  })

  root.classList.add('webx-smooth-scroll')
  webx.scrollTo = (target, more = {}) => lenis.scrollTo(target, more)

  // A modal of the runtime locks the page: the glide waits until it is closed.
  const pause = () => (root.classList.contains(LOCKED) ? lenis.stop() : lenis.start())
  const locks = new MutationObserver(pause)
  locks.observe(root, { attributes: true, attributeFilter: ['class'] })
  pause()

  const click = (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.detail === 0) return
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null
    if (!link) return
    const target = anchorOf(link)
    if (target === null) return

    event.preventDefault()
    const hash = new URL(link.href, location.href).hash
    if (hash !== location.hash) history.pushState(history.state, '', hash)
    // From where the page really is: a jump the scroll event has not reported yet would be counted
    // from the old place, and the anchor missed by that much.
    lenis.reset()
    lenis.scrollTo(target, { duration: options.anchorDuration, easing: ANCHOR_EASING })
  }

  // Back and Forward: the browser has put the page where it was; the glide starts from there.
  const restored = () => lenis.reset()

  document.addEventListener('click', click)
  window.addEventListener('popstate', restored)

  return () => {
    document.removeEventListener('click', click)
    window.removeEventListener('popstate', restored)
    locks.disconnect()
    lenis.destroy()
    root.classList.remove('webx-smooth-scroll')
    delete webx.scrollTo
    active = false
  }
}

webx.widget?.(
  'smooth-scroll',
  'html[data-webx-smooth-scroll], body[data-webx-smooth-scroll]',
  smoothScroll,
)
