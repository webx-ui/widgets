/*
 * `data-webx-dialog="<id>"` on a link or a button opens `<dialog id="<id>">` as a modal: the page
 * behind it goes inert, so focus stays inside; Esc, a click on the backdrop and any
 * `[data-webx-dialog-close]` inside close it; focus goes back to the opener. The page does not
 * scroll under it and does not jump either — `webx-scroll-locked` keeps the scrollbar's gutter.
 *
 * Without JavaScript the opener is a link to `#<id>` and the stylesheet shows the dialog
 * through `:target`, until `.webx-js` on <html> says the runtime is here.
 *
 * `webx-dialog--sheet`: while the window is narrower than 40rem the open dialog is `is-sheet`, a
 * sheet from the bottom (dialog.css). Decided here and not by a media query: a modal lies over the
 * viewport, outside every container of the page, and the stylesheets of a theme know only
 * container queries (THEMES §8). A turned phone turns the open dialog with it.
 *
 * `webx-dialog--page`: the dialog is the whole window and scrolls with its backdrop, its box
 * `.webx-dialog__window` inside. A click on the dialog outside that box closes it — the box, not
 * the dialog's own rectangle, which is the window here; a press that began inside the box (a
 * selection dragged out of it) is no click outside. Narrow, it is `is-narrow` (under the header).
 */

import { listeners } from '../core.js'

const prepared = new WeakSet()
const openers = new WeakMap()
const open = new Set()

const lock = () => document.documentElement.classList.toggle('webx-scroll-locked', open.size > 0)

const NARROW = '(width < 40rem)'
let narrow = null

const shape = (dialog) => {
  const small = narrow?.matches ?? false
  // A dialog scrolled as a page has a box of its own: a sheet it is not.
  const page = dialog.classList.contains('webx-dialog--page')
  dialog.classList.toggle(
    'is-sheet',
    dialog.classList.contains('webx-dialog--sheet') && !page && small,
  )
  dialog.classList.toggle('is-narrow', page && small)
}

/** The box of a dialog drawn as a page; null for one that is its own box. */
const windowOf = (dialog) =>
  dialog.classList.contains('webx-dialog--page')
    ? (Array.from(dialog.children).find((el) => el.classList.contains('webx-dialog__window')) ??
      null)
    : null

/**
 * Whether an event happened inside a node: by the path the event took when it was dispatched,
 * which keeps the ancestors of a target that has since left the document.
 */
const within = (event, node) => {
  const path = typeof event.composedPath === 'function' ? event.composedPath() : []
  if (path.length > 0) return path.includes(node)
  return event.target instanceof Node && node.contains(event.target)
}

function watchWidth() {
  if (narrow || typeof window.matchMedia !== 'function') return
  narrow = window.matchMedia(NARROW)
  narrow.addEventListener?.('change', () => {
    for (const dialog of open) shape(dialog)
  })
}

function prepare(dialog) {
  if (prepared.has(dialog)) return
  prepared.add(dialog)

  // Whether the press began inside the box: a selection dragged out of it ends with a click on
  // the backdrop. Decided at the press and by the event path, not by the node later: a calendar
  // that redraws its days on the click takes the pressed day out of the document before the
  // click reaches the dialog, and a detached node is in no box.
  let pressed = null
  dialog.addEventListener('pointerdown', (event) => {
    const box = windowOf(dialog)
    pressed = box ? within(event, box) : null
  })

  dialog.addEventListener('click', (event) => {
    const close =
      event.target instanceof Element ? event.target.closest('[data-webx-dialog-close]') : null
    if (close && dialog.contains(close)) {
      event.preventDefault()
      dialog.close()
      return
    }

    const began = pressed
    pressed = null
    const box = windowOf(dialog)
    if (box) {
      if (!within(event, box) && began !== true) dialog.close()
      return
    }

    // A click on the dialog element itself outside its box is a click on the backdrop.
    if (event.target !== dialog) return
    const rect = dialog.getBoundingClientRect()
    const inside =
      event.clientX >= rect.left &&
      event.clientX <= rect.right &&
      event.clientY >= rect.top &&
      event.clientY <= rect.bottom
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
  watchWidth()
  shape(dialog)
  dialog.showModal()
  // Scrolled as a page, it opens at its top, wherever it was left the last time.
  if (dialog.classList.contains('webx-dialog--page')) dialog.scrollTop = 0
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
