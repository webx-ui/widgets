/**
 * The site's captcha in a form (`<x-webx-captcha>`, `WebxUi\Widgets\Captcha`). Sending is the form
 * engine's (form.js, §17.3); this only listens to its events:
 *
 *  - `webx:form:submit` — before the form goes, the captcha's answer: a checkbox nobody ticked
 *    stops it with the sentence the server would send back; an invisible one (reCAPTCHA or
 *    Turnstile) is run now, and reCAPTCHA v3 asks for a score for the form's action — both
 *    holding the form with the engine's `detail.wait(promise)`, so it is busy from the press to
 *    the answer and a second press does nothing; a captcha that failed lets it go with its word;
 *  - `webx:form:success`, `webx:form:error` — a captcha answer is good once: the widget is reset,
 *    so a visitor told their e-mail is wrong is not refused by a captcha they already passed.
 *
 * A provider whose script never arrived — blocked, offline — is not waited for: the form is sent,
 * and the server's refusal says what is missing, under the widget (`data-webx-field="captcha"`).
 */

;(function () {
  'use strict'

  // One listener for every captcha on the page: the inbox of before printed its own copy of this
  // script, and two of them would run one widget twice.
  if (window.webxCaptcha || window.webxInboxCaptcha) return
  window.webxCaptcha = window.webxInboxCaptcha = true

  var CAPTCHA = '[data-webx-captcha]'

  function widgetOf(form) {
    if (!(form instanceof HTMLFormElement)) return null

    var widget = form.querySelector(CAPTCHA)

    return widget && widget.closest('form') === form ? widget : null
  }

  /** The intake's word, where form.js puts it too: the error of the captcha's place. */
  function say(widget, message) {
    var box = widget.querySelector('.webx-field__error')

    widget.classList.toggle('is-invalid', !!message)
    if (!box) return
    box.textContent = message || ''
    box.hidden = !message
  }

  /** reCAPTCHA numbers its widgets, Turnstile names them. */
  function widgetId(widget, provider) {
    var id = widget.dataset.webxWidget

    return provider === 'recaptcha' ? Number(id) : id
  }

  /**
   * How long a captcha may take before the form is let go. A reCAPTCHA challenge the visitor
   * closes says nothing at all, and one left open expires in two minutes.
   */
  var LIMIT = 120000

  /** The promise, or what `late` makes of it when it has not settled in time. */
  function within(promise, late) {
    var timer

    return Promise.race([
      promise,
      new Promise(function (resolve, reject) {
        timer = setTimeout(function () {
          late(resolve, reject)
        }, LIMIT)
      }),
    ]).finally(function () {
      clearTimeout(timer)
    })
  }

  document.addEventListener('webx:form:submit', function (event) {
    var form = event.target
    var widget = widgetOf(form)

    if (!widget) return

    var provider = widget.getAttribute('data-webx-captcha')
    var type = widget.getAttribute('data-webx-captcha-type') || 'checkbox'
    var key = widget.getAttribute('data-webx-captcha-key') || ''

    if (type === 'checkbox') {
      var answer = widget.querySelector(
        '[name="' +
          (provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response') +
          '"]',
      )

      if (answer && !answer.value) {
        event.preventDefault()
        say(widget, widget.getAttribute('data-webx-captcha-message'))
      }

      return
    }

    // The engine holds the form while the captcha runs — busy, another press ignored — and reads
    // it again, the answer in it, when the promise resolves. An engine that cannot hold it sends
    // it as it is, and the intake's refusal says what is missing.
    var wait = event.detail && typeof event.detail.wait === 'function' ? event.detail.wait : null

    if (!wait) return

    if (provider === 'recaptcha' && type === 'v3') {
      var grecaptcha = window.grecaptcha

      if (!grecaptcha || typeof grecaptcha.ready !== 'function') return

      // No score — refused, or never come — is the intake's to judge: the form goes either way.
      wait(
        within(
          new Promise(function (resolve) {
            grecaptcha.ready(function () {
              grecaptcha
                .execute(key, {
                  action: widget.getAttribute('data-webx-captcha-action') || 'submit',
                })
                .then(function (token) {
                  var input = widget.querySelector('[name="g-recaptcha-response"]')

                  if (input) input.value = token
                  resolve()
                }, resolve)
            })
          }),
          function (resolve) {
            resolve()
          },
        ),
      )

      return
    }

    // Invisible, either provider: one widget per form, drawn on the first submit and remembered
    // by its id, because the providers' execute and reset act on the first widget of the page
    // without one. Run on submit rather than on load, so the answer is fresh — a Turnstile token
    // made when the page opened is dead after five minutes of typing.
    var api = provider === 'turnstile' ? window.turnstile : window.grecaptcha
    var box = widget.querySelector('[data-sitekey]')

    if (!api || typeof api.render !== 'function' || !box) return

    // A provider that could not vouch for this browser leaves no answer, and posting without one
    // only earns a refusal that says less than this does. The form is let go; the next press
    // tries once more.
    var failed = function () {
      var pending = widget.webxPending

      widget.webxPending = null
      widget.dataset.webxCaptchaState = 'error'
      say(widget, widget.getAttribute('data-webx-captcha-unavailable'))
      if (pending) pending.reject(new Error('captcha'))

      // Handled: Turnstile throws an uncaught error for every one it is not told about.
      return true
    }

    var run = function () {
      if (widget.dataset.webxWidget === undefined) {
        var settings = {
          sitekey: key,
          callback: function () {
            var pending = widget.webxPending

            widget.webxPending = null
            widget.dataset.webxCaptchaState = 'answered'
            if (pending) pending.resolve()
          },
          'error-callback': failed,
          // A challenge left until it went stale is no answer: the form is let go. An answer that
          // went stale after it was sent is only marked — the next press draws a fresh one.
          'expired-callback': function () {
            if (widget.webxPending) return failed()
            widget.dataset.webxCaptchaState = 'expired'
          },
        }

        if (provider === 'turnstile') {
          // Nothing on the page unless Cloudflare really needs the visitor, and no retrying on
          // its own: a browser it distrusts fails the same way every few seconds for as long as
          // the page is open. The visitor's next press of the button is the retry.
          settings.appearance = 'interaction-only'
          settings.execution = 'execute'
          settings.retry = 'never'
          settings['timeout-callback'] = failed
          settings['unsupported-callback'] = failed
        } else {
          settings.size = 'invisible'
        }

        widget.dataset.webxWidget = String(api.render(box, settings))
      } else if (widget.dataset.webxCaptchaState) {
        // Anything but a widget fresh from its reset: failed, expired, answered and never sent.
        api.reset(widgetId(widget, provider))
      }

      widget.dataset.webxCaptchaState = 'running'
      api.execute(widgetId(widget, provider))
    }

    var guarded = function () {
      try {
        run()
      } catch {
        failed()
      }
    }

    wait(
      within(
        new Promise(function (resolve, reject) {
          widget.webxPending = { resolve: resolve, reject: reject }

          // A turn later, not now: the engine clears the form's errors right after this event,
          // and a provider that throws at once has to say so after that, not before.
          Promise.resolve().then(function () {
            // reCAPTCHA has to be asked whether it is ready; Turnstile is, once its script is here.
            if (provider === 'recaptcha' && typeof api.ready === 'function') api.ready(guarded)
            else guarded()
          })
        }),
        function () {
          failed()
        },
      ),
    )
  })

  /**
   * Every reset names its widget: a page may carry two forms with a captcha each, and the
   * providers' reset without one resets the first widget on the page, not this form's.
   */
  function reset(event) {
    var widget = widgetOf(event.target)

    if (!widget) return

    var provider = widget.getAttribute('data-webx-captcha')
    var type = widget.getAttribute('data-webx-captcha-type') || 'checkbox'

    if (widget.dataset.webxCaptchaState === 'answered') delete widget.dataset.webxCaptchaState

    try {
      if (provider === 'recaptcha' && type === 'v3') {
        var input = widget.querySelector('[name="g-recaptcha-response"]')

        if (input) input.value = ''

        return
      }

      var api = provider === 'turnstile' ? window.turnstile : window.grecaptcha

      if (!api) return

      if (type === 'invisible') {
        if (widget.dataset.webxWidget !== undefined) api.reset(widgetId(widget, provider))

        return
      }

      if (provider === 'turnstile') {
        // Turnstile takes the container it drew in.
        api.reset(widget.querySelector('.cf-turnstile') || undefined)

        return
      }

      // A reCAPTCHA checkbox is drawn by the provider's script, which numbers the widgets it finds
      // in the order they stand on the page — so the place of this one is its id.
      var drawn = Array.prototype.indexOf.call(
        document.querySelectorAll('.g-recaptcha'),
        widget.querySelector('.g-recaptcha'),
      )

      if (drawn >= 0) api.reset(drawn)
    } catch {
      // A provider that is not loaded yet, or one with nothing to reset: neither is worth more.
    }
  }

  document.addEventListener('webx:form:success', reset)
  document.addEventListener('webx:form:error', reset)
})()
