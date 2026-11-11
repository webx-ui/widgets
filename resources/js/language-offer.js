/*
 * The offer of the first visit's language beside the switcher (ROUTING §18.6). Its own file,
 * claimed by `<x-webx-language-switcher>` only on a page the server decided to offer it on.
 *
 * The server prints it `hidden`: without this script a refusal could not be kept, and an offer
 * nobody can put away is worse than none. Shown, it lies over the page — nothing below it moves.
 * It does not take the focus: it is a suggestion, next in the order of Tab after the switcher.
 *
 * One offer a page, beside a switcher somebody can see. The server prints it with the first
 * switcher, but a theme may print that markup twice — theme-default's header copies its actions
 * into the mobile menu — so the first copy whose switcher comes on screen is shown, at once or
 * when the folded menu opens, and the other copies leave the page (they share an id).
 *
 * "Stay", the cross and Esc decline it: the cookie (its name is the attribute's value) is written
 * for as long as the first visit's own, and the server offers nothing again. "Yes" is a plain link
 * to the same page in that language, where there is nothing to offer.
 */

import '../css/language-offer.css'

const webx = (window.webx ??= {})

function decline(name, seconds) {
  const secure = location.protocol === 'https:' ? '; Secure' : ''
  document.cookie = `${name}=1; Max-Age=${seconds}; Path=/; SameSite=Lax${secure}`
}

export function languageOffer(root) {
  const name = root.getAttribute('data-webx-language-offer') || ''
  if (!/^[A-Za-z0-9_-]+$/.test(name)) return

  const seconds = Number(root.getAttribute('data-webx-language-offer-lifetime')) || 31536000
  const switcher = root.parentElement
  const buttons = root.querySelectorAll('.webx-language-offer__stay, .webx-language-offer__close')
  const copies = () =>
    [...document.querySelectorAll('[data-webx-language-offer]')].filter((o) => o !== root)
  let shown = false

  const close = () => {
    const focused = root.contains(document.activeElement)
    decline(name, seconds)
    root.hidden = true
    // The keyboard goes on from the switcher the offer stood beside: its first control.
    if (focused) {
      const controls = switcher?.querySelectorAll('summary, button, a[href]') ?? []
      ;[...controls].find((control) => !root.contains(control))?.focus()
    }
    root.dispatchEvent(new CustomEvent('webx:language-offer', { bubbles: true }))
  }

  // Esc closes the innermost layer only: while a dropdown or a dialog is open, it is theirs. Read
  // in the capture phase, before their own listeners close them.
  const escape = (event) => {
    if (event.key !== 'Escape' || event.defaultPrevented || root.hidden) return
    if (
      document.querySelector(
        '.webx-dropdown.is-open, dialog[open], [aria-modal="true"]:not([hidden])',
      )
    )
      return
    close()
  }

  // Shown at the first switcher that is on screen — now, or when a folded menu opens; a copy
  // shown first, elsewhere, takes this one off the page.
  const show = () => {
    if (shown || !root.isConnected || !switcher?.getClientRects().length) return
    if (copies().some((copy) => !copy.hidden)) {
      root.remove()
      return
    }
    shown = true
    copies().forEach((copy) => copy.remove())
    observer?.disconnect()
    buttons.forEach((button) => button.addEventListener('click', close))
    document.addEventListener('keydown', escape, true)
    root.hidden = false
    // At the bottom of a folded menu there is no room below the switcher: above it, then.
    const box = root.getBoundingClientRect()
    root.classList.toggle('is-above', box.bottom > innerHeight && box.top - box.height > 0)
  }

  const observer =
    typeof ResizeObserver === 'function' && switcher ? new ResizeObserver(show) : null
  if (observer) observer.observe(switcher)
  show()

  return () => {
    observer?.disconnect()
    buttons.forEach((button) => button.removeEventListener('click', close))
    document.removeEventListener('keydown', escape, true)
    root.hidden = true
  }
}

webx.widget?.('language-offer', '[data-webx-language-offer]', languageOffer)
