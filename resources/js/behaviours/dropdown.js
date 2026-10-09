/*
 * `<details data-webx-dropdown="click|hover">` — `<x-webx-dropdown>` (spec §6.3): a panel tied to
 * its trigger, the `<summary>`. The `<details>` is what works without JavaScript — a click opens
 * the panel under the trigger — and stays the state with it: open is `details.open`, so the
 * trigger keeps the expanded state a screen reader already reads off a `<summary>`.
 *
 * - The panel is a `popover` where the browser has it: in the top layer, out of reach of an
 *   ancestor's `overflow: hidden` and `z-index` — a dropdown in a sticky header, in a card. It
 *   is placed by the script (fixed, from the trigger's box) and turned over at the window's
 *   edge: up when there is no room below, to the other side when it would cross the right or
 *   the left edge (`is-flipped`), held inside the window when neither side fits.
 * - `hover` opens after a short delay and closes a while after the pointer left, with the
 *   corridor of the header's dropdowns; a touch or a pen always works it as `click`.
 * - Enter and Space on the trigger open and close, Esc closes and gives focus back to the
 *   trigger, Tab out of the panel closes, so does a click anywhere else.
 * - One open on the page: opening one closes the other.
 */

import { idOf, listeners } from '../core.js'
import { trail } from './corridor.js'

const OPEN_DELAY = 120
const CLOSE_DELAY = 300

/** The open one, whichever it is: its `close`. */
let current = null

const popovers = typeof HTMLElement !== 'undefined' && 'popover' in HTMLElement.prototype

/** A length of the stylesheet — px, rem or em — in pixels. */
function pixels(value, el) {
  const number = parseFloat(value)
  if (Number.isNaN(number)) return 0
  if (value.trim().endsWith('rem')) {
    return number * parseFloat(getComputedStyle(document.documentElement).fontSize)
  }
  if (value.trim().endsWith('em')) return number * parseFloat(getComputedStyle(el).fontSize)
  return number
}

export function dropdown(root) {
  const trigger = root.querySelector(':scope > summary')
  const panel = root.querySelector(':scope > .webx-dropdown__panel')
  if (!trigger || !panel) return

  const on = listeners()
  const hover = root.getAttribute('data-webx-dropdown') === 'hover'
  const placement = (
    Array.from(root.classList)
      .find((name) => name.startsWith('webx-dropdown--'))
      ?.slice('webx-dropdown--'.length) ?? 'bottom-start'
  ).split('-')
  const path = trail()
  let openTimer = 0
  let closeTimer = 0
  let hoveredAt = 0
  let following = null

  trigger.setAttribute('aria-controls', idOf(panel, 'webx-dropdown'))
  if (popovers) panel.popover = 'manual'

  const place = () => {
    const box = trigger.getBoundingClientRect()
    const width = panel.offsetWidth
    const height = panel.offsetHeight
    const view = { width: document.documentElement.clientWidth, height: window.innerHeight }
    const gap = pixels(getComputedStyle(panel).getPropertyValue('--webx-dropdown-offset'), panel)
    const [side, align = 'center'] = placement
    let flipped = false

    let top = side === 'top' ? box.top - gap - height : box.bottom + gap
    if (side !== 'top' && top + height > view.height && box.top - gap - height >= 0) {
      top = box.top - gap - height
      flipped = true
    } else if (side === 'top' && top < 0 && box.bottom + gap + height <= view.height) {
      top = box.bottom + gap
      flipped = true
    }

    let left =
      align === 'start'
        ? box.left
        : align === 'end'
          ? box.right - width
          : box.left + box.width / 2 - width / 2
    if (align === 'start' && left + width > view.width) {
      left = box.right - width
      flipped = true
    } else if (align === 'end' && left < 0) {
      left = box.left
      flipped = true
    }
    // Wider than either side allows: inside the window, however it lines up with the trigger.
    left = Math.max(0, Math.min(left, view.width - width))

    // Fixed in the top layer; without popovers, absolute inside the <details>.
    const origin = popovers ? { left: 0, top: 0 } : root.getBoundingClientRect()
    panel.style.inset = `${top - origin.top}px auto auto ${left - origin.left}px`
    panel.style.translate = 'none'
    panel.classList.toggle('is-flipped', flipped)
  }

  const follow = () => {
    if (following) return
    following = listeners()
    following(window, 'scroll', place, { capture: true, passive: true })
    following(window, 'resize', place)
  }

  const set = (open) => {
    clearTimeout(openTimer)
    clearTimeout(closeTimer)
    if (open === root.open && root.classList.contains('is-open') === open) return

    if (open) {
      if (current && current !== close) current()
      current = close
      root.open = true
      if (popovers && !panel.matches(':popover-open')) panel.showPopover()
      place()
      follow()
    } else {
      if (current === close) current = null
      if (popovers && panel.matches(':popover-open')) panel.hidePopover()
      root.open = false
      panel.style.inset = ''
      panel.style.translate = ''
      panel.classList.remove('is-flipped')
      following?.off()
      following = null
      path.clear()
    }
    root.classList.toggle('is-open', open)
    panel.classList.toggle('is-open', open)
    trigger.setAttribute('aria-expanded', String(open))
  }

  function close() {
    set(false)
  }

  trigger.setAttribute('aria-expanded', 'false')
  // Open in the markup (`open` from the server): opened the scripted way, placed and counted.
  if (root.open) {
    root.open = false
    set(true)
  }

  on(trigger, 'click', (event) => {
    event.preventDefault()
    // A mouse that hovered it open a moment ago clicks to keep it, not to close it again.
    if (hover && root.open && Date.now() - hoveredAt < 600) return
    set(!root.open)
  })

  if (hover) {
    on(root, 'pointerenter', (event) => {
      if (event.pointerType !== 'mouse') return
      clearTimeout(closeTimer)
      if (root.open) return
      // Another dropdown is open and the pointer is on its way there across this one.
      const passing = current && current !== close
      openTimer = setTimeout(
        () => {
          hoveredAt = Date.now()
          set(true)
        },
        passing ? CLOSE_DELAY : OPEN_DELAY,
      )
    })
    on(root, 'pointerleave', (event) => {
      if (event.pointerType !== 'mouse') return
      clearTimeout(openTimer)
      if (root.open) closeTimer = setTimeout(close, CLOSE_DELAY)
    })
    on(document, 'pointermove', (event) => {
      if (!root.open || event.pointerType !== 'mouse') return
      path.push(event)
      // Still heading for the panel across a neighbour: the neighbour waits.
      if (!root.contains(event.target) && path.heading(panel)) clearTimeout(closeTimer)
    })
  }

  on(document, 'keydown', (event) => {
    if (event.key !== 'Escape' || !root.open) return
    const inside = root.contains(document.activeElement)
    set(false)
    if (inside) trigger.focus()
  })
  on(document, 'click', (event) => {
    if (root.open && !root.contains(event.target)) set(false)
  })
  on(root, 'focusout', (event) => {
    if (root.open && event.relatedTarget && !root.contains(event.relatedTarget)) set(false)
  })
  // The browser toggles a <details> on its own too — find-in-page opening a closed one.
  on(root, 'toggle', () => {
    if (root.open !== root.classList.contains('is-open')) {
      const open = root.open
      root.open = !open
      set(open)
    }
  })

  return () => {
    on.off()
    set(false)
    if (popovers) panel.removeAttribute('popover')
    trigger.removeAttribute('aria-expanded')
  }
}
