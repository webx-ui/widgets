/*
 * `data-webx-dialog="<id>"` on a link or a button opens `<dialog id="<id>">` as a modal: the page
 * behind it goes inert, so focus stays inside; Esc, a click on the backdrop and any
 * `[data-webx-dialog-close]` inside close it; focus goes back to the opener. The page does not
 * scroll under it and does not jump either — `webx-scroll-locked` keeps the scrollbar's gutter.
 *
 * Without JavaScript the opener is a link to `#<id>` and the stylesheet shows the dialog
 * through `:target`, until `.webx-js` on <html> says the runtime is here.
 */

import { listeners } from '../core.js'

const prepared = new WeakSet()
const openers = new WeakMap()
const open = new Set()

const lock = () => document.documentElement.classList.toggle('webx-scroll-locked', open.size > 0)

function prepare(dialog) {
  if (prepared.has(dialog)) return
  prepared.add(dialog)

  dialog.addEventListener('click', (event) => {
    const close =
      event.target instanceof Element ? event.target.closest('[data-webx-dialog-close]') : null
    if (close && dialog.contains(close)) {
      event.preventDefault()
      dialog.close()
      return
    }

    // A click on the dialog element itself outside its box is a click on the backdrop.
    if (event.target !== dialog) return
    const box = dialog.getBoundingClientRect()
    const inside =
      event.clientX >= box.left &&
      event.clientX <= box.right &&
      event.clientY >= box.top &&
      event.clientY <= box.bottom
    if (!inside) dialog.close()
  })

  // Watched on the `open` attribute rather than the `close` event: the event is queued behind
  // rendering, and a page in a background tab never gets it — its scroll would stay locked.
  // Every way out (Esc, a form with method="dialog", close()) drops the attribute.
  new MutationObserver(() => {
    if (dialog.open || !open.has(dialog)) return
    open.delete(dialog)
    lock()
    openers.get(dialog)?.focus()
    openers.delete(dialog)
  }).observe(dialog, { attributes: true, attributeFilter: ['open'] })
}

export function openDialog(dialog, opener = null) {
  if (!(dialog instanceof HTMLDialogElement) || dialog.open) return
  prepare(dialog)
  if (opener) openers.set(dialog, opener)
  dialog.showModal()
  open.add(dialog)
  lock()
}

export function dialog(opener) {
  const on = listeners()

  on(opener, 'click', (event) => {
    const target = document.getElementById(opener.getAttribute('data-webx-dialog') ?? '')
    if (!(target instanceof HTMLDialogElement)) return
    event.preventDefault()
    openDialog(target, opener)
  })

  return on.off
}
