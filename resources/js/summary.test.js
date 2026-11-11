import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  vi.restoreAllMocks()
})

async function start(html) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./form.js')
  await import('./steps.js')
  await import('./summary.js')
  return document.querySelector('form')
}

const settle = async () => {
  for (let i = 0; i < 5; i++) await new Promise((resolve) => setTimeout(resolve, 0))
}

const field = (key, type, label, control) => `
  <div class="webx-field webx-field--${type}" data-webx-field="${key}">
    <label class="webx-field__label" for="${key}">${label}<span class="webx-field__required" aria-hidden="true">*</span></label>
    ${control}
    <p class="webx-field__error" hidden></p>
  </div>`

const input = (key, label, type = 'text') =>
  field(key, type, label, `<input type="${type}" id="${key}" name="${key}">`)

const choices = (key, label, options, attributes = '') => `
  <fieldset class="webx-field webx-field--radio webx-field--cards" data-webx-field="${key}" ${attributes}>
    <legend class="webx-field__label webx-field__label--legend">${label}</legend>
    <div class="webx-field__choices">
      ${options
        .map(
          ([value, words]) =>
            `<label class="webx-field__card"><input type="radio" name="${key}" value="${value}"><span class="webx-field__card-title">${words}</span><span class="webx-field__card-text">More words</span></label>`,
        )
        .join('')}
    </div>
    <p class="webx-field__error" hidden></p>
  </fieldset>`

const summary = (names = null) => `
  <section class="webx-form-summary" data-webx-summary data-webx-summary-edit="Edit"
    data-webx-summary-field="Edit: :field"
    ${names ? `data-webx-summary-fields="${JSON.stringify(names).replace(/"/g, '&quot;')}"` : ''} hidden>
    <p class="webx-form-summary__title">Your request</p>
    <dl class="webx-form-summary__list"></dl>
  </section>`

const step = (name, title, fields) => `
  <fieldset class="webx-form-step" data-webx-step="${name}" data-webx-step-label="${title}">
    <legend class="webx-form-step__legend">
      <span class="webx-form-step__count" hidden></span>
      <span class="webx-form-step__title" role="heading" aria-level="3" tabindex="-1">${title}</span>
    </legend>
    <div class="webx-form-step__fields">${fields}</div>
    <div class="webx-form-step__nav" hidden>
      <button type="button" data-webx-step-back>Back</button>
      <button type="button" data-webx-step-next>Next</button>
    </div>
  </fieldset>`

const purpose = choices('purpose', 'Purpose', [
  ['agency', 'Agency meeting'],
  ['direct', 'Direct inquiry'],
])

const stepped = (summaryHtml) => `
  <form class="webx-form webx-form--steps" action="/send" method="post" data-webx-send
    data-webx-steps-count="Step :current of :total">
    <div class="webx-form__body">
      <ol class="webx-form-steps" hidden></ol>
      ${step('first', 'Purpose', purpose)}
      ${step('contacts', 'Your details', summaryHtml + input('name', 'Your name') + field('consent', 'consent', 'I agree', '<input type="checkbox" name="consent" value="1">'))}
      <button type="submit">Send</button>
    </div>
    <div class="webx-form__error" role="alert"></div>
  </form>`

const rows = () =>
  Array.from(document.querySelectorAll('.webx-form-summary__row')).map((row) => [
    row.querySelector('dt').textContent,
    row.querySelector('.webx-form-summary__value').textContent,
  ])

const choose = (form, name, value) => {
  const radio = form.querySelector(`input[name="${name}"][value="${value}"]`)
  radio.checked = true
  radio.dispatchEvent(new Event('change', { bubbles: true }))
}

describe('the summary of a form', () => {
  it('stays hidden while nothing is answered — and as the server printed it, without the script', async () => {
    await start(stepped(summary()))
    await settle()

    const box = document.querySelector('[data-webx-summary]')
    expect(box.hidden).toBe(true)
    expect(box.querySelectorAll('.webx-form-summary__row')).toHaveLength(0)
  })

  it('is built from the current values, with the words of the choices rather than their values', async () => {
    const form = await start(stepped(summary()))
    choose(form, 'purpose', 'agency')
    await settle()

    expect(rows()).toEqual([['Purpose', 'Agency meeting']])
    expect(document.querySelector('[data-webx-summary]').hidden).toBe(false)

    // A change made later follows: the summary is never behind the form.
    choose(form, 'purpose', 'direct')
    await settle()
    expect(rows()).toEqual([['Purpose', 'Direct inquiry']])
  })

  it('shows the fields above it only — not the name typed below, not the consent', async () => {
    const form = await start(stepped(summary()))
    choose(form, 'purpose', 'agency')
    const name = form.querySelector('input[name="name"]')
    name.value = 'Alex Doe'
    name.dispatchEvent(new Event('input', { bubbles: true }))
    form.querySelector('input[name="consent"]').checked = true
    await settle()

    expect(rows()).toEqual([['Purpose', 'Agency meeting']])
  })

  it('shows the named fields, in the order of the form, and leaves out one a condition hides', async () => {
    const html = `
      <form class="webx-form" action="/send" method="post" data-webx-send>
        <div class="webx-form__body">
          ${purpose}
          ${input('fields.day', 'Day')}
          ${choices('fields.room', 'Room', [['a', 'Room A']], 'data-webx-when="{&quot;purpose&quot;:[&quot;agency&quot;]}"')}
          ${summary(['fields.room', 'purpose'])}
        </div>
      </form>`
    const form = await start(html)
    choose(form, 'purpose', 'agency')
    await settle()
    choose(form, 'fields.room', 'a')
    const day = form.querySelector('input[name="fields.day"]')
    day.value = 'Monday'
    day.dispatchEvent(new Event('input', { bubbles: true }))
    await settle()

    expect(rows()).toEqual([
      ['Purpose', 'Agency meeting'],
      ['Room', 'Room A'],
    ])

    choose(form, 'purpose', 'direct')
    await settle()
    expect(rows()).toEqual([['Purpose', 'Direct inquiry']])
  })

  it('«Edit» brings back the step of the field, the focus on it, without a word in the alert', async () => {
    const form = await start(stepped(summary()))
    choose(form, 'purpose', 'agency')
    form.querySelector('[data-webx-step="first"] [data-webx-step-next]').click()
    await settle()

    const first = form.querySelector('[data-webx-step="first"]')
    expect(first.hidden).toBe(true)
    const edit = document.querySelector('.webx-form-summary__edit')
    expect(edit.textContent).toBe('Edit')
    expect(edit.getAttribute('aria-label')).toBe('Edit: Purpose')

    edit.click()
    await settle()

    expect(first.hidden).toBe(false)
    expect(document.activeElement).toBe(form.querySelector('input[name="purpose"]'))
    expect(form.querySelector('.webx-form__error').textContent.trim()).toBe('')
  })

  it('unmounts to the page as printed', async () => {
    const form = await start(stepped(summary()))
    choose(form, 'purpose', 'agency')
    await settle()

    window.webx.unmount()
    const box = document.querySelector('[data-webx-summary]')
    expect(box.hidden).toBe(true)
    expect(box.querySelector('.webx-form-summary__list').children).toHaveLength(0)
  })
})
