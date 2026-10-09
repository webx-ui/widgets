/*
 * `[data-webx-mobile-menu="<id>"]` — the shell of `<x-webx-mobile-menu>` (spec §6.1) on top of
 * the dialog behaviour, which already opens the panel modal, traps focus, locks the page's
 * scroll and gives focus back to the trigger. What this adds is the phone's part:
 *
 * - a link inside closes the menu, a same-page anchor included — the page would otherwise
 *   scroll under a menu still covering it;
 * - Back closes the menu instead of leaving the page: opening adds an entry to the history,
 *   closing any other way takes it off again (`data-history="false"` leaves history alone);
 * - a swipe toward the side the panel came from closes it.
 *
 * Open and closed are read off the `open` attribute, as the dialog behaviour does: the `close`
 * event is queued behind rendering and a hidden tab never gets it.
 */

import { listeners } from '../core.js'

const SWIPE = 64

export function mobileMenu(root) {
  const id = root.getAttribute('data-webx-mobile-menu')
  const panel = id ? document.getElementById(id) : null
  if (!(panel instanceof HTMLDialogElement)) return

  const trigger = document.getElementById(`${id}-trigger`)
  const side = root.getAttribute('data-side') ?? 'right'
  const closeOnNavigate = root.getAttribute('data-close-on-navigate') !== 'false'
  const useHistory = root.getAttribute('data-history') !== 'false'
  const on = listeners()

  // Our entry is on top of the history while the menu is open; `then` runs once it is gone.
  let entry = false
  let then = null

  if (trigger) {
    trigger.setAttribute('role', 'button')
    trigger.setAttribute('aria-expanded', String(panel.open))
  }

  const opened = () => {
    trigger?.setAttribute('aria-expanded', 'true')
    trigger?.classList.add('is-open')
    if (useHistory && !entry) {
      history.pushState({ ...(history.state ?? {}), webxMenu: id }, '')
      entry = true
    }
  }

  const closed = () => {
    trigger?.setAttribute('aria-expanded', 'false')
    trigger?.classList.remove('is-open')
    if (entry) {
      entry = false
      history.back()
    } else {
      run()
    }
  }

  const run = () => {
    const next = then
    then = null
    next?.()
  }

  const observer = new MutationObserver(() => (panel.open ? opened() : closed()))
  observer.observe(panel, { attributes: true, attributeFilter: ['open'] })
  if (panel.open) opened()

  on(window, 'popstate', () => {
    if (entry) {
      // Back while the menu is open: the entry is already gone, so closing must not go back again.
      entry = false
      panel.close()
      return
    }
    run()
  })

  on(panel, 'click', (event) => {
    if (!closeOnNavigate || event.defaultPrevented || event.button !== 0) return
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

    const link = event.target instanceof Element ? event.target.closest('a[href]') : null
    if (!link || !panel.contains(link) || link.hasAttribute('data-webx-dialog-close')) return
    if (link.target && link.target !== '_self') return
    if (link.hasAttribute('download')) return

    if (!entry) {
      // Nothing to take off the history: close and let the link do what it does.
      panel.close()
      return
    }

    // Go where the link points once our entry is off the history, or the new page would sit on
    // top of it and Back from there would land on the menu's entry first.
    event.preventDefault()
    const url = new URL(link.href, location.href)
    then = () => {
      const samePage =
        url.origin === location.origin &&
        url.pathname === location.pathname &&
        url.search === location.search
      if (samePage && url.hash && url.hash === location.hash) {
        document.getElementById(decodeURIComponent(url.hash.slice(1)))?.scrollIntoView()
      } else {
        location.assign(url.href)
      }
    }
    panel.close()
  })

  // A swipe toward the side the panel came from closes it, the panel following the finger.
  const axis = side === 'top' || side === 'bottom' ? 'y' : 'x'
  const sign = side === 'left' || side === 'top' ? -1 : 1
  let start = null

  const reset = () => {
    start = null
    panel.style.removeProperty('transform')
    panel.style.removeProperty('transition')
  }

  if (side !== 'full') {
    on(panel, 'pointerdown', (event) => {
      if (event.pointerType === 'mouse' || !panel.open) return
      const body = panel.querySelector('.webx-mobile-menu__body')
      // Up and down inside the body is the body's own scroll.
      if (axis === 'y' && body?.contains(event.target) && body.scrollTop > 0) return
      start = { x: event.clientX, y: event.clientY }
    })
    on(panel, 'pointermove', (event) => {
      if (!start) return
      const along = (axis === 'x' ? event.clientX - start.x : event.clientY - start.y) * sign
      const across = Math.abs(axis === 'x' ? event.clientY - start.y : event.clientX - start.x)
      if (across > Math.abs(along) && across > 12) return reset()
      if (along <= 0) return panel.style.removeProperty('transform')
      panel.style.transition = 'none'
      panel.style.transform = `translate${axis.toUpperCase()}(${along * sign}px)`
    })
    on(panel, 'pointerup', (event) => {
      if (!start) return
      const along = (axis === 'x' ? event.clientX - start.x : event.clientY - start.y) * sign
      reset()
      if (along > SWIPE) panel.close()
    })
    on(panel, 'pointercancel', reset)
  }

  return () => {
    on.off()
    observer.disconnect()
    reset()
    trigger?.removeAttribute('role')
    trigger?.removeAttribute('aria-expanded')
    trigger?.classList.remove('is-open')
  }
}
