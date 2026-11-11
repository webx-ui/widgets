import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  vi.restoreAllMocks()
  delete globalThis.fetch
})

async function start(html) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./form.js')
  await import('./steps.js')
  return document.querySelector('form')
}

const settle = async () => {
  for (let i = 0; i < 10; i++) await new Promise((resolve) => setTimeout(resolve, 0))
}

const json = (status, data) =>
  new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } })

function answers(...list) {
  const calls = []
  globalThis.fetch = vi.fn(async (url, init = {}) => {
    calls.push({ url: String(url), body: init.body instanceof FormData ? init.body : null })
    const next = list.shift()
    return typeof next === 'function' ? next() : next
  })
  return calls
}

const required = (label) =>
  JSON.stringify([{ rule: 'required', message: `The ${label} field is required.` }]).replace(
    /"/g,
    '&quot;',
  )

const text = (key, id) => `
  <div class="webx-field" data-webx-field="${key}" data-webx-rules="${required(id)}">
    <label for="${id}">${id}</label>
    <input type="text" id="${id}" name="${key.replace(/\.([^.]+)/g, '[$1]')}">
    <p class="webx-field__error" hidden></p>
  </div>`

const radios = (key, values) => `
  <fieldset class="webx-field" data-webx-field="${key}" data-webx-rules="${required(key)}">
    <legend>${key}</legend>
    ${values.map((value) => `<label><input type="radio" name="${key.replace(/\.([^.]+)/g, '[$1]')}" value="${value}"> ${value}</label>`).join('')}
    <p class="webx-field__error" hidden></p>
  </fieldset>`

const step = (name, title, fields, attributes = '') => `
  <fieldset class="webx-form-step" id="step-${name}" data-webx-step="${name}" data-webx-step-label="${title}" ${attributes}>
    <legend class="webx-form-step__legend">
      <span class="webx-form-step__count" hidden></span>
      <span class="webx-form-step__title" role="heading" aria-level="3" tabindex="-1">${title}</span>
    </legend>
    <div class="webx-form-step__fields">${fields}</div>
    <div class="webx-form-step__nav" hidden>
      <button type="button" class="webx-form-step__back" data-webx-step-back>Back</button>
      <button type="button" class="webx-form-step__next" data-webx-step-next>Next</button>
    </div>
  </fieldset>`

const meeting = `data-webx-when="{&quot;fields.purpose&quot;:[&quot;meeting&quot;]}"`

const form = (check = '/send/check', extra = '') => `
  <form id="form-1" class="webx-form webx-form--steps" action="/send" method="post" data-webx-send
    ${check ? `data-webx-check="${check}"` : ''}
    data-webx-steps-count="Step :current of :total"
    data-webx-steps-return="Back to step :number: :title"
    data-webx-steps-fix="Please check step :number: :message">
    <input type="hidden" name="_webx_form" value="form-1">
    <div class="webx-form__body">
      <ol class="webx-form-steps" aria-label="Steps" hidden></ol>
      ${step('purpose', 'Purpose', radios('fields.purpose', ['question', 'meeting']))}
      ${step('meeting', 'Meeting', text('fields.day', 'day'), meeting)}
      ${step('contacts', 'Your details', text('fields.name', 'name') + extra)}
      <button type="submit" class="webx-form__submit">Send</button>
    </div>
    <div class="webx-form__message" role="status" aria-live="polite"></div>
    <div class="webx-form__error" role="alert" data-webx-failed="Could not send." data-webx-expired="Reload."></div>
  </form>`

const steps = () => Array.from(document.querySelectorAll('[data-webx-step]'))
const current = () => steps().find((one) => !one.hidden)?.dataset.webxStep
const indicator = () =>
  Array.from(document.querySelectorAll('.webx-form-steps__step'), (item) => item.textContent.trim())
const choose = (value) => {
  const radio = document.querySelector(`input[value="${value}"]`)
  radio.checked = true
  radio.dispatchEvent(new Event('change', { bubbles: true }))
}
const press = (selector) => {
  const button = Array.from(document.querySelectorAll(selector)).find(
    (one) => !one.hidden && !one.closest('[hidden]'),
  )
  button.click()
}

describe('a form of steps', () => {
  it('shows one step, counts out the one its condition hides, and gives the last its buttons', async () => {
    const el = await start(form())
    await settle()

    expect(current()).toBe('purpose')
    expect(steps().filter((one) => one.hidden)).toHaveLength(2)
    const bar = el.querySelector('.webx-form-steps')
    expect(bar.hidden).toBe(false)
    expect(indicator()).toEqual(['1Purpose', '2Your details'])
    expect(document.querySelector('.webx-form-steps__step').getAttribute('aria-current')).toBe(
      'step',
    )
    expect(el.querySelector('#step-purpose .webx-form-step__count').textContent).toBe('Step 1 of 2')
    // Not the last: «Next», no «Back», the form's button out of the way.
    expect(el.querySelector('#step-purpose [data-webx-step-back]').hidden).toBe(true)
    expect(el.querySelector('#step-purpose [data-webx-step-next]').hidden).toBe(false)
    expect(el.querySelector('.webx-form__submit').hidden).toBe(true)

    choose('meeting')
    await settle()
    expect(indicator()).toEqual(['1Purpose', '2Meeting', '3Your details'])
    expect(el.querySelector('#step-purpose .webx-form-step__count').textContent).toBe('Step 1 of 3')
  })

  it('«Next» with an error stays on the step, says it under the field and puts the focus there', async () => {
    const calls = answers()
    const el = await start(form())

    press('[data-webx-step-next]')
    await settle()

    expect(current()).toBe('purpose')
    const field = el.querySelector('[data-webx-field="fields.purpose"]')
    expect(field.classList.contains('is-invalid')).toBe(true)
    expect(field.querySelector('.webx-field__error').textContent).toBe(
      'The fields.purpose field is required.',
    )
    expect(document.activeElement).toBe(field.querySelector('input'))
    expect(calls).toHaveLength(0)
  })

  it('asks the server about the step, files left out, and goes on at a 204 — the focus on the next title', async () => {
    const calls = answers(new Response(null, { status: 204 }))
    const el = await start(
      form(
        '/send/check',
        `<div class="webx-field" data-webx-field="fields.photo"><input type="file" name="fields[photo]"></div>`,
      ),
    )
    const moves = []
    el.addEventListener('webx:form:step', (event) => moves.push(event.detail))

    choose('question')
    press('[data-webx-step-next]')
    await settle()

    expect(calls).toHaveLength(1)
    expect(calls[0].url).toBe('/send/check')
    expect(calls[0].body.get('webx_step')).toBe('purpose')
    expect(calls[0].body.get('fields[purpose]')).toBe('question')
    expect(calls[0].body.has('fields[photo]')).toBe(false)
    expect(current()).toBe('contacts')
    expect(moves).toEqual([{ from: 1, to: 2, name: 'contacts' }])
    expect(document.activeElement).toBe(el.querySelector('#step-contacts .webx-form-step__title'))
    // The last step: «Back» and the form's button beside it, no «Next».
    const nav = el.querySelector('#step-contacts .webx-form-step__nav')
    expect(nav.contains(el.querySelector('.webx-form__submit'))).toBe(true)
    expect(el.querySelector('.webx-form__submit').hidden).toBe(false)
    expect(el.querySelector('#step-contacts [data-webx-step-next]').hidden).toBe(true)
    expect(indicator()).toEqual(['1Purpose', '2Your details'])
    expect(document.querySelector('.webx-form-steps__button').getAttribute('aria-label')).toBe(
      'Back to step 1: Purpose',
    )
  })

  it('keeps the step at a 422 of the server, its words under the field', async () => {
    answers(json(422, { message: 'Invalid.', errors: { 'fields.purpose': ['Not this one.'] } }))
    const el = await start(form())

    choose('question')
    press('[data-webx-step-next]')
    await settle()

    expect(current()).toBe('purpose')
    expect(
      el.querySelector('[data-webx-field="fields.purpose"] .webx-field__error').textContent,
    ).toBe('Not this one.')
  })

  it('without `check` goes on by the browser’s checks alone', async () => {
    const calls = answers()
    await start(form(null))

    choose('question')
    press('[data-webx-step-next]')
    await settle()

    expect(calls).toHaveLength(0)
    expect(current()).toBe('contacts')
  })

  it('«Back» checks nothing and wipes nothing; a listener may stop a change of step', async () => {
    answers(new Response(null, { status: 204 }), new Response(null, { status: 204 }))
    const el = await start(form())
    choose('meeting')
    press('[data-webx-step-next]')
    await settle()
    expect(current()).toBe('meeting')

    el.querySelector('#day').value = 'Monday'
    press('[data-webx-step-back]')
    expect(current()).toBe('purpose')
    expect(el.querySelector('input[value="meeting"]').checked).toBe(true)
    expect(el.querySelector('#day').value).toBe('Monday')

    const stop = (event) => event.preventDefault()
    el.addEventListener('webx:form:step', stop)
    press('[data-webx-step-next]')
    await settle()
    expect(current()).toBe('purpose')
    el.removeEventListener('webx:form:step', stop)
  })

  it('Enter in a field off the last step is «Next», not the submission', async () => {
    const calls = answers()
    const el = await start(form(null))

    el.requestSubmit()
    await settle()

    expect(calls).toHaveLength(0)
    expect(current()).toBe('purpose')
    expect(
      el.querySelector('[data-webx-field="fields.purpose"]').classList.contains('is-invalid'),
    ).toBe(true)
  })

  it('a 422 of the submission on a step behind brings it back, the focus on the field, the alert says which', async () => {
    const calls = answers(
      json(422, { message: 'Invalid.', errors: { 'fields.purpose': ['Not any more.'] } }),
    )
    const el = await start(form(null))
    choose('question')
    press('[data-webx-step-next]')
    el.querySelector('#name').value = 'Alex Example'
    expect(current()).toBe('contacts')

    el.querySelector('.webx-form__submit').click()
    await settle()

    expect(calls).toHaveLength(1)
    expect(calls[0].url).toBe('/send')
    expect(current()).toBe('purpose')
    expect(document.activeElement).toBe(el.querySelector('input[value="question"]'))
    expect(el.querySelector('.webx-form__error').textContent.trim()).toBe(
      'Please check step 1: Not any more.',
    )
  })

  it('a step whose field the page came back wrong with is the one shown', async () => {
    document.body.innerHTML = ''
    const html = form(null).replace(
      '<div class="webx-field" data-webx-field="fields.name"',
      '<div class="webx-field is-invalid" data-webx-field="fields.name"',
    )
    await start(html)
    expect(current()).toBe('contacts')
  })

  it('goes back to the first step at a reset — the thank-you — and leaves the page as printed when let go', async () => {
    const el = await start(form(null))
    choose('question')
    press('[data-webx-step-next]')
    expect(current()).toBe('contacts')

    el.reset()
    await settle()
    expect(current()).toBe('purpose')

    window.webx.unmount(document.body)
    expect(steps().every((one) => !one.hidden)).toBe(true)
    expect(el.querySelector('.webx-form-steps').hidden).toBe(true)
    expect(el.querySelector('.webx-form__submit').parentElement).toBe(
      el.querySelector('.webx-form__body'),
    )
    expect(el.querySelector('.webx-form__submit').hidden).toBe(false)
  })
})
