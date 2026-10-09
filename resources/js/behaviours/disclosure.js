/*
 * `<button data-webx-disclosure="<id>">` (or `aria-controls`) shows and hides the element with
 * that id: header menus, dropdowns. Esc closes it and gives focus back to the button; so does a
 * click anywhere outside the two.
 *
 * Without JavaScript the panel is simply shown — the runtime is what hides it — so markup should
 * not carry `hidden` on it.
 */

import { listeners } from '../core.js'

export function disclosure(button) {
  const id = button.getAttribute('data-webx-disclosure') || button.getAttribute('aria-controls')
  const panel = id ? document.getElementById(id) : null
  if (!panel) return

  const on = listeners()
  const isOpen = () => button.getAttribute('aria-expanded') === 'true'
  const set = (open) => {
    button.setAttribute('aria-expanded', String(open))
    button.classList.toggle('is-open', open)
    panel.classList.toggle('is-open', open)
    panel.hidden = !open
  }

  button.setAttribute('aria-controls', id)
  set(isOpen())

  on(button, 'click', (event) => {
    event.preventDefault()
    set(!isOpen())
  })
  on(document, 'keydown', (event) => {
    if (event.key !== 'Escape' || !isOpen()) return
    const inside = panel.contains(document.activeElement) || button === document.activeElement
    set(false)
    if (inside) button.focus()
  })
  on(document, 'click', (event) => {
    if (isOpen() && !panel.contains(event.target) && !button.contains(event.target)) set(false)
  })

  return () => {
    on.off()
    button.removeAttribute('aria-expanded')
    button.classList.remove('is-open')
    panel.classList.remove('is-open')
    panel.hidden = false
  }
}
