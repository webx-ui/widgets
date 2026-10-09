/*
 * `[data-webx-header-nav]` — the dropdowns of `<x-webx-header.nav>` (spec §6.2). One is open at
 * a time (`is-open` on the item and its panel, `aria-expanded` on its toggles).
 *
 * - Mouse: opens on hover after a short delay and closes a while after the pointer left, so a
 *   pass over the menu opens nothing and a slip off it closes nothing. While a dropdown is open,
 *   a pointer heading for it across a neighbour — inside the triangle between where it was and
 *   the panel's near edge — does not switch to the neighbour.
 * - Touch: the first tap on an item's link opens its dropdown, the second follows the link.
 * - Keyboard: the toggle opens on Enter and Space; ↓ opens and goes to the first entry, ↑ ↓
 *   move inside, ← → go along the top level, Esc closes and gives focus back, Tab out closes.
 * - A dropdown that would cross the window's right edge is held against it (`is-flipped`).
 */

import { listeners } from '../core.js'
import { trail } from './corridor.js'

const OPEN_DELAY = 120
const CLOSE_DELAY = 300

const focusables = (el) =>
  el ? Array.from(el.querySelectorAll('a[href], button:not([disabled])')) : []

export function headerNav(list) {
  const on = listeners()
  const items = Array.from(list.children).filter((item) =>
    item.querySelector(':scope > [data-webx-header-nav-toggle]'),
  )
  const toggles = (item) => item.querySelectorAll(':scope > [data-webx-header-nav-toggle]')
  const panelOf = (item) =>
    document.getElementById(toggles(item)[0]?.getAttribute('aria-controls') ?? '')
  const tops = () =>
    Array.from(list.children)
      .map((item) => item.querySelector(':scope > .webx-header-nav__link'))
      .filter((link) => link && link.tagName !== 'SPAN')

  let current = null
  let openTimer = 0
  let closeTimer = 0
  let pointer = 'mouse'
  const path = trail()
  let hovered = null

  const set = (item, open) => {
    const panel = panelOf(item)
    item.classList.toggle('is-open', open)
    panel?.classList.toggle('is-open', open)
    for (const toggle of toggles(item)) toggle.setAttribute('aria-expanded', String(open))
    if (!panel) return
    panel.classList.remove('is-flipped')
    if (open && panel.classList.contains('webx-header-nav__dropdown')) {
      const box = panel.getBoundingClientRect()
      if (box.right > document.documentElement.clientWidth) panel.classList.add('is-flipped')
    }
  }

  const open = (item) => {
    clearTimeout(openTimer)
    clearTimeout(closeTimer)
    if (current === item) return
    if (current) set(current, false)
    current = item
    set(item, true)
  }

  const close = () => {
    clearTimeout(openTimer)
    clearTimeout(closeTimer)
    if (current) set(current, false)
    current = null
  }

  const heading = () => path.heading(current ? panelOf(current) : null)

  for (const item of items) {
    on(item, 'pointerenter', (event) => {
      if (event.pointerType !== 'mouse') return
      hovered = item
      clearTimeout(closeTimer)
      clearTimeout(openTimer)
      if (current === item) return
      const wait = current && heading() ? CLOSE_DELAY : current ? 0 : OPEN_DELAY
      openTimer = setTimeout(() => {
        if (hovered === item) open(item)
      }, wait)
    })
    on(item, 'pointerleave', (event) => {
      if (event.pointerType !== 'mouse') return
      if (hovered === item) hovered = null
      clearTimeout(openTimer)
      if (current === item) closeTimer = setTimeout(close, CLOSE_DELAY)
    })

    for (const toggle of toggles(item)) {
      on(toggle, 'click', (event) => {
        event.preventDefault()
        if (current === item) return close()
        open(item)
        // From the keyboard (no pointer position), straight into the list.
        if (event.detail === 0) focusables(panelOf(item))[0]?.focus()
      })
    }

    const link = item.querySelector(':scope > a.webx-header-nav__link')
    if (link) {
      on(link, 'click', (event) => {
        if ((pointer === 'touch' || pointer === 'pen') && current !== item) {
          event.preventDefault()
          open(item)
        }
      })
    }

    on(item, 'focusout', (event) => {
      if (current === item && !item.contains(event.relatedTarget)) close()
    })
  }

  on(list, 'pointerdown', (event) => (pointer = event.pointerType || 'mouse'), true)
  on(document, 'pointermove', (event) => {
    if (!current || event.pointerType !== 'mouse') return
    path.push(event)
  })

  on(list, 'keydown', (event) => {
    const target = event.target instanceof Element ? event.target : null
    if (!target) return
    const item = target.closest('.webx-header-nav__item')
    if (!item || item.parentElement !== list) return
    const panel = panelOf(item)
    const inPanel = panel?.contains(target) ?? false
    const top = tops()
    const at = top.indexOf(item.querySelector(':scope > .webx-header-nav__link'))

    if (event.key === 'Escape' && current) {
      event.preventDefault()
      const back = toggles(item)[toggles(item).length - 1]
      close()
      back?.focus()
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      if (!panel) return
      event.preventDefault()
      const entries = focusables(panel)
      if (!inPanel) {
        open(item)
        entries[event.key === 'ArrowDown' ? 0 : entries.length - 1]?.focus()
        return
      }
      const i = entries.indexOf(target)
      const next = event.key === 'ArrowDown' ? i + 1 : i - 1
      entries[(next + entries.length) % entries.length]?.focus()
    } else if ((event.key === 'ArrowRight' || event.key === 'ArrowLeft') && !inPanel && at >= 0) {
      event.preventDefault()
      const step = event.key === 'ArrowRight' ? 1 : -1
      top[(at + step + top.length) % top.length]?.focus()
    }
  })

  on(document, 'click', (event) => {
    if (current && !current.contains(event.target)) close()
  })

  return () => {
    on.off()
    close()
  }
}
