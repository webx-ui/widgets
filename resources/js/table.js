/*
 * Tables in prose (spec §14). The server put each one in a scroller (`Prose\Tables`) and claimed
 * this file; the table scrolls without it. Here only the edges: a shadow on the side there is
 * more of the table behind, and no tab stop on a table that fits — the scroller is focusable for
 * the keyboard to scroll it, which a table with nothing to scroll does not need.
 *
 * The shadows are left and right, not start and end: a right-to-left table scrolls the other
 * way, and the script tells which side hides something by where the scroller stands.
 */

import '../css/table.css'

const webx = (window.webx ??= {})

export function table(root) {
  const frame = root.querySelector('.webx-table__frame')
  const scroller = root.querySelector('.webx-table__scroller')
  if (!frame || !scroller) return

  const update = () => {
    const hidden = scroller.scrollWidth - scroller.clientWidth
    const scrolls = hidden > 1
    // Right to left, scrollLeft runs from 0 down to minus what is hidden.
    const rtl = getComputedStyle(scroller).direction === 'rtl'
    const passed = Math.abs(scroller.scrollLeft)
    const before = scrolls && passed > 1
    const after = scrolls && passed < hidden - 1

    frame.classList.toggle('is-more-left', rtl ? after : before)
    frame.classList.toggle('is-more-right', rtl ? before : after)
    if (scrolls) scroller.setAttribute('tabindex', '0')
    else scroller.removeAttribute('tabindex')
  }

  const sizes = new ResizeObserver(update)
  sizes.observe(scroller)
  const inner = scroller.querySelector('table')
  if (inner) sizes.observe(inner)
  scroller.addEventListener('scroll', update, { passive: true })
  update()

  return () => {
    sizes.disconnect()
    scroller.removeEventListener('scroll', update)
    frame.classList.remove('is-more-left', 'is-more-right')
    scroller.setAttribute('tabindex', '0')
  }
}

webx.widget?.('table', '[data-webx-table]', table)
