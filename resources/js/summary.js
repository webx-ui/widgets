/*
 * The summary of a form (FORM_STEPS §7): the answers given so far, each with «Edit» that brings its
 * field back — in a form of steps, that field's step, the focus on the field. Its own file, claimed
 * by `<x-webx-form.summary>`: a form without one never loads it.
 *
 * Built from the form as it stands — on mount, on every change of a field, when a step comes and
 * after a reset — so it is never behind what was typed. What it shows:
 *
 *  - the fields `data-webx-summary-fields` names, or every field above the summary;
 *  - only the ones shown (a condition, a step out of the way) and answered;
 *  - never a consent, a file, a hidden field, a password or a single tick;
 *  - the label of the field, and for a choice the words of the choice, not its value.
 *
 * Without the script the summary stays hidden: every answer is on the page above it. With nothing to
 * show, it stays hidden too.
 */

import '../css/summary.css'

const webx = (window.webx ??= {})

const SKIPPED = ['consent', 'file', 'hidden', 'password']

/** The words of a label: without the asterisk and «(optional)» the field carries beside them. */
const words = (el) => {
  if (!el) return ''
  const copy = el.cloneNode(true)
  copy
    .querySelectorAll('.webx-field__required, .webx-field__optional')
    .forEach((one) => one.remove())
  return copy.textContent.replace(/\s+/g, ' ').trim()
}

/** What a field holds, as the visitor reads it: the words of the choices, the text typed. */
function answerOf(field) {
  // A field a script draws over its controls says its answer itself: a day and a time (datetime.js).
  if (field.dataset.webxAnswer) return field.dataset.webxAnswer
  const said = []
  for (const control of field.querySelectorAll('input, select, textarea')) {
    if (control.disabled || control.type === 'hidden' || control.type === 'password') continue
    if (control instanceof HTMLSelectElement) {
      for (const option of control.selectedOptions)
        if (option.value !== '') said.push(words(option))
    } else if (control.type === 'radio' || control.type === 'checkbox') {
      if (!control.checked) continue
      const label = control.closest('label') ?? control.labels?.[0]
      said.push(
        words(
          label?.querySelector('.webx-field__card-title, .webx-field__choice-label') ?? label,
        ) || control.value,
      )
    } else if (control.type !== 'file' && control.value.trim() !== '') {
      said.push(control.value.trim())
    }
  }
  return said.filter((one) => one !== '').join(', ')
}

const typeOf = (field) =>
  Array.from(field.classList)
    .find((name) => name.startsWith('webx-field--') && name !== 'webx-field--cards')
    ?.slice('webx-field--'.length)

export function summary(el) {
  const form = el.closest('form')
  const list = el.querySelector('.webx-form-summary__list')
  if (!form || !list) return undefined

  let named = null
  try {
    const parsed = JSON.parse(el.getAttribute('data-webx-summary-fields') ?? 'null')
    if (Array.isArray(parsed) && parsed.length) named = new Set(parsed.map(String))
  } catch {
    named = null
  }
  const edit = el.getAttribute('data-webx-summary-edit') ?? 'Edit'
  const nameOf = el.getAttribute('data-webx-summary-field') ?? 'Edit: :field'

  const fieldsOf = () =>
    Array.from(form.querySelectorAll('[data-webx-field]')).filter((field) => {
      if (field.closest('form') !== form || el.contains(field)) return false
      if (field.closest('[data-webx-hidden]')) return false
      const type = typeOf(field)
      if (SKIPPED.includes(type)) return false
      // A single tick says yes or no to its own label: nothing a summary would add.
      if (type === 'checkbox' && !field.querySelector('.webx-field__choices')) return false
      if (named) return named.has(field.getAttribute('data-webx-field'))
      return Boolean(el.compareDocumentPosition(field) & Node.DOCUMENT_POSITION_PRECEDING)
    })

  /** Its step first, when it is out of sight (steps.js), then the focus on its first control. */
  const bring = (field) => {
    const step = field.closest('[data-webx-step]')
    if (step?.hidden)
      step.dispatchEvent(
        new CustomEvent('webx:form:reveal', { bubbles: true, detail: { field, edit: true } }),
      )
    const control =
      field.querySelector('[data-webx-focus]') ??
      Array.from(field.querySelectorAll('input, select, textarea')).find(
        (one) => one.type !== 'hidden' && !one.disabled,
      )
    control?.focus({ preventScroll: true })
    control?.scrollIntoView?.({ block: 'center' })
  }

  const draw = () => {
    const rows = []
    for (const field of fieldsOf()) {
      const answer = answerOf(field)
      if (answer === '') continue
      const label = words(field.querySelector('.webx-field__label')) || field.dataset.webxField
      const row = document.createElement('div')
      row.className = 'webx-form-summary__row'
      const term = document.createElement('dt')
      term.className = 'webx-form-summary__term'
      term.textContent = label
      const value = document.createElement('dd')
      value.className = 'webx-form-summary__value'
      value.textContent = answer
      const actions = document.createElement('dd')
      actions.className = 'webx-form-summary__actions'
      const button = document.createElement('button')
      button.type = 'button'
      button.className = 'webx-form-summary__edit'
      button.textContent = edit
      button.setAttribute('aria-label', nameOf.split(':field').join(label))
      button.addEventListener('click', () => bring(field))
      actions.append(button)
      row.append(term, value, actions)
      rows.push(row)
    }
    list.replaceChildren(...rows)
    el.hidden = rows.length === 0
  }

  const later = () => queueMicrotask(draw)
  // A beat later each: the conditions (form.js) answer the same change, the step is changed after its
  // event, the reset is read after it.
  form.addEventListener('input', later)
  form.addEventListener('change', later)
  form.addEventListener('webx:form:step', later)
  form.addEventListener('reset', later)
  draw()

  return () => {
    form.removeEventListener('input', later)
    form.removeEventListener('change', later)
    form.removeEventListener('webx:form:step', later)
    form.removeEventListener('reset', later)
    list.replaceChildren()
    el.hidden = true
  }
}

webx.widget?.('summary', '[data-webx-summary]', summary)
