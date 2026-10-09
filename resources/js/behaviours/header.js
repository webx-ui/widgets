/*
 * `[data-webx-header]` — `<x-webx-header>` (spec §6.2):
 *
 * - folds the navigation into the mobile menu (`is-collapsed` on the header, its navigation,
 *   its actions and its trigger): `data-collapse="auto"` when the items no longer fit in their
 *   row, a number when the header is narrower than that, `never` never. `auto` is measured, not
 *   guessed: unfold, look whether every item lies inside the navigation and the actions inside
 *   the bar, fold back if not — inside one frame, so nothing flickers. That holds whatever grid
 *   the theme arranged the bar into;
 * - keeps `--webx-header-height` (what stays on screen when it sticks) and
 *   `--webx-header-topbar-height` on :root, and the page's scroll padding with them, so an anchor
 *   is not hidden under a sticky header;
 * - `is-scrolled` past its own height, and with `hide-on-scroll` `is-hidden` while scrolling down
 *   — but not while a dropdown or the menu is open or the keyboard is inside.
 */

import { listeners } from '../core.js'

const px = (value) => `${Math.round(value)}px`

export function header(el) {
  const root = document.documentElement
  const bar = el.querySelector('.webx-header__bar')
  const topbar = el.querySelector(':scope > .webx-header__topbar')
  const nav = bar?.querySelector(':scope > .webx-header__nav') ?? null
  const actions = bar?.querySelector(':scope > .webx-header__actions') ?? null
  const mode = el.getAttribute('data-collapse') ?? 'auto'
  const sticky = el.getAttribute('data-sticky') ?? 'none'
  const on = listeners()

  const folding = () =>
    [el, nav, actions, ...el.querySelectorAll('.webx-header__trigger')].filter(Boolean)

  const apply = (folded) => {
    for (const part of folding()) part.classList.toggle('is-collapsed', folded)
  }

  // Every item of the navigation inside the navigation, on one line; the actions inside the bar.
  const fits = () => {
    if (!nav || !bar) return true
    const box = nav.getBoundingClientRect()
    const list = nav.querySelector(':scope > .webx-header-nav') ?? nav
    let line = null
    for (const item of list.children) {
      const r = item.getBoundingClientRect()
      if (r.width === 0 && r.height === 0) continue
      if (r.left < box.left - 1 || r.right > box.right + 1) return false
      if (line === null) line = r
      else if (r.top >= line.bottom) return false
    }
    const style = getComputedStyle(bar)
    const inner = bar.getBoundingClientRect()
    const left = inner.left + parseFloat(style.paddingLeft)
    const right = inner.right - parseFloat(style.paddingRight)
    for (const part of [actions, bar.querySelector(':scope > .webx-header__brand')]) {
      if (!part) continue
      const r = part.getBoundingClientRect()
      if (r.left < left - 1 || r.right > right + 1) return false
    }
    return bar.scrollWidth <= bar.clientWidth + 1
  }

  const fold = () => {
    if (mode === 'never') return apply(false)
    if (/^\d+$/.test(mode)) return apply(el.clientWidth < Number(mode))
    apply(false)
    apply(!fits())
  }

  const measure = () => {
    const top = topbar ? topbar.offsetHeight : 0
    root.style.setProperty('--webx-header-topbar-height', px(top))
    root.style.setProperty('--webx-header-height', px(el.offsetHeight - top))
  }

  let frame = 0
  const update = () => {
    if (frame) return
    // Next frame rather than inside the observer: folding resizes the header it observes.
    frame = requestAnimationFrame(() => {
      frame = 0
      const folded = el.classList.contains('is-collapsed')
      fold()
      // Widened past the point the menu was needed: it closes with the trigger gone.
      if (folded && !el.classList.contains('is-collapsed')) {
        for (const dialog of el.querySelectorAll('dialog[open]')) dialog.close()
      }
      measure()
    })
  }

  fold()
  measure()
  if (sticky !== 'none') root.classList.add('webx-header-sticky')

  let resize = null
  if (typeof ResizeObserver === 'function') {
    resize = new ResizeObserver(update)
    for (const part of [el, nav?.firstElementChild, actions].filter(Boolean)) resize.observe(part)
  }
  on(window, 'resize', update)
  document.fonts?.ready?.then(update)

  // Scrolling: solid past its own height; hidden on the way down, back on the way up.
  let last = window.scrollY
  let ticking = false
  // The keyboard's focus, not a mouse's: a clicked trigger keeps the focus after its menu closed.
  const keyboard = () => {
    try {
      return el.querySelector(':focus-visible') !== null
    } catch {
      return el.contains(document.activeElement)
    }
  }
  const busy = () => el.querySelector('.is-open, dialog[open]') !== null || keyboard()

  const scrolled = () => {
    ticking = false
    const y = window.scrollY
    const height = el.offsetHeight
    el.classList.toggle('is-scrolled', y > height)
    if (sticky === 'hide-on-scroll') {
      if (y <= height || y < last - 2 || busy()) el.classList.remove('is-hidden')
      else if (y > last + 2) el.classList.add('is-hidden')
    }
    last = y
  }

  on(
    window,
    'scroll',
    () => {
      if (ticking) return
      ticking = true
      requestAnimationFrame(scrolled)
    },
    { passive: true },
  )
  on(el, 'focusin', () => el.classList.remove('is-hidden'))
  scrolled()

  return () => {
    on.off()
    resize?.disconnect()
    cancelAnimationFrame(frame)
    apply(false)
    el.classList.remove('is-scrolled', 'is-hidden')
    root.classList.remove('webx-header-sticky')
    root.style.removeProperty('--webx-header-height')
    root.style.removeProperty('--webx-header-topbar-height')
  }
}
