/*
 * A form of module-inbox in a dialog (spec §6.4).
 *
 * `formOpener` — anything with `data-webx-form="<slug>"` (but the form itself, which carries the
 * same attribute for the module's script), or a link to `#webx-form-<slug>`, opens
 * `<dialog id="webx-form-<slug>">` the way any dialog of the runtime opens: modal, focus trapped
 * and given back. `data-webx-form-value-<field>="…"` on the opener fills the form's field
 * `fields[<field>]` — "Book" from a service's page arrives with the service — and the next
 * opener without it gets the field back as it was printed.
 *
 * `formDialog` — the dialog. The module's script shows the thank-you in place; here, once it
 * shows, the rest of the form steps aside and a button that closes the dialog takes its place
 * (`is-sent`): it never closes by itself, the visitor has to have time to read. Closed, the
 * dialog is a fresh form again. A dialog printed open — the page came back from it without
 * JavaScript, with its answer — is opened again as a modal.
 */

import { listeners } from '../core.js'
import { openDialog } from './dialog.js'

const modal = (dialog) => {
  try {
    return dialog.matches(':modal')
  } catch {
    return false
  }
}

const ID = 'webx-form-'

/** The value each field had before an opener changed it. */
const printed = new WeakMap()

const dialogOf = (opener) => {
  const slug =
    opener.getAttribute('data-webx-form') ||
    (opener.getAttribute('href') ?? '').replace(/^#webx-form-/, '')
  const dialog = slug ? document.getElementById(ID + slug) : null
  return dialog instanceof HTMLDialogElement ? dialog : null
}

function fill(dialog, opener) {
  const form = dialog.querySelector('form')
  if (!form) return

  for (const [input, value] of printed.get(dialog) ?? []) input.value = value
  const changed = new Map()

  for (const { name, value } of Array.from(opener.attributes)) {
    if (!name.startsWith('data-webx-form-value-')) continue
    const field = name.slice('data-webx-form-value-'.length)
    const input = form.elements.namedItem(`fields[${field}]`)
    if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement)) continue
    if (!changed.has(input)) changed.set(input, input.value)
    input.value = value
  }
  printed.set(dialog, changed)
}

export function formOpener(opener) {
  if (opener instanceof HTMLFormElement) return
  const on = listeners()

  on(opener, 'click', (event) => {
    const dialog = dialogOf(opener)
    // No dialog for it on the page — no such form, or switched off: the link goes where it goes.
    if (!dialog) return
    event.preventDefault()
    fill(dialog, opener)
    openDialog(dialog, opener)
  })

  return on.off
}

export function formDialog(dialog) {
  if (!(dialog instanceof HTMLDialogElement)) return
  const form = dialog.querySelector('form')
  const message = form?.querySelector('[data-webx-message]')
  const done = dialog.querySelector('.webx-form-dialog__done')
  if (!form || !message) return

  let aside = []

  const sent = () => {
    if (dialog.classList.contains('is-sent')) return
    dialog.classList.add('is-sent')
    // Everything of the form but the thank-you and what is hidden anyway.
    aside = Array.from(form.children).filter(
      (el) =>
        el !== message &&
        !el.hidden &&
        !(el instanceof HTMLInputElement) &&
        el.tagName !== 'SCRIPT',
    )
    for (const el of aside) el.hidden = true
    if (done) {
      done.hidden = false
      done.focus()
    }
  }

  const fresh = () => {
    if (!dialog.classList.contains('is-sent')) return
    dialog.classList.remove('is-sent')
    for (const el of aside) el.hidden = false
    aside = []
    message.hidden = true
    if (done) done.hidden = true
  }

  // The module's script shows the thank-you by dropping `hidden`; a refusal keeps the form.
  const shown = new MutationObserver(() => {
    if (!message.hidden) sent()
  })
  shown.observe(message, { attributes: true, attributeFilter: ['hidden'] })

  const closed = new MutationObserver(() => {
    if (!dialog.open) fresh()
  })
  closed.observe(dialog, { attributes: true, attributeFilter: ['open'] })

  // Printed open: the page came back from it. As a modal, like any other time it opens.
  if (dialog.open && !modal(dialog)) {
    dialog.close()
    openDialog(dialog)
    if (!message.hidden) sent()
  }

  return () => {
    shown.disconnect()
    closed.disconnect()
    fresh()
  }
}
