/*
 * The shell of a form (spec §17.3, §17.4). Its own file, claimed by `<x-webx-form>`.
 *
 * Everything here is on top of a form that works without it: a plain POST, a redirect back,
 * the errors under the fields. What this adds is the same in place:
 *
 *  - the submission as `fetch` with `Accept: application/json` — or `XMLHttpRequest` when a file
 *    is going, for the upload's progress;
 *  - the token from the XSRF-TOKEN cookie, not from the HTML: a page is cached with the token it
 *    was printed with (INBOX decision 8). A 419 gets a fresh one from the package's route and the
 *    request is sent again — once;
 *  - `is-sending` and `aria-busy` on the form and the buttons held back: a second press while the
 *    first is on its way sends nothing;
 *  - Laravel's 422 as it is: each error into the place its field already has (W6.1 printed it,
 *    hidden, and described the control by it), what belongs to no field into the form's alert,
 *    the focus on the first field wrong; an error goes as soon as its field is changed;
 *  - the answer of FormResponse: off to `redirect`, `html` in place of the fields, `message`
 *    under them in a live region — in a dialog in place of them, with a button that closes it;
 *    `heading_html` (a heading with the visitor's answers in it) as HTML, `heading` as text;
 *  - `again` (`.webx-form__again`): the thank-you in place of the fields and a button that brings
 *    them back empty, the focus on the first — the link to the page itself it is without this;
 *  - the checks of FormRules (§17.5) before anything is sent: a touched field when it is left,
 *    every field on submit, in the validator's own words; the browser's bubbles off
 *    (`novalidate`); a button with `formnovalidate` goes straight to the server;
 *  - `webx:form:submit` (cancelable), `webx:form:success`, `webx:form:error` on the form. A
 *    listener with something to finish first — a captcha to run — holds the form with
 *    `detail.wait(promise)`: busy from the press on, the form read again and sent when it
 *    resolves, let go as it was when it rejects.
 *
 * A form of steps (FORM_STEPS §3) is steps.js's, on top of this: `webx.formOf(form)` hands it the
 * checks, the request and the 422 of this form, so there is one of each. An error found on a step
 * that is not shown — the server's on the last press — asks for it with `webx:form:reveal`.
 */

import '../css/form.css'

const webx = (window.webx ??= {})

// Not core.js's: a module both import would be a chunk of its own, one more request on every page.
const listeners = () => {
  const added = []
  const on = (target, type, listener) => {
    target.addEventListener(type, listener)
    added.push(() => target.removeEventListener(type, listener))
  }
  on.off = () => added.splice(0).forEach((remove) => remove())
  return on
}

/** What steps.js asks of a form this script drives. */
const forms = new WeakMap()
webx.formOf = (el) => forms.get(el) ?? null

/** Where a redirect goes: the page itself, or a test's stand-in (jsdom cannot navigate). */
export const navigation = { go: (url) => window.location.assign(url) }

/** The token Laravel left in its cookie, as the header `X-XSRF-TOKEN` wants it. */
export function token() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : null
}

const own = (form, selector) =>
  Array.from(form.querySelectorAll(selector)).find((el) => el.closest('form') === form) ?? null

const fieldsOf = (form) =>
  Array.from(form.querySelectorAll('[data-webx-field]')).filter((el) => el.closest('form') === form)

const controlsOf = (field) =>
  Array.from(field.querySelectorAll('input, select, textarea')).filter((el) => el.type !== 'hidden')

/** Where the focus goes in a field: what a script drew over its controls asks for it (`data-webx-focus`). */
const focusOf = (field) => field.querySelector('[data-webx-focus]') ?? controlsOf(field)[0] ?? null

const buttonsOf = (form) =>
  Array.from(
    form.querySelectorAll('button:not([type]), button[type=submit], input[type=submit]'),
  ).filter((el) => el.closest('form') === form)

/**
 * The field an error of the validator is about: `fields.email` is the field of that name,
 * `extras.1` an item of the group `extras`, `items.0.qty` a field of its own if the form has one.
 */
export function fieldFor(form, key) {
  const fields = new Map(fieldsOf(form).map((field) => [field.dataset.webxField, field]))
  for (
    let name = key;
    name;
    name = name.includes('.') ? name.slice(0, name.lastIndexOf('.')) : ''
  ) {
    if (fields.has(name)) return fields.get(name)
  }
  return null
}

function invalid(field, messages) {
  field.classList.add('is-invalid')
  const box = field.querySelector('.webx-field__error')
  if (box) {
    box.textContent = messages.join(' ')
    box.hidden = false
  }
  // Each control of a group: `aria-invalid` on a <fieldset> is read by nobody.
  for (const control of controlsOf(field)) control.setAttribute('aria-invalid', 'true')
}

function valid(field) {
  if (!field?.classList.contains('is-invalid')) return
  field.classList.remove('is-invalid')
  const box = field.querySelector('.webx-field__error')
  if (box) {
    box.textContent = ''
    box.hidden = true
  }
  for (const control of controlsOf(field)) control.removeAttribute('aria-invalid')
}

/** The form's own alert: what belongs to no field, or why nothing could be sent. */
function refuse(form, messages) {
  const box = own(form, '.webx-form__error')
  if (!box) return
  if (messages.length === 0) return box.replaceChildren()
  // A box inside the alert, so that an empty alert takes no room and the rule stays one class.
  const list = document.createElement('div')
  list.className = 'webx-form__refusals'
  for (const message of messages) {
    const line = document.createElement('p')
    line.className = 'webx-form__refusal'
    line.textContent = message
    list.append(line)
  }
  box.replaceChildren(list)
}

function say(form, heading, message, headingHtml = null) {
  const box = own(form, '.webx-form__message')
  if (!box) return
  const parts = []
  if (heading || headingHtml) {
    const line = document.createElement('p')
    line.className = 'webx-form__heading'
    // The controller's own HTML, the visitor's words escaped in it: FormResponse::message(HtmlString).
    if (headingHtml) line.innerHTML = headingHtml
    else line.textContent = heading
    parts.push(line)
  }
  if (message) {
    // The site's own HTML, written by the controller that answers: FormResponse::message().
    const text = document.createElement('div')
    text.className = 'webx-form__text'
    text.innerHTML = message
    parts.push(text)
  }
  if (parts.length === 0) return box.replaceChildren()
  const answer = document.createElement('div')
  answer.className = 'webx-form__answer'
  answer.append(...parts)
  box.replaceChildren(answer)
}

function clear(form) {
  for (const field of fieldsOf(form)) valid(field)
  refuse(form, [])
  say(form, null, null)
}

const parse = (text) => {
  try {
    return { data: JSON.parse(text), json: true }
  } catch {
    // A proxy's error page, a maintenance page, a controller that answered with HTML.
    return { data: {}, json: false }
  }
}

function headers() {
  const all = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  const xsrf = token()
  if (xsrf) all['X-XSRF-TOKEN'] = xsrf
  return all
}

/** One request; `{ status, data, json, url }` whichever way it went. */
function request(action, body, progress) {
  const files = Array.from(body.values()).some((value) => value instanceof File && value.name)

  if (!files) {
    return fetch(action, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: headers(),
    }).then(async (response) => ({
      status: response.status,
      url: response.redirected ? response.url : null,
      ...parse(await response.text()),
    }))
  }

  // fetch says nothing of an upload's progress; XMLHttpRequest does.
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', action)
    xhr.withCredentials = true
    for (const [name, value] of Object.entries(headers())) xhr.setRequestHeader(name, value)
    xhr.upload.addEventListener('progress', (event) => {
      if (event.lengthComputable) progress(Math.round((event.loaded / event.total) * 100))
    })
    xhr.addEventListener('load', () =>
      resolve({
        status: xhr.status,
        url:
          xhr.responseURL && xhr.responseURL !== new URL(action, location.href).href
            ? xhr.responseURL
            : null,
        ...parse(xhr.responseText),
      }),
    )
    xhr.addEventListener('error', () => reject(new Error('network')))
    xhr.send(body)
  })
}

/** The request again, once, with a fresh token after a 419: the page was cached with a stale one. */
async function post(action, data, refresh, progress) {
  // The cookie's token is the fresh one; the HTML's may have been cached with the page, and
  // Laravel would read `_token` first.
  const send = () => {
    if (token()) data.delete('_token')
    return request(action, data, progress)
  }
  let answer = await send()
  if (answer.status === 419 && refresh) {
    await fetch(refresh, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    })
    answer = await send()
  }
  return answer
}

/*
 * The checks of FormRules (spec §17.5): the field carries its rules in `data-webx-rules`, each
 * with the line the validator would write — so this says what the server would, and only what
 * it surely would: anything it cannot know for certain it lets through to the server.
 */

const rulesOf = (field) => {
  try {
    return JSON.parse(field.getAttribute('data-webx-rules') || 'null')
  } catch {
    return null
  }
}

/** `items.0.qty` as the browser sends it: `items[0][qty]`. */
const nameOf = (key) => key.replace(/\.([^.]+)/g, '[$1]')

/** What Laravel's TrimStrings and ConvertEmptyStringsToNull leave of what was sent. */
function sent(data, name, password) {
  return data
    .getAll(name)
    .filter((value) => typeof value === 'string' || value.name)
    .map((value) => (typeof value === 'string' && !password ? value.trim() : value))
    .filter((value) => value !== '')
}

const numeric = (value) => /^\s*[+-]?(\d+(\.\d*)?|\.\d+)(e[+-]?\d+)?\s*$/i.test(String(value))
const iso = (value) => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)

function measure(check, value) {
  if (check.as === 'numeric' && numeric(value)) return Number(value)
  if (Array.isArray(value)) return value.length
  if (value instanceof File) return value.size / 1024
  return [...String(value ?? '')].length
}

function extension(file) {
  const name = file?.name ?? ''
  return name.includes('.') ? name.slice(name.lastIndexOf('.') + 1).toLowerCase() : ''
}

function passes(check, value, other) {
  const text = typeof value === 'string'
  switch (check.rule) {
    case 'required':
      return value != null && !(Array.isArray(value) && value.length === 0)
    case 'accepted':
      return ['yes', 'on', '1', 'true'].includes(value)
    case 'email':
      return text && /^[^\s@]+@[^\s@]+$/.test(value)
    case 'url':
      return text && /^[a-z][a-z0-9+.-]*:\/\/[^\s/?#]+\S*$/i.test(value)
    case 'numeric':
      return text && numeric(value)
    case 'integer':
      return text && /^\s*[+-]?(0|[1-9]\d*)\s*$/.test(value)
    case 'min':
      return measure(check, value) >= check.value
    case 'max':
      return measure(check, value) <= check.value
    case 'size':
      return measure(check, value) === check.value
    case 'between':
      return measure(check, value) >= check.value[0] && measure(check, value) <= check.value[1]
    case 'in':
      return [].concat(value ?? '').every((one) => check.values.includes(String(one)))
    case 'mimes':
    case 'extensions': {
      const list = check.values.some((one) => one === 'jpg' || one === 'jpeg')
        ? [...check.values, 'jpg', 'jpeg']
        : check.values
      return value instanceof File && list.includes(extension(value))
    }
    case 'mimetypes':
      return (
        value instanceof File &&
        check.values.some((type) =>
          type.endsWith('/*') ? value.type.startsWith(type.slice(0, -1)) : value.type === type,
        )
      )
    case 'regex':
    case 'not_regex': {
      if (!text) return false
      let pattern
      try {
        pattern = new RegExp(check.source, check.flags)
      } catch {
        return true // Not JavaScript after all: the server's alone.
      }
      return pattern.test(value) === (check.rule === 'regex')
    }
    case 'confirmed':
    case 'same':
      return other === undefined || value === other
    case 'different':
      return other === undefined || value !== other
    case 'date': {
      // Only what is surely no date: 2026-02-30. PHP's strtotime reads far more than JavaScript.
      if (!text) return false
      if (!iso(value)) return true
      const day = new Date(`${value}T00:00:00Z`)
      return day.toISOString().slice(0, 10) === value
    }
    case 'after':
    case 'before':
    case 'after_or_equal':
    case 'before_or_equal':
      if (!text) return false
      if (!iso(value)) return true
      return {
        after: value > check.value,
        before: value < check.value,
        after_or_equal: value >= check.value,
        before_or_equal: value <= check.value,
      }[check.rule]
    default:
      return true
  }
}

/**
 * The validator's order: a rule after a failed `required` or `accepted` is not run, nor any
 * after the first failure under `bail`; an empty value meets only those two — unless it was sent
 * empty and is not `nullable`, which the server holds against every rule as well.
 */
function run(checks, value, present, otherOf) {
  const said = []
  const bail = checks.some((check) => check.rule === 'bail')
  const nullable = checks.some((check) => check.rule === 'nullable')
  const empty = value == null || (Array.isArray(value) && value.length === 0)

  for (const check of checks) {
    if (!check.message) continue
    const implicit = check.rule === 'required' || check.rule === 'accepted'
    if (empty && !implicit && (!present || nullable)) continue
    if (passes(check, value, otherOf(check))) continue
    said.push(check.message)
    if (bail || implicit) break
  }
  return said
}

/** What the server would say of this field, by the rules it carries; nothing — it has none. */
export function check(form, field, data = new FormData(form)) {
  const checks = rulesOf(field)
  const control = controlsOf(field)[0]
  if (!Array.isArray(checks) || !control?.name) return []

  const values =
    control.type === 'file'
      ? Array.from(control.files ?? []).filter((file) => file.name)
      : sent(data, control.name, control.type === 'password')
  const many = control.name.endsWith('[]') || control.multiple
  // A line, a list, a number are sent even empty; nothing goes for an unticked box, a radio no
  // one chose, an empty file input.
  const present =
    !['radio', 'checkbox', 'file'].includes(control.type) && !(control.multiple && !values.length)
  const otherOf = (rule) => {
    if (!['confirmed', 'same', 'different'].includes(rule.rule)) return undefined
    const name = nameOf(rule.other ?? `${field.dataset.webxField}_confirmation`)
    if (!Array.from(form.elements).some((control) => control.name === name)) return undefined
    return sent(data, name, true)[0] ?? null
  }

  const said = run(
    checks.filter((rule) => !rule.each),
    many ? values : (values[0] ?? null),
    present,
    otherOf,
  )
  const each = checks.filter((rule) => rule.each)
  if (many && each.length) {
    values.forEach((value, index) => {
      const lines = run(each, value, true, otherOf)
      const item = each[0].item
      for (const line of lines) said.push(line.split(item).join(item.replace(/0$/, String(index))))
      // The row of a refused file in the list files.js draws.
      field
        .querySelector(`[data-webx-file-index="${index}"]`)
        ?.classList.toggle('is-invalid', lines.length > 0)
    })
  }
  return said
}

/** The fields whose rules look at this one: a password's `confirmed`, `same`, `different`. */
const watching = (form, key) =>
  fieldsOf(form).filter((field) =>
    (rulesOf(field) ?? []).some(
      (rule) =>
        ['confirmed', 'same', 'different'].includes(rule.rule) &&
        (rule.other ?? `${field.dataset.webxField}_confirmation`) === key,
    ),
  )

/*
 * When a field is shown (FORM_STEPS §4.3): `data-webx-when` / `data-webx-unless` on it — `{ name:
 * [values] }`, "or" inside a list, "and" between names, the names the validator's (`fields.purpose`).
 * One pass in the page's order: a hidden field has no value, so what was chosen in it reaches no field
 * below — the server's rule. Hidden is `hidden` and its controls `disabled`: nothing of it is sent or
 * checked. Shown again, it has what was typed in it; a `required` the server left off for the page
 * without the script comes back.
 */
const conditionOf = (el, name) => {
  try {
    return JSON.parse(el.getAttribute(name) || 'null')
  } catch {
    return null
  }
}

const holds = (condition, answers) =>
  Object.entries(condition).every(([name, values]) =>
    (answers.get(name) ?? []).some((value) => [].concat(values).map(String).includes(value)),
  )

const chosen = (field) =>
  controlsOf(field).flatMap((control) =>
    control.tagName === 'SELECT'
      ? Array.from(control.selectedOptions, (option) => option.value).filter(Boolean)
      : ['radio', 'checkbox'].includes(control.type) && control.checked
        ? [control.value]
        : [],
  )

const hiddenIn = (el) => el.closest('[data-webx-hidden]')

function show(el, shown) {
  // A step's `hidden` is steps.js's: it shows one step at a time, and counts this one out.
  if (!el.hasAttribute('data-webx-step')) el.hidden = !shown
  el.toggleAttribute('data-webx-hidden', !shown)
  for (const control of el.querySelectorAll('input, select, textarea, button')) {
    if (shown && control.hasAttribute('data-webx-off')) {
      control.disabled = false
      control.removeAttribute('data-webx-off')
    } else if (!shown && !control.disabled) {
      control.disabled = true
      control.setAttribute('data-webx-off', '')
    }
    if (control.hasAttribute('data-webx-required')) control.required = shown
  }
}

export function conditions(form) {
  const answers = new Map()
  for (const el of form.querySelectorAll(
    '[data-webx-field], [data-webx-when], [data-webx-unless]',
  )) {
    if (el.closest('form') !== form) continue
    const when = conditionOf(el, 'data-webx-when')
    const unless = conditionOf(el, 'data-webx-unless')
    if (when || unless) {
      show(
        el,
        !(el.parentElement && hiddenIn(el.parentElement)) &&
          (!when || holds(when, answers)) &&
          !(unless && holds(unless, answers)),
      )
      // Said for the page without the script: with it, the field is there only when it applies.
      for (const line of el.querySelectorAll('[data-webx-condition]')) line.hidden = true
    }
    const key = el.dataset.webxField
    if (key && !hiddenIn(el)) answers.set(key, chosen(el))
  }
}

export function form(el) {
  if (!(el instanceof HTMLFormElement)) return undefined
  const on = listeners()
  const body = own(el, '.webx-form__body')
  const bar = own(el, '.webx-form__progress')
  const done = own(el, '.webx-form__done')
  const again = own(el, '.webx-form__again')
  const refresh = el.getAttribute('data-webx-csrf')
  const dialog = el.closest('dialog')
  // An `action` input would shadow the property: the attribute is the address.
  const action = el.getAttribute('action') || location.href
  let sending = false
  let gone = false
  // The fields the browser itself found wrong.
  const refused = new WeakSet()
  // The fields the server found wrong, unchanged since: leaving one keeps its error.
  const answered = new WeakSet()

  const busy = (state) => {
    sending = state
    el.classList.toggle('is-sending', state)
    if (state) el.setAttribute('aria-busy', 'true')
    else el.removeAttribute('aria-busy')
    // Held back, not disabled: a disabled button drops the focus of the one who pressed it.
    for (const button of buttonsOf(el)) {
      if (state) button.setAttribute('aria-disabled', 'true')
      else button.removeAttribute('aria-disabled')
    }
  }

  const progress = (percent) => {
    if (!bar) return
    bar.hidden = percent === null
    bar.value = percent ?? 0
  }

  // A dialog shows the thank-you in place of the fields and a button that closes it; closed, it
  // is a fresh form again. It never closes by itself: the visitor has to have time to read.
  // `again`: the thank-you in place of the fields on the page as well, and the way back to them.
  const aside = () => {
    if (!body) return
    if (dialog && done) {
      body.hidden = true
      done.hidden = false
      if (again) again.hidden = false
      done.focus()
    } else if (again) {
      body.hidden = true
      again.hidden = false
      // The button that sent is gone with the fields: the focus goes where the way back is.
      again.focus({ preventScroll: true })
    }
  }

  const fresh = () => {
    if (!el.classList.contains('is-sent')) return false
    el.classList.remove('is-sent')
    if (body) body.hidden = false
    if (done) done.hidden = true
    if (again) again.hidden = true
    say(el, null, null)
    el.reset()
    conditions(el)
    return true
  }

  const first = () => {
    const field = fieldsOf(el)[0]
    const control = field ? focusOf(field) : null
    control?.focus({ preventScroll: true })
    control?.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
  }

  const emit = (name, detail, cancelable = false) =>
    el.dispatchEvent(new CustomEvent(`webx:form:${name}`, { bubbles: true, cancelable, detail }))

  const focusFirst = () => {
    const first = fieldsOf(el).find(
      (field) => field.classList.contains('is-invalid') && !hiddenIn(field),
    )
    const control = first ? focusOf(first) : null
    if (!control) return
    // On a step not shown: steps.js brings it before the focus goes.
    const step = first.closest('[data-webx-step]')
    if (step?.hidden)
      step.dispatchEvent(
        new CustomEvent('webx:form:reveal', { bubbles: true, detail: { field: first } }),
      )
    control.focus({ preventScroll: true })
    control.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
  }

  const succeed = (answer) => {
    const data = answer.json ? answer.data : {}
    // A controller that answered a redirect rather than JSON: the browser followed it already.
    const redirect = data.redirect || (!answer.json && answer.url) || null
    emit('success', { status: answer.status, response: data })

    if (redirect) {
      navigation.go(redirect)
      return true
    }

    if (typeof data.html === 'string' && body) {
      webx.unmount?.(body)
      body.innerHTML = data.html
      webx.mount?.(body)
      el.classList.add('is-sent')
    } else if (data.heading || data.heading_html || data.message) {
      say(el, data.heading, data.message, data.heading_html)
      el.classList.add('is-sent')
      el.reset()
      conditions(el)
      aside()
      if (!dialog)
        own(el, '.webx-form__message')?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' })
    }
    return false
  }

  const reject = (answer, quiet = false) => {
    const data = answer.json ? answer.data : {}
    // A 429 says why as well (too many attempts, wait so long): by field, or in a sentence.
    const explained = answer.status === 422 || answer.status === 429
    const errors = explained && data.errors && typeof data.errors === 'object' ? data.errors : {}
    const unplaced = []
    const byField = new Map()

    for (const [key, messages] of Object.entries(errors)) {
      const list = [].concat(messages).map(String)
      const field = fieldFor(el, key)
      if (field) byField.set(field, [...(byField.get(field) ?? []), ...list])
      else unplaced.push(...list)
    }
    for (const [field, messages] of byField) {
      invalid(field, messages)
      answered.add(field)
    }

    const box = own(el, '.webx-form__error')
    if (explained) {
      // Laravel's own sentence sums the errors up — said already where they are.
      if (unplaced.length === 0 && byField.size === 0 && data.message)
        unplaced.push(String(data.message))
    } else {
      unplaced.push(
        box?.getAttribute(answer.status === 419 ? 'data-webx-expired' : 'data-webx-failed') ?? '',
      )
    }
    refuse(el, unplaced.filter(Boolean))
    focusFirst()
    if (!quiet) emit('error', { status: answer.status, errors, message: data.message ?? null })
  }

  // The browser's checks of these fields: wrong ones said, the focus on the first; true — all right.
  const verify = (fields) => {
    const all = new FormData(el)
    let wrong = false
    for (const field of fields) {
      // Hidden by its condition: not sent, so not checked — as the server does.
      if (hiddenIn(field)) continue
      const said = check(el, field, all)
      if (said.length) {
        invalid(field, said)
        refused.add(field)
        wrong = true
      } else if (refused.has(field)) {
        // Its error was the browser's, and it is right now; one of the server's stays.
        valid(field)
        refused.delete(field)
      }
    }
    if (wrong) {
      refuse(el, [])
      say(el, null, null)
      focusFirst()
    }
    return !wrong
  }

  on(el, 'submit', async (event) => {
    event.preventDefault()
    if (sending) return

    // A button with formnovalidate sends without the browser's checks — straight to the server's.
    if (!event.submitter?.formNoValidate && !verify(fieldsOf(el))) return

    const read = () => {
      try {
        return new FormData(el, event.submitter ?? undefined)
      } catch {
        return new FormData(el)
      }
    }
    const data = read()

    // Called while the event is dispatched; a promise handed over later is not waited for.
    const waits = []
    const wait = (promise) => void waits.push(Promise.resolve(promise))
    if (!emit('submit', { data, submitter: event.submitter ?? null, wait }, true)) return

    clear(el)
    busy(true)
    let leaving = false

    try {
      if (waits.length) {
        // Rejected: the listener has said why where it belongs; the form is let go below.
        try {
          await Promise.all(waits)
        } catch {
          return
        }
        if (gone) return
        // What the listener put into the form meanwhile — a captcha's token — goes along; what it
        // added to `data` itself stays.
        const now = read()
        for (const name of new Set(now.keys())) {
          data.delete(name)
          for (const value of now.getAll(name)) data.append(name, value)
        }
      }

      const answer = await post(action, data, refresh, progress)
      if (answer.status >= 200 && answer.status < 300) leaving = succeed(answer)
      else reject(answer)
    } catch {
      reject({ status: 0, data: {}, json: false })
    } finally {
      progress(null)
      // Off to another page: the form stays held until it is gone.
      if (!leaving) busy(false)
    }
  })

  // A field is checked when it is left, if it was touched; a field already wrong is checked as it
  // is corrected and loses its error the moment it is right. One the browser cannot check — an
  // error of the server's — goes at the first change, as before.
  const touched = new WeakSet()
  const recheck = (field) => {
    const said = check(el, field)
    if (said.length) {
      invalid(field, said)
      refused.add(field)
    } else {
      valid(field)
      refused.delete(field)
    }
  }
  const changed = (event) => {
    if (event.type === 'change') conditions(el)
    const field = event.target instanceof Element ? event.target.closest('[data-webx-field]') : null
    if (!field || field.closest('form') !== el) return
    touched.add(field)
    answered.delete(field)
    // A file is checked as soon as it is chosen or dropped: before it is sent, not after.
    if (field.classList.contains('is-invalid') || (event.target.type === 'file' && rulesOf(field)))
      recheck(field)
    for (const other of watching(el, field.dataset.webxField)) {
      if (other.classList.contains('is-invalid')) recheck(other)
    }
  }
  if (again) {
    // A link to the page without the script; with it, the same form again in place.
    again.setAttribute('role', 'button')
    on(again, 'click', (event) => {
      event.preventDefault()
      if (fresh()) first()
    })
  }

  on(el, 'input', changed)
  on(el, 'change', changed)
  on(el, 'focusout', (event) => {
    const field = event.target instanceof Element ? event.target.closest('[data-webx-field]') : null
    // Moving between the radios of one group is not leaving it.
    if (!field || field.contains(event.relatedTarget) || !touched.has(field)) return
    // An error of the server stays until the field is changed: a check of the browser that lets
    // the value through cannot tell it is right, and the form under it would jump at the press.
    if (rulesOf(field) && !answered.has(field)) recheck(field)
    for (const other of watching(el, field.dataset.webxField)) {
      if (touched.has(other)) recheck(other)
    }
  })

  forms.set(el, {
    verify,
    reject: (answer) => reject(answer, true),
    clear: () => clear(el),
    // A step checked by the server (FORM_STEPS §3.4): held like a submission, nothing taken.
    async ask(url, data) {
      if (sending) return null
      busy(true)
      try {
        return await post(url, data, refresh, () => {})
      } catch {
        return { status: 0, data: {}, json: false }
      } finally {
        if (!gone) busy(false)
      }
    },
  })

  // What is chosen now decides what is shown — the page may have come back with answers.
  conditions(el)

  // The browser's bubbles off: the same errors in every language and browser. Without this
  // script the browser's own checks stay — the only ones before the round trip.
  const validated = el.noValidate
  el.noValidate = true

  let watch = null
  if (dialog) {
    watch = new MutationObserver(() => {
      if (!dialog.open) fresh()
    })
    watch.observe(dialog, { attributes: true, attributeFilter: ['open'] })
  }

  // The page came back from this form without the script having sent it — a cached page, a
  // browser that had it switched off: a dialog it stands in opens again as a modal.
  if (el.hasAttribute('data-webx-returned')) {
    if (dialog?.open && typeof webx.openDialog === 'function') {
      dialog.close()
      webx.openDialog(dialog)
    }
    if (el.classList.contains('is-sent')) aside()
    else focusFirst()
  }

  return () => {
    gone = true
    forms.delete(el)
    on.off()
    el.noValidate = validated
    watch?.disconnect()
    again?.removeAttribute('role')
    if (sending) busy(false)
  }
}

webx.widget?.('form', 'form[data-webx-send]', form)
