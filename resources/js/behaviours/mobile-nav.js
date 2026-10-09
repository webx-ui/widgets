/*
 * `[data-webx-mobile-nav="drill"]` — a branch opens over its parent rather than under it (spec
 * §6.1). Every branch is a <details>, so the markup already opens and closes; this lays an open
 * branch over the level it came from (the stylesheet, `is-drilled` on that level's list), moves
 * focus to the branch's Back button and back to the branch's name when Back closes it. A branch
 * open from the start fires its toggle inside a closed dialog, where focus goes nowhere.
 *
 * Without JavaScript Back is not shown and the branches open in place, as in `accordion`.
 */

import { listeners } from '../core.js'

const listOf = (details) => details.parentElement?.closest('.webx-mobile-nav__list') ?? null

export function mobileNav(nav) {
  if (nav.getAttribute('data-webx-mobile-nav') !== 'drill') return

  const on = listeners()
  const groups = () => Array.from(nav.querySelectorAll('details.webx-mobile-nav__group'))

  const sync = () => {
    for (const list of nav.querySelectorAll('.webx-mobile-nav__list')) {
      const drilled = Array.from(list.children).some((item) =>
        item.querySelector(':scope > details[open]'),
      )
      list.classList.toggle('is-drilled', drilled)
    }
  }

  // A branch opened by hand closes its open siblings: one path is drilled into at a time.
  on(
    nav,
    'toggle',
    (event) => {
      const details = event.target
      if (!(details instanceof HTMLDetailsElement)) return
      if (details.open) {
        for (const other of groups()) {
          if (other !== details && other.open && listOf(other) === listOf(details))
            other.open = false
        }
      }
      sync()
      if (details.open) {
        details.querySelector(':scope > .webx-mobile-nav__level > .webx-mobile-nav__back')?.focus()
        nav.closest('.webx-mobile-menu__body')?.scrollTo?.({ top: 0 })
      }
    },
    true,
  )

  on(nav, 'click', (event) => {
    const back =
      event.target instanceof Element ? event.target.closest('[data-webx-mobile-nav-back]') : null
    if (!back || !nav.contains(back)) return
    const details = back.closest('details')
    if (!details) return
    details.open = false
    details.querySelector(':scope > summary')?.focus()
  })

  // The visitor's branch is open from the start: drilled into, without stealing focus.
  sync()

  return () => {
    on.off()
    for (const list of nav.querySelectorAll('.is-drilled')) list.classList.remove('is-drilled')
  }
}
