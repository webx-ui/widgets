/*
 * A form of several steps (FORM_STEPS §3). Its own file, claimed by `<x-webx-form steps>`: a flat
 * form never loads it.
 *
 * The server prints every step, a `<fieldset data-webx-step>` each; without this they all stand on
 * the page and one button sends them. With it:
 *
 *  - one step is shown, the rest `hidden` — their fields stay in the form and go with it at the end;
 *  - the indicator (`.webx-form-steps`) numbers the steps shown: the current one `aria-current`,
 *    the ones behind are buttons back to them, a step its condition hides is counted out;
 *  - «Next» checks the step's fields by the rules of the page (form.js), then — with `:check` —
 *    asks the server (`webx_step` = the step, no files: the browser has measured them); a 422 is
 *    said under the fields, a 2xx goes on. «Back» checks nothing and wipes nothing. Enter in a field
 *    is «Next» too, but on the last step;
 *  - the last step shown has the form's own buttons in place of «Next»;
 *  - an error on a step not shown (the server's, on the last press) brings that step back, the
 *    focus on the field, and says in the form's alert which step it is; «Edit» of a summary
 *    (summary.js, `detail.edit`) brings it back the same way, without a word in the alert;
 *  - a change of step: `webx:form:step` (cancelable; `detail.from`, `detail.to` — numbers among
 *    the steps shown, `detail.name`), the focus on the step's title without a jump, the top of the
 *    form in sight — in a dialog, of the dialog's own scroll;
 *  - a reset (the thank-you, a fresh form) goes back to the first step. A dialog closed before that
 *    keeps the step and what was typed: in the page's memory only, nothing is stored (§3.5).
 */

import '../css/steps.css'

const webx = (window.webx ??= {})

const own = (form, selector) =>
  Array.from(form.querySelectorAll(selector)).filter((el) => el.closest('form') === form)

const submitsOf = (form) =>
  own(form, 'button:not([type]), button[type=submit], input[type=submit]').filter(
    (button) => !button.closest('[data-webx-step]'),
  )

const text = (template, words) =>
  Object.entries(words).reduce(
    (line, [name, value]) => line.split(`:${name}`).join(value),
    template,
  )

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

/** The box that scrolls the form: a dialog's body, the dialog itself — or none, the page. */
function scroller(el) {
  const dialog = el.closest('dialog')
  for (let node = el.parentElement; node && dialog?.contains(node); node = node.parentElement) {
    if (
      node.scrollHeight > node.clientHeight &&
      /auto|scroll/.test(getComputedStyle(node).overflowY)
    )
      return node
  }
  return dialog
}

export function steps(el) {
  if (!(el instanceof HTMLFormElement)) return undefined
  const all = own(el, '[data-webx-step]')
  if (!all.length) return undefined

  const bar = own(el, '.webx-form-steps')[0] ?? null
  const alert = own(el, '.webx-form__error')[0] ?? null
  const check = el.getAttribute('data-webx-check')
  const words = {
    step: el.getAttribute('data-webx-steps-count') ?? 'Step :current of :total',
    back: el.getAttribute('data-webx-steps-return') ?? 'Back to step :number: :title',
    fix: el.getAttribute('data-webx-steps-fix') ?? 'Please check step :number: :message',
  }
  // The form's own buttons, and where each stands, to go back there off the last step.
  const homes = new Map(
    submitsOf(el).map((button) => {
      const mark = document.createComment('webx-step-submit')
      button.before(mark)
      return [button, mark]
    }),
  )
  const added = []
  const on = (target, type, listener, capture = false) => {
    target.addEventListener(type, listener, capture)
    added.push(() => target.removeEventListener(type, listener, capture))
  }

  const shown = () => all.filter((step) => !step.hasAttribute('data-webx-hidden'))
  const labelOf = (step) =>
    step.getAttribute('data-webx-step-label') ||
    text(words.step, { current: String(all.indexOf(step) + 1), total: String(all.length) })
  // The page came back from the form without the script: the step of its first error.
  let current = all.find((step) => step.querySelector('[data-webx-field].is-invalid')) ?? all[0]
  let checking = false

  const draw = () => {
    const list = shown()
    if (!list.includes(current)) current = list[0] ?? all[0]
    const at = list.indexOf(current)
    const last = at === list.length - 1

    for (const step of all) {
      const here = step === current
      step.hidden = !here
      step.classList.toggle('is-current', here)
      const nav = step.querySelector('.webx-form-step__nav')
      if (nav) nav.hidden = !here
      const back = step.querySelector('[data-webx-step-back]')
      if (back) back.hidden = at <= 0
      const next = step.querySelector('[data-webx-step-next]')
      if (next) next.hidden = last
      const count = step.querySelector('.webx-form-step__count')
      if (count) {
        count.hidden = !here
        count.textContent = here
          ? text(words.step, { current: String(at + 1), total: String(list.length) })
          : ''
      }
    }

    // The form's buttons: in the nav of the last step, beside «Back»; off it, home and hidden.
    const nav = current.querySelector('.webx-form-step__nav')
    for (const [button, mark] of homes) {
      if (last && nav) nav.append(button)
      else mark.after(button)
      button.hidden = !last
    }

    if (bar) {
      bar.hidden = list.length < 2
      bar.replaceChildren(
        ...list.map((step, index) => {
          const item = document.createElement('li')
          item.className = 'webx-form-steps__step'
          // On each part: a rule of the widgets is one flat class (THEMES §8).
          const state = index === at ? 'is-current' : index < at ? 'is-done' : null
          if (index <= at) item.classList.add('is-reached')
          if (index === at) item.setAttribute('aria-current', 'step')
          const number = document.createElement('span')
          number.className = 'webx-form-steps__number'
          number.textContent = String(index + 1)
          const label = document.createElement('span')
          label.className = 'webx-form-steps__label'
          label.textContent = labelOf(step)
          for (const part of [item, number, label]) if (state) part.classList.add(state)
          let box = item
          if (index < at) {
            box = document.createElement('button')
            box.type = 'button'
            box.className = 'webx-form-steps__button'
            box.setAttribute(
              'aria-label',
              text(words.back, { number: String(index + 1), title: labelOf(step) }),
            )
            box.addEventListener('click', () => go(step))
            item.append(box)
          }
          box.append(number, label)
          return item
        }),
      )
    }
  }

  /** The step's title takes the focus — read out by itself — and the form's top comes in sight. */
  const arrive = () => {
    const title =
      current.querySelector('.webx-form-step__title') ??
      current.querySelector('.webx-form-step__count')
    if (title && !title.hasAttribute('tabindex')) title.setAttribute('tabindex', '-1')
    title?.focus({ preventScroll: true })
    const box = scroller(el)
    const top = el.getBoundingClientRect().top
    if (box) {
      const above = top - box.getBoundingClientRect().top
      if (above < 0) box.scrollTop += above
    } else if (top < 0) {
      el.scrollIntoView?.({ block: 'start', behavior: reduced() ? 'auto' : 'smooth' })
    }
  }

  /** To another step: the event first, which may stop it — but not an error's way back. */
  const go = (step, forced = false) => {
    if (!step || step === current) return false
    const list = shown()
    const detail = {
      from: list.indexOf(current) + 1,
      to: list.indexOf(step) + 1,
      name: step.getAttribute('data-webx-step'),
    }
    const event = new CustomEvent('webx:form:step', {
      bubbles: true,
      cancelable: !forced,
      detail,
    })
    if (!el.dispatchEvent(event) && !forced) return false
    current = step
    draw()
    arrive()
    return true
  }

  const next = async () => {
    if (checking) return
    const list = shown()
    const after = list[list.indexOf(current) + 1]
    if (!after) return
    const api = webx.formOf?.(el)
    const fields = Array.from(current.querySelectorAll('[data-webx-field]')).filter(
      (field) => field.closest('form') === el,
    )
    if (api && !api.verify(fields)) return

    if (check && api) {
      const step = current
      const data = new FormData(el)
      // Files are the browser's to measure: megabytes on every «Next» would be for nothing.
      for (const name of new Set(data.keys())) {
        const values = data.getAll(name)
        if (values.some((value) => value instanceof File)) {
          data.delete(name)
          for (const value of values) if (!(value instanceof File)) data.append(name, value)
        }
      }
      data.set('webx_step', step.getAttribute('data-webx-step'))
      checking = true
      const button = step.querySelector('[data-webx-step-next]')
      button?.setAttribute('aria-disabled', 'true')
      api.clear()
      let answer
      try {
        answer = await api.ask(check, data)
      } finally {
        checking = false
        button?.removeAttribute('aria-disabled')
      }
      if (!answer || current !== step) return
      if (answer.status < 200 || answer.status >= 300) {
        api.reject(answer)
        return
      }
    }
    go(after)
  }

  on(el, 'click', (event) => {
    const target = event.target instanceof Element ? event.target : null
    if (target?.closest('[data-webx-step-next]')) {
      event.preventDefault()
      next()
    } else if (target?.closest('[data-webx-step-back]')) {
      event.preventDefault()
      const list = shown()
      go(list[list.indexOf(current) - 1])
    }
  })

  // Enter in a field submits the form; off the last step it is «Next». Caught before form.js's.
  on(
    el.ownerDocument,
    'submit',
    (event) => {
      if (event.target !== el) return
      const list = shown()
      if (list.indexOf(current) === list.length - 1) return
      event.preventDefault()
      event.stopImmediatePropagation()
      next()
    },
    true,
  )

  // An error on a step not shown: that step, and the alert says which, for a reader of the screen.
  on(el, 'webx:form:reveal', (event) => {
    const step = event.target instanceof Element ? event.target.closest('[data-webx-step]') : null
    if (!step || !all.includes(step) || step === current) return
    const message = event.detail?.field?.querySelector('.webx-form__error, .webx-field__error')
    go(step, true)
    // «Edit» of a summary (summary.js) brings the step back to change an answer: nothing is wrong.
    if (alert && !event.detail?.edit) {
      const line = document.createElement('p')
      line.className = 'webx-form__refusal'
      line.textContent = text(words.fix, {
        number: String(shown().indexOf(step) + 1),
        message: message?.textContent?.trim() || labelOf(step),
      })
      let box = alert.querySelector('.webx-form__refusals')
      if (!box) {
        box = document.createElement('div')
        box.className = 'webx-form__refusals'
        alert.append(box)
      }
      box.prepend(line)
    }
  })

  // The thank-you, a fresh form: the first step again, once form.js has read what is shown.
  on(el, 'reset', () => {
    current = all[0]
    queueMicrotask(draw)
  })

  // A condition shows or hides a step (form.js): the count and the indicator follow.
  const watch = new MutationObserver(draw)
  for (const step of all)
    watch.observe(step, { attributes: true, attributeFilter: ['data-webx-hidden'] })

  draw()

  return () => {
    added.splice(0).forEach((remove) => remove())
    watch.disconnect()
    for (const [button, mark] of homes) {
      mark.after(button)
      mark.remove()
      button.hidden = false
    }
    for (const step of all) {
      step.hidden = false
      step.classList.remove('is-current')
      const nav = step.querySelector('.webx-form-step__nav')
      if (nav) nav.hidden = true
      const count = step.querySelector('.webx-form-step__count')
      if (count) count.hidden = true
    }
    if (bar) {
      bar.hidden = true
      bar.replaceChildren()
    }
  }
}

webx.widget?.('steps', 'form.webx-form--steps', steps)
