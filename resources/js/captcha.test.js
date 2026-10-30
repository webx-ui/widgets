/**
 * captcha.js: the site's captcha on the events of the widgets' form engine (form.test.js). The
 * inbox's inbox.js is the same listener under its old name (module-inbox inbox.test.js).
 */
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'

beforeAll(async () => {
  await import('./captcha.js')
})

afterEach(() => {
  document.body.innerHTML = ''
  delete window.grecaptcha
  delete window.turnstile
})

function page({ provider = 'turnstile', type = 'checkbox', widget = true } = {}) {
  document.body.innerHTML = `
    <form class="webx-form" data-webx-send data-webx-form="contact">
      <div class="webx-form__body">
        <input name="fields[name]" value="Ada">
        ${
          widget
            ? `<div class="webx-captcha" data-webx-field="captcha"
                data-webx-captcha="${provider}" data-webx-captcha-type="${type}"
                data-webx-captcha-key="site-key" data-webx-captcha-action="webx_form_contact"
                data-webx-captcha-message="Please confirm you are not a robot."
                data-webx-captcha-unavailable="The check could not run.">
                ${
                  type === 'v3'
                    ? '<input type="hidden" name="g-recaptcha-response" value="">'
                    : type === 'checkbox'
                      ? `<div class="${provider === 'turnstile' ? 'cf-turnstile' : 'g-recaptcha'}" data-sitekey="site-key"><input name="${provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response'}" value=""></div>`
                      : '<div class="webx-captcha__widget" data-sitekey="site-key"></div>'
                }
                <p class="webx-field__error" hidden></p>
              </div>`
            : ''
        }
        <button type="submit" class="webx-form__submit">Send</button>
      </div>
    </form>`
  const form = document.querySelector('form')
  form.requestSubmit = vi.fn()
  return {
    form,
    widget: form.querySelector('[data-webx-captcha]'),
    error: form.querySelector('.webx-field__error'),
    button: form.querySelector('button'),
  }
}

/**
 * What form.js does before sending: `webx:form:submit`, cancelable, with `wait` to hold the form.
 * `sent` false — it was cancelled; `held` — what the listener asked the engine to wait for.
 */
function submit(form, submitter = null) {
  const waits = []
  const sent = form.dispatchEvent(
    new CustomEvent('webx:form:submit', {
      bubbles: true,
      cancelable: true,
      detail: { data: new FormData(form), submitter, wait: (promise) => waits.push(promise) },
    }),
  )
  const outcome = waits.length
    ? Promise.all(waits).then(
        () => 'resolved',
        () => 'rejected',
      )
    : null
  return { sent, held: waits.length > 0, outcome }
}

const answered = (form, name) =>
  form.dispatchEvent(new CustomEvent(`webx:form:${name}`, { bubbles: true, detail: {} }))

/** A turn of the microtask queue or two: where captcha.js starts the provider. */
const turn = async () => {
  for (let i = 0; i < 5; i++) await Promise.resolve()
}

function turnstile() {
  const api = {
    settings: null,
    render: vi.fn((box, given) => {
      api.settings = given
      return 'w1'
    }),
    execute: vi.fn(),
    reset: vi.fn(),
  }
  window.turnstile = api
  return api
}

describe("captcha.js — the site's captcha on the form engine", () => {
  it('leaves a form without a captcha alone', () => {
    const { form } = page({ widget: false })
    expect(submit(form)).toMatchObject({ sent: true, held: false })
  })

  it('stops a checkbox nobody ticked with the intake’s sentence, in the captcha’s place', () => {
    const { form, widget, error } = page({ provider: 'turnstile' })
    expect(submit(form)).toMatchObject({ sent: false, held: false })
    expect(error.hidden).toBe(false)
    expect(error.textContent).toBe('Please confirm you are not a robot.')
    expect(widget.classList.contains('is-invalid')).toBe(true)

    // Ticked: it goes.
    widget.querySelector('[name="cf-turnstile-response"]').value = 'token'
    expect(submit(form)).toMatchObject({ sent: true, held: false })
  })

  it('holds the form while reCAPTCHA v3 scores it, and lets it go with the token in it', async () => {
    const execute = vi.fn(() => Promise.resolve('v3-token'))
    window.grecaptcha = { ready: (run) => run(), execute }
    const { form, widget, button } = page({ provider: 'recaptcha', type: 'v3' })

    const press = submit(form, button)
    expect(press).toMatchObject({ sent: true, held: true })
    expect(await press.outcome).toBe('resolved')
    expect(execute).toHaveBeenCalledWith('site-key', { action: 'webx_form_contact' })
    expect(widget.querySelector('[name="g-recaptcha-response"]').value).toBe('v3-token')
    // The engine sends what the form holds now; nothing is pressed a second time.
    expect(form.requestSubmit).not.toHaveBeenCalled()

    // The answer is good once — gone after the reply.
    answered(form, 'error')
    expect(widget.querySelector('[name="g-recaptcha-response"]').value).toBe('')
  })

  it('lets a v3 form go without a score: refusing it is the intake’s word', async () => {
    window.grecaptcha = { ready: (run) => run(), execute: () => Promise.reject(new Error('x')) }
    const { form } = page({ provider: 'recaptcha', type: 'v3' })
    expect(await submit(form).outcome).toBe('resolved')
  })

  it('holds the form while an invisible Turnstile runs, and lets it go when it answers', async () => {
    const api = turnstile()
    const { form, widget, button } = page({ provider: 'turnstile', type: 'invisible' })

    const press = submit(form, button)
    expect(press).toMatchObject({ sent: true, held: true })
    await turn()
    expect(api.render).toHaveBeenCalledTimes(1)
    expect(api.settings).toMatchObject({
      sitekey: 'site-key',
      appearance: 'interaction-only',
      execution: 'execute',
      retry: 'never',
    })
    expect(api.execute).toHaveBeenCalledWith('w1')

    api.settings.callback('token')
    expect(await press.outcome).toBe('resolved')
    expect(form.requestSubmit).not.toHaveBeenCalled()

    // After the reply the widget is reset by its id; the next press runs it again, not draws it.
    answered(form, 'success')
    expect(api.reset).toHaveBeenCalledWith('w1')
    expect(submit(form, button).held).toBe(true)
    await turn()
    expect(api.render).toHaveBeenCalledTimes(1)
    expect(api.execute).toHaveBeenCalledTimes(2)
    expect(widget.dataset.webxCaptchaState).toBe('running')
  })

  it('lets the form go with its word when Turnstile fails, times out or is not supported', async () => {
    for (const callback of ['error-callback', 'timeout-callback', 'unsupported-callback']) {
      const api = turnstile()
      const { form, widget, error } = page({ provider: 'turnstile', type: 'invisible' })

      const press = submit(form)
      await turn()
      // Handled: Turnstile would throw an uncaught error otherwise.
      expect(api.settings[callback]()).toBe(true)
      expect(await press.outcome).toBe('rejected')
      expect(error.textContent).toBe('The check could not run.')
      expect(widget.dataset.webxCaptchaState).toBe('error')

      // The next press starts clean: the widget is reset, then run.
      submit(form)
      await turn()
      expect(api.reset).toHaveBeenLastCalledWith('w1')
      expect(api.execute).toHaveBeenCalledTimes(2)
    }
  })

  it('lets the form go when the challenge expires before it is answered', async () => {
    const api = turnstile()
    const { form, widget, error } = page({ provider: 'turnstile', type: 'invisible' })

    const press = submit(form)
    await turn()
    api.settings['expired-callback']()
    expect(await press.outcome).toBe('rejected')
    expect(error.textContent).toBe('The check could not run.')

    // An answer gone stale after it was sent holds nothing: only marked, reset on the next press.
    const again = submit(form)
    await turn()
    api.settings.callback('token')
    expect(await again.outcome).toBe('resolved')
    api.settings['expired-callback']()
    expect(widget.dataset.webxCaptchaState).toBe('expired')
    submit(form)
    await turn()
    expect(api.reset).toHaveBeenLastCalledWith('w1')
  })

  it('lets the form go when the provider throws instead of running', async () => {
    window.turnstile = {
      render: () => {
        throw new Error('bad sitekey')
      },
      execute: vi.fn(),
      reset: vi.fn(),
    }
    const { form, error } = page({ provider: 'turnstile', type: 'invisible' })
    expect(await submit(form).outcome).toBe('rejected')
    expect(error.textContent).toBe('The check could not run.')
  })

  it('lets the form go when no answer comes at all — a reCAPTCHA challenge closed', async () => {
    vi.useFakeTimers()
    try {
      window.grecaptcha = {
        ready: (run) => run(),
        render: () => 0,
        execute: vi.fn(),
        reset: vi.fn(),
      }
      const { form, error } = page({ provider: 'recaptcha', type: 'invisible' })
      const press = submit(form)
      await vi.advanceTimersByTimeAsync(120000)
      expect(await press.outcome).toBe('rejected')
      expect(error.textContent).toBe('The check could not run.')
    } finally {
      vi.useRealTimers()
    }
  })

  it('sizes an invisible reCAPTCHA as invisible and waits until it is ready', async () => {
    let settings
    const ready = vi.fn((run) => run())
    window.grecaptcha = {
      ready,
      render: vi.fn((box, given) => {
        settings = given
        return 0
      }),
      execute: vi.fn(),
      reset: vi.fn(),
    }
    const { form } = page({ provider: 'recaptcha', type: 'invisible' })

    expect(submit(form).held).toBe(true)
    await turn()
    expect(ready).toHaveBeenCalled()
    expect(settings.size).toBe('invisible')
    expect(window.grecaptcha.execute).toHaveBeenCalledWith(0)
  })

  it('does not wait for a provider whose script never came: the intake says what is missing', () => {
    const { form } = page({ provider: 'turnstile', type: 'invisible' })
    expect(submit(form)).toMatchObject({ sent: true, held: false })
  })

  it('resets a reCAPTCHA checkbox by its place on the page after any reply', () => {
    window.grecaptcha = { reset: vi.fn() }
    const { form } = page({ provider: 'recaptcha' })
    answered(form, 'error')
    expect(window.grecaptcha.reset).toHaveBeenCalledWith(0)
  })
})
