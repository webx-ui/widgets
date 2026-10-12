import { afterEach, describe, expect, it, vi } from 'vitest'

// jsdom has <dialog> but not its modal half; enough of it for the behaviours to be observable.
if (!HTMLDialogElement.prototype.showModal) {
  HTMLDialogElement.prototype.showModal = function () {
    this.setAttribute('open', '')
  }
  HTMLDialogElement.prototype.close = function () {
    if (!this.hasAttribute('open')) return
    this.removeAttribute('open')
    this.dispatchEvent(new Event('close'))
  }
}

let module

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
  vi.restoreAllMocks()
  delete globalThis.fetch
})

async function start(html) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  module = await import('./form.js')
  return document.querySelector('form')
}

/** Long enough for a fetch, its body and the handler after it. */
const settle = async () => {
  for (let i = 0; i < 10; i++) await new Promise((resolve) => setTimeout(resolve, 0))
}

const json = (status, data) =>
  new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } })

/** A fetch answering from a list, one answer per call, and remembering what it was asked. */
function answers(...list) {
  const calls = []
  globalThis.fetch = vi.fn(async (url, init = {}) => {
    calls.push({
      url: String(url),
      init,
      body: init.body instanceof FormData ? Object.fromEntries(init.body) : null,
    })
    const next = list.shift()
    if (next instanceof Error) throw next
    return typeof next === 'function' ? next() : next
  })
  return calls
}

const field = (
  key,
  id,
  control = `<input type="text" id="${id}" name="${key}" aria-describedby="${id}-error">`,
) => `
  <div class="webx-field" data-webx-field="${key}">
    <label for="${id}">${key}</label>
    ${control}
    <p class="webx-field__error" id="${id}-error" hidden></p>
  </div>`

const shell = (fields, attributes = 'data-webx-csrf="/webx/widgets/csrf"') => `
  <form id="form-1" class="webx-form" action="/send" method="post" data-webx-send ${attributes}>
    <input type="hidden" name="_token" value="printed">
    <input type="hidden" name="_webx_form" value="form-1">
    <div class="webx-form__body">
      ${fields}
      <button type="submit" class="webx-form__submit">Send</button>
    </div>
    <progress class="webx-form__progress" max="100" value="0" hidden></progress>
    <div class="webx-form__message" role="status" aria-live="polite"></div>
    <div class="webx-form__error" role="alert" data-webx-failed="Could not send." data-webx-expired="Reload the page."></div>
    <button type="button" class="webx-form__done" data-webx-dialog-close hidden>Close</button>
  </form>`

const contact =
  field('fields.name', 'field-fields-name') +
  field('fields.email', 'field-fields-email') +
  `<fieldset class="webx-field" data-webx-field="extras">
     <legend>Extras</legend>
     <input type="checkbox" id="x1" name="extras[]" value="a">
     <input type="checkbox" id="x2" name="extras[]" value="b">
     <p class="webx-field__error" id="field-extras-error" hidden></p>
   </fieldset>`

const send = (form) => form.querySelector('.webx-form__submit').click()

describe('the form sent in place', () => {
  it('lays a 422 out by field, puts what belongs to no field in the alert and the focus on the first wrong one', async () => {
    const form = await start(shell(contact))
    answers(
      json(422, {
        message: 'The name field is required. (and 3 more errors)',
        errors: {
          'fields.email': ['The email must be a valid address.'],
          'fields.name': ['The name field is required.'],
          'extras.1': ['The second extra is not offered.'],
          captcha: ['Prove you are not a robot.'],
        },
      }),
    )
    const errors = []
    form.addEventListener('webx:form:error', (event) => errors.push(event.detail))

    send(form)
    await settle()

    const name = form.querySelector('[data-webx-field="fields.name"]')
    expect(name.classList.contains('is-invalid')).toBe(true)
    expect(name.querySelector('.webx-field__error').hidden).toBe(false)
    expect(name.querySelector('.webx-field__error').textContent).toBe('The name field is required.')
    expect(document.getElementById('field-fields-name').getAttribute('aria-invalid')).toBe('true')
    // An item of a group is the group's, and every control of it is said to be wrong.
    expect(form.querySelector('[data-webx-field="extras"] .webx-field__error').textContent).toBe(
      'The second extra is not offered.',
    )
    expect(document.getElementById('x1').getAttribute('aria-invalid')).toBe('true')
    expect(document.getElementById('x2').getAttribute('aria-invalid')).toBe('true')
    // No field of that name: the alert, not nowhere — and not Laravel's summary on top.
    expect(form.querySelector('.webx-form__error').textContent).toBe('Prove you are not a robot.')
    // The first in the form, not the first in the answer.
    expect(document.activeElement).toBe(document.getElementById('field-fields-name'))
    expect(errors).toHaveLength(1)
    expect(errors[0].status).toBe(422)
    expect(Object.keys(errors[0].errors)).toContain('captcha')
  })

  it('takes an error away as its field is corrected, and all of them on the next submission', async () => {
    const form = await start(shell(contact))
    answers(
      json(422, { errors: { 'fields.name': ['Required.'], 'fields.email': ['Wrong.'] } }),
      json(200, { ok: true }),
    )
    send(form)
    await settle()

    const input = document.getElementById('field-fields-name')
    input.value = 'Ann'
    input.dispatchEvent(new Event('input', { bubbles: true }))

    expect(input.closest('.webx-field').classList.contains('is-invalid')).toBe(false)
    expect(input.hasAttribute('aria-invalid')).toBe(false)
    expect(input.closest('.webx-field').querySelector('.webx-field__error').hidden).toBe(true)
    expect(
      form.querySelector('[data-webx-field="fields.email"]').classList.contains('is-invalid'),
    ).toBe(true)

    send(form)
    await settle()
    expect(form.querySelectorAll('.is-invalid')).toHaveLength(0)
  })

  it('sends once however often it is pressed, held back and busy while it goes', async () => {
    const form = await start(shell(contact))
    let release
    const calls = answers(
      () => new Promise((resolve) => (release = () => resolve(json(200, { ok: true })))),
    )

    send(form)
    await settle()
    const button = form.querySelector('.webx-form__submit')
    expect(form.classList.contains('is-sending')).toBe(true)
    expect(form.getAttribute('aria-busy')).toBe('true')
    expect(button.getAttribute('aria-disabled')).toBe('true')

    send(form)
    form.requestSubmit()
    await settle()
    expect(calls).toHaveLength(1)

    release()
    await settle()
    expect(form.classList.contains('is-sending')).toBe(false)
    expect(form.hasAttribute('aria-busy')).toBe(false)
    expect(button.hasAttribute('aria-disabled')).toBe(false)
  })

  it('sends the cookie’s token, not the one the page was cached with', async () => {
    const form = await start(shell(contact))
    document.cookie = 'XSRF-TOKEN=fresh%3D%3D'
    const calls = answers(json(200, { ok: true }))

    send(form)
    await settle()

    expect(calls[0].url).toBe('/send')
    expect(calls[0].init.method).toBe('POST')
    expect(calls[0].init.headers.Accept).toBe('application/json')
    expect(calls[0].init.headers['X-XSRF-TOKEN']).toBe('fresh==')
    expect(calls[0].body._token).toBeUndefined()
    expect(calls[0].body._webx_form).toBe('form-1')
  })

  it('on a 419 asks for a fresh token and tries again — once', async () => {
    const form = await start(shell(contact))
    document.cookie = 'XSRF-TOKEN=stale'
    const calls = answers(
      json(419, { message: 'CSRF token mismatch.' }),
      () => {
        document.cookie = 'XSRF-TOKEN=renewed'
        return new Response(null, { status: 204 })
      },
      json(200, { ok: true, heading: 'Thanks' }),
    )

    send(form)
    await settle()

    expect(calls.map((call) => call.url)).toEqual(['/send', '/webx/widgets/csrf', '/send'])
    expect(calls[2].init.headers['X-XSRF-TOKEN']).toBe('renewed')
    expect(form.querySelector('.webx-form__heading').textContent).toBe('Thanks')
  })

  it('a second 419 is not tried a third time: the page is too old, and it says so', async () => {
    const form = await start(shell(contact))
    const calls = answers(json(419, {}), new Response(null, { status: 204 }), json(419, {}))
    const errors = []
    form.addEventListener('webx:form:error', (event) => errors.push(event.detail.status))

    send(form)
    await settle()

    expect(calls).toHaveLength(3)
    expect(form.querySelector('.webx-form__error').textContent).toBe('Reload the page.')
    expect(errors).toEqual([419])
  })

  it('a door without a token route is not retried, and a dead network is said in the alert', async () => {
    const form = await start(shell(contact, ''))
    const calls = answers(json(419, {}), new TypeError('offline'))

    send(form)
    await settle()
    expect(calls).toHaveLength(1)

    send(form)
    await settle()
    expect(form.querySelector('.webx-form__error').textContent).toBe('Could not send.')
    expect(form.hasAttribute('aria-busy')).toBe(false)
  })

  it('is held busy while a module finishes first, ignores presses meanwhile, then sends what the form holds', async () => {
    const form = await start(shell(contact + '<input type="hidden" name="captcha" value="">'))
    const calls = answers(json(200, { ok: true }))
    let finish
    let asked = 0
    form.addEventListener('webx:form:submit', (event) => {
      asked++
      event.detail.data.append('extra', 'kept')
      event.detail.wait(new Promise((resolve) => (finish = resolve)))
    })

    send(form)
    await settle()
    expect(form.classList.contains('is-sending')).toBe(true)
    expect(form.getAttribute('aria-busy')).toBe('true')
    expect(form.querySelector('.webx-form__submit').getAttribute('aria-disabled')).toBe('true')
    expect(calls).toHaveLength(0)

    // A second press while it is held: no second run of the module, nothing sent.
    send(form)
    await settle()
    expect(asked).toBe(1)
    expect(calls).toHaveLength(0)

    // What the module put into the form while it was held goes along, and what it added to data.
    form.querySelector('[name="captcha"]').value = 'token'
    finish()
    await settle()
    expect(calls).toHaveLength(1)
    expect(calls[0].body).toMatchObject({ captcha: 'token', extra: 'kept' })
    expect(form.classList.contains('is-sending')).toBe(false)
  })

  it('lets the form go, sending nothing, when what it waited for fails — the module’s word stays', async () => {
    const form = await start(shell(contact))
    const calls = answers(json(200, { ok: true }))
    let fail
    const hold = (event) => event.detail.wait(new Promise((resolve, reject) => (fail = reject)))
    form.addEventListener('webx:form:submit', hold)

    send(form)
    await settle()
    expect(form.getAttribute('aria-busy')).toBe('true')

    const name = form.querySelector('[data-webx-field="fields.name"]')
    name.classList.add('is-invalid')
    name.querySelector('.webx-field__error').textContent = 'The check could not run.'
    fail(new Error('captcha'))
    await settle()

    expect(calls).toHaveLength(0)
    expect(form.classList.contains('is-sending')).toBe(false)
    expect(form.hasAttribute('aria-busy')).toBe(false)
    expect(form.querySelector('.webx-form__submit').hasAttribute('aria-disabled')).toBe(false)
    expect(name.querySelector('.webx-field__error').textContent).toBe('The check could not run.')

    // The next press is a press again.
    form.removeEventListener('webx:form:submit', hold)
    send(form)
    await settle()
    expect(calls).toHaveLength(1)
  })

  it('lets a module stop the submission, and says success with the answer', async () => {
    const form = await start(shell(contact))
    const calls = answers(
      json(200, { ok: true, heading: null, message: null, redirect: null, html: null }),
    )
    const stop = (event) => {
      expect(event.detail.data).toBeInstanceOf(FormData)
      event.preventDefault()
    }
    form.addEventListener('webx:form:submit', stop)

    send(form)
    await settle()
    expect(calls).toHaveLength(0)
    expect(form.classList.contains('is-sending')).toBe(false)

    form.removeEventListener('webx:form:submit', stop)
    const success = []
    form.addEventListener('webx:form:success', (event) => success.push(event.detail))
    send(form)
    await settle()

    expect(calls).toHaveLength(1)
    expect(success).toEqual([
      {
        status: 200,
        response: { ok: true, heading: null, message: null, redirect: null, html: null },
      },
    ])
    // Nothing to show: the form stays as it was.
    expect(form.querySelector('.webx-form__message').childElementCount).toBe(0)
    expect(form.classList.contains('is-sent')).toBe(false)
  })

  it('shows the message with its heading in the live region and empties the form', async () => {
    const form = await start(shell(contact))
    answers(json(200, { ok: true, heading: 'Thank you', message: '<p>We will <b>write</b>.</p>' }))
    document.getElementById('field-fields-name').value = 'Ann'

    send(form)
    await settle()

    const region = form.querySelector('.webx-form__message')
    expect(region.querySelector('.webx-form__answer .webx-form__heading').textContent).toBe(
      'Thank you',
    )
    expect(region.querySelector('.webx-form__text b').textContent).toBe('write')
    expect(form.classList.contains('is-sent')).toBe(true)
    expect(document.getElementById('field-fields-name').value).toBe('')
    // On a page the fields stay: another request may follow.
    expect(form.querySelector('.webx-form__body').hidden).toBe(false)
  })

  it('puts the HTML of the answer in place of the fields and starts what is in it', async () => {
    const form = await start(shell(contact))
    answers(json(200, { ok: true, html: '<p class="step">Step two</p><div data-webx-tabs></div>' }))

    send(form)
    await settle()

    const body = form.querySelector('.webx-form__body')
    expect(body.querySelector('.step').textContent).toBe('Step two')
    expect(body.querySelector('[data-webx-field]')).toBeNull()
    // The token and the form's name are outside the fields: the next step sends them too.
    expect(form.querySelector('[name=_webx_form]')).not.toBeNull()
  })

  it('goes where the answer says, and stays held until it is gone', async () => {
    const form = await start(shell(contact))
    const go = vi.spyOn(module.navigation, 'go').mockImplementation(() => {})
    answers(json(200, { ok: true, redirect: '/account' }))

    send(form)
    await settle()

    expect(go).toHaveBeenCalledWith('/account')
    expect(form.getAttribute('aria-busy')).toBe('true')
  })

  it('in a dialog shows the thank-you in place of the fields with a button that closes it, then is fresh again', async () => {
    document.body.innerHTML = ''
    const form = await start(`<dialog id="callback" class="webx-dialog">${shell(contact)}</dialog>`)
    const dialog = document.getElementById('callback')
    window.webx.openDialog(dialog)
    answers(json(200, { ok: true, heading: 'We will call' }))

    send(form)
    await settle()

    const done = form.querySelector('.webx-form__done')
    expect(form.querySelector('.webx-form__body').hidden).toBe(true)
    expect(done.hidden).toBe(false)
    expect(document.activeElement).toBe(done)
    expect(dialog.open).toBe(true)

    done.click()
    await settle()

    expect(dialog.open).toBe(false)
    expect(form.querySelector('.webx-form__body').hidden).toBe(false)
    expect(done.hidden).toBe(true)
    expect(form.querySelector('.webx-form__message').childElementCount).toBe(0)
    expect(form.classList.contains('is-sent')).toBe(false)
  })

  it('opens again the dialog of a form the page came back from', async () => {
    const showModal = vi.spyOn(HTMLDialogElement.prototype, 'showModal')
    const form = await start(
      `<dialog id="callback" class="webx-dialog" open>${shell(field('phone', 'field-phone')).replace('data-webx-send', 'data-webx-send data-webx-returned').replace('class="webx-field"', 'class="webx-field is-invalid"')}</dialog>`,
    )
    const dialog = document.getElementById('callback')

    // Printed open is not modal: closed and opened again as one, the focus on the field the server refused.
    expect(showModal).toHaveBeenCalledTimes(1)
    expect(dialog.open).toBe(true)
    expect(document.activeElement).toBe(form.querySelector('input:not([type=hidden])'))
  })

  it('sends a file by XMLHttpRequest and shows how far the upload is', async () => {
    const form = await start(
      shell(field('cv', 'field-cv', '<input type="file" id="field-cv" name="cv">')),
    )
    // jsdom cannot pick a file for the input; a module's handler can add one to what is sent.
    form.addEventListener('webx:form:submit', (event) =>
      event.detail.data.set('cv', new File(['x'], 'cv.pdf')),
    )
    globalThis.fetch = vi.fn()

    const sent = []
    class FakeRequest {
      constructor() {
        this.upload = new EventTarget()
        this.listeners = new EventTarget()
        this.headers = {}
      }
      open(method, url) {
        Object.assign(this, { method, url })
      }
      setRequestHeader(name, value) {
        this.headers[name] = value
      }
      addEventListener(type, listener) {
        this.listeners.addEventListener(type, listener)
      }
      send(body) {
        sent.push(this)
        this.body = body
      }
    }
    vi.stubGlobal('XMLHttpRequest', FakeRequest)

    send(form)
    await settle()

    expect(globalThis.fetch).not.toHaveBeenCalled()
    const xhr = sent[0]
    expect(xhr.method).toBe('POST')
    expect(xhr.headers.Accept).toBe('application/json')
    expect(xhr.body.get('cv').name).toBe('cv.pdf')

    const progress = new Event('progress')
    Object.assign(progress, { lengthComputable: true, loaded: 40, total: 100 })
    xhr.upload.dispatchEvent(progress)
    const bar = form.querySelector('.webx-form__progress')
    expect(bar.hidden).toBe(false)
    expect(bar.value).toBe(40)

    Object.assign(xhr, {
      status: 200,
      responseText: JSON.stringify({ ok: true, heading: 'Got it' }),
      responseURL: '',
    })
    xhr.listeners.dispatchEvent(new Event('load'))
    await settle()

    expect(bar.hidden).toBe(true)
    expect(form.querySelector('.webx-form__heading').textContent).toBe('Got it')
    vi.unstubAllGlobals()
  })

  it('lets the form go when unmounted: a submission is the browser’s again', async () => {
    const form = await start(shell(contact))
    const calls = answers(json(200, { ok: true }))
    window.webx.unmount(document)

    const event = new Event('submit', { cancelable: true })
    form.dispatchEvent(event)
    await settle()

    expect(event.defaultPrevented).toBe(false)
    expect(calls).toHaveLength(0)
  })
})
