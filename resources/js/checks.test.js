import { afterEach, describe, expect, it, vi } from 'vitest'

/*
 * Spec §17.5, W6.3: the checks of FormRules in the browser — when they run, what each rule
 * refuses and lets through, the server's order (`required` first, `bail`, `nullable`), the phone
 * mask with a paste, the files dropped, listed, removed and refused before anything is sent.
 */

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  vi.restoreAllMocks()
  delete globalThis.fetch
  delete globalThis.DataTransfer
})

async function start(html, ...scripts) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./form.js')
  for (const script of scripts) await import(`./${script}.js`)
  return document.querySelector('form')
}

const settle = async () => {
  for (let i = 0; i < 10; i++) await new Promise((resolve) => setTimeout(resolve, 0))
}

const json = (status, data) =>
  new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } })

const said = (rule, extra = {}) => ({ rule, message: `${rule} failed`, ...extra })

/** A field as `<x-webx-field>` prints it, with the rules FormRules gave it. */
const field = (key, rules, control) => {
  const id = `field-${key.replace(/\W+/g, '-')}`
  return `
  <div class="webx-field" data-webx-field="${key}" data-webx-rules='${JSON.stringify(rules)}'>
    <label for="${id}">${key}</label>
    ${control ?? `<input type="text" id="${id}" name="${key.replace(/\.([^.]+)/g, '[$1]')}">`}
    <p class="webx-field__error" id="${id}-error" hidden></p>
  </div>`
}

const shell = (
  fields,
  buttons = '<button type="submit" class="webx-form__submit">Send</button>',
) => `
  <form id="form-1" class="webx-form" action="/send" method="post" data-webx-send>
    <div class="webx-form__body">${fields}${buttons}</div>
    <progress class="webx-form__progress" max="100" value="0" hidden></progress>
    <div class="webx-form__message" role="status" aria-live="polite"></div>
    <div class="webx-form__error" role="alert" data-webx-failed="Could not send." data-webx-expired="Reload."></div>
  </form>`

const input = (key) => document.querySelector(`[data-webx-field="${key}"] input`)
const error = (key) => document.querySelector(`[data-webx-field="${key}"] .webx-field__error`)
const type = (key, value) => {
  const el = input(key)
  el.focus()
  el.value = value
  el.dispatchEvent(new Event('input', { bubbles: true }))
}
const leave = (key) => input(key).dispatchEvent(new FocusEvent('focusout', { bubbles: true }))

/** A check of one rule on one value: what the field says after a submit. */
async function verdict(rules, value) {
  const form = await start(shell(field('f', rules)))
  globalThis.fetch = vi.fn(async () => json(200, { ok: true }))
  input('f').value = value
  form.querySelector('.webx-form__submit').click()
  await settle()
  return error('f').hidden ? null : error('f').textContent
}

describe('when the browser checks', () => {
  it('turns the browser’s bubbles off while it is there, and gives them back', async () => {
    const form = await start(shell(field('f', [said('required')])))
    expect(form.noValidate).toBe(true)
    window.webx.unmount()
    expect(form.noValidate).toBe(false)
  })

  it('says nothing of a field only focused and left; a touched one is checked when left', async () => {
    await start(shell(field('name', [said('required'), said('min', { as: 'string', value: 3 })])))

    input('name').focus()
    leave('name')
    expect(error('name').hidden).toBe(true)

    type('name', 'ab')
    // Not while typing: only when the field is left.
    expect(error('name').hidden).toBe(true)
    leave('name')
    expect(error('name').hidden).toBe(false)
    expect(error('name').textContent).toBe('min failed')
    expect(input('name').getAttribute('aria-invalid')).toBe('true')
  })

  it('takes the error away the moment the field is right, and keeps it while it is not', async () => {
    await start(shell(field('name', [said('required'), said('min', { as: 'string', value: 3 })])))
    type('name', 'a')
    leave('name')

    type('name', '')
    expect(error('name').textContent).toBe('required failed')
    type('name', 'abc')
    expect(error('name').hidden).toBe(true)
    expect(input('name').hasAttribute('aria-invalid')).toBe(false)
  })

  it('checks every field on submit, sends nothing while one is wrong, and focuses the first', async () => {
    const form = await start(
      shell(field('name', [said('required')]) + field('email', [said('required'), said('email')])),
    )
    globalThis.fetch = vi.fn()
    const sent = []
    form.addEventListener('webx:form:submit', () => sent.push(true))
    input('email').value = 'nope'

    form.querySelector('.webx-form__submit').click()
    await settle()

    expect(globalThis.fetch).not.toHaveBeenCalled()
    expect(sent).toHaveLength(0)
    expect(error('name').textContent).toBe('required failed')
    expect(error('email').textContent).toBe('email failed')
    expect(document.activeElement).toBe(input('name'))
  })

  it('sends once everything is right, and a button with formnovalidate sends without asking', async () => {
    const form = await start(
      shell(
        field('name', [said('required')]),
        '<button type="submit" class="webx-form__submit">Send</button><button type="submit" class="raw" formnovalidate>Ask the server</button>',
      ),
    )
    globalThis.fetch = vi.fn(async () => json(422, { errors: { name: ['The server says no.'] } }))

    form.querySelector('.raw').click()
    await settle()
    expect(globalThis.fetch).toHaveBeenCalledTimes(1)
    expect(error('name').textContent).toBe('The server says no.')

    input('name').value = 'Ann'
    form.querySelector('.webx-form__submit').click()
    await settle()
    expect(globalThis.fetch).toHaveBeenCalledTimes(2)
  })

  it('lets an error of the server go at the first change — the browser cannot tell it is right', async () => {
    const form = await start(shell(field('email', [said('email')])))
    globalThis.fetch = vi.fn(async () => json(422, { errors: { email: ['Taken.'] } }))
    input('email').value = 'a@b.c'
    form.querySelector('.webx-form__submit').click()
    await settle()
    expect(error('email').textContent).toBe('Taken.')

    type('email', 'b@b.c')
    expect(error('email').hidden).toBe(true)
  })

  it('keeps an error of the server when its field is only left: the link under it must not jump away', async () => {
    const form = await start(shell(field('email', [said('email')])))
    globalThis.fetch = vi.fn(async () =>
      json(422, { errors: { email: ['Wrong e-mail or password.'] } }),
    )
    type('email', 'a@b.c')
    form.querySelector('.webx-form__submit').click()
    await settle()
    expect(error('email').textContent).toBe('Wrong e-mail or password.')

    // The press on «Forgot your password?» under the form takes the focus out of the field first.
    leave('email')
    expect(error('email').hidden).toBe(false)
    expect(error('email').textContent).toBe('Wrong e-mail or password.')

    type('email', 'b@b.c')
    expect(error('email').hidden).toBe(true)
  })

  it('checks a confirmation against its password, and the password again as the confirmation changes', async () => {
    await start(
      shell(
        field(
          'password',
          [said('required'), said('confirmed', { other: null })],
          '<input type="password" name="password">',
        ) +
          field(
            'password_confirmation',
            [],
            '<input type="password" name="password_confirmation">',
          ),
      ),
    )
    type('password', 'secret12')
    leave('password')
    expect(error('password').textContent).toBe('confirmed failed')

    type('password_confirmation', 'secret12')
    expect(error('password').hidden).toBe(true)
  })
})

describe('what each rule refuses', () => {
  it.each([
    ['required', [said('required')], '', true],
    ['required with spaces only — trimmed, as the server trims', [said('required')], '   ', true],
    ['email', [said('email')], 'ann@example', false],
    ['email', [said('email')], 'ann example.com', true],
    ['url', [said('url')], 'https://example.com/a?b', false],
    ['url', [said('url')], 'example.com', true],
    ['numeric', [said('numeric')], '-1.5e3', false],
    ['numeric', [said('numeric')], '1,5', true],
    ['integer', [said('integer')], '42', false],
    ['integer', [said('integer')], '4.2', true],
    [
      'min of a string counts letters, not bytes',
      [said('min', { as: 'string', value: 3 })],
      'żółw'.slice(0, 3),
      false,
    ],
    ['max of a number', [said('numeric'), said('max', { as: 'numeric', value: 10 })], '11', true],
    ['between', [said('between', { as: 'string', value: [2, 4] })], 'abcde', true],
    ['size', [said('size', { as: 'string', value: 2 })], 'ab', false],
    [
      'regex',
      [said('regex', { source: '^\\+[1-9][0-9 ]{6,18}$', flags: '' })],
      '+48 512 345 678',
      false,
    ],
    ['regex', [said('regex', { source: '^\\d+$', flags: '' })], '12a', true],
    [
      'regex JavaScript cannot compile — the server’s alone',
      [said('regex', { source: '(?<', flags: '' })],
      'x',
      false,
    ],
    ['not_regex', [said('not_regex', { source: 'admin', flags: 'i' })], 'ADMIN', true],
    ['in', [said('in', { values: ['a', 'b'] })], 'c', true],
    ['date that cannot be', [said('date')], '2026-02-30', true],
    ['date PHP reads and JavaScript would not', [said('date')], 'next monday', false],
    ['after a literal date', [said('after', { value: '2026-01-01' })], '2026-01-01', true],
    ['before or on it', [said('before_or_equal', { value: '2026-01-01' })], '2026-01-01', false],
  ])('%s', async (_, rules, value, refused) => {
    expect((await verdict(rules, value)) !== null).toBe(refused)
  })

  it('runs every rule of a field sent empty — unless it is nullable, as the server does', async () => {
    expect(await verdict([said('email')], '')).toBe('email failed')
    expect(await verdict([{ rule: 'nullable' }, said('email')], '')).toBeNull()
  })

  it('stops after a failed required, and after the first failure under bail; else says them all', async () => {
    expect(await verdict([said('required'), said('email')], '')).toBe('required failed')
    const all = [said('email'), said('min', { as: 'string', value: 5 })]
    expect(await verdict(all, 'ab')).toBe('email failed min failed')
    expect(await verdict([{ rule: 'bail' }, ...all], 'ab')).toBe('email failed')
  })

  it('wants a tick for accepted and one ticked box of a group for required', async () => {
    const form = await start(
      shell(
        field('agree', [said('accepted')], '<input type="checkbox" name="agree" value="1">') +
          field(
            'extras',
            [
              said('required'),
              said('min', { as: 'array', value: 2 }),
              said('in', {
                values: ['a', 'b'],
                each: true,
                item: 'extras.0',
                message: 'The extras.0 is not offered.',
              }),
            ],
            '<input type="checkbox" name="extras[]" value="a"><input type="checkbox" name="extras[]" value="z">',
          ),
      ),
    )
    globalThis.fetch = vi.fn()
    form.querySelector('.webx-form__submit').click()
    await settle()
    expect(error('agree').textContent).toBe('accepted failed')
    expect(error('extras').textContent).toBe('required failed')

    form.querySelector('[name=agree]').checked = true
    form.querySelectorAll('[name="extras[]"]').forEach((box) => (box.checked = true))
    form.querySelector('.webx-form__submit').click()
    await settle()
    expect(error('agree').hidden).toBe(true)
    // Each item by its own index, as the server names it.
    expect(error('extras').textContent).toBe('The extras.1 is not offered.')
  })
})

describe('the phone mask', () => {
  const phone = shell(field('phone', [], '<input type="tel" name="phone" data-webx-mask="phone">'))

  it('writes an international number in groups of digits, by the country code', async () => {
    const { format } = await import('./mask.js')
    expect(format('+48512345678')).toBe('+48 512 345 678')
    expect(format('+12125550123')).toBe('+1 212 555 0123')
    expect(format('+79123456789')).toBe('+7 912 345 6789')
    expect(format('+380441234567')).toBe('+380 441 234 567')
    expect(format('0048 512-345-678')).toBe('+48 512 345 678')
    expect(format('+4851234567890123456')).toBe('+48 512 345 678 9012')
    // Someone's local number, pasted without +: left alone.
    expect(format('(0512) 345 678')).toBe('(0512) 345 678')
  })

  it('keeps a paste and an autofill as they come and puts them in order', async () => {
    await start(phone, 'mask')
    const tel = input('phone')
    tel.focus()
    tel.value = '+48 (512) 345-678'
    tel.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertFromPaste' }))
    expect(tel.value).toBe('+48 512 345 678')

    tel.value = '+380441234567'
    tel.dispatchEvent(new Event('change', { bubbles: true }))
    expect(tel.value).toBe('+380 441 234 567')
  })

  it('starts with + when a digit is typed into an empty field, and backspace over a space takes a digit', async () => {
    await start(phone, 'mask')
    const tel = input('phone')
    tel.focus()
    tel.value = '4'
    tel.setSelectionRange(1, 1)
    tel.dispatchEvent(
      new InputEvent('input', { bubbles: true, inputType: 'insertText', data: '4' }),
    )
    expect(tel.value).toBe('+4')

    tel.value = '+48 512'
    tel.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText' }))
    // The caret after the space: backspace removes the space, the mask takes the 8 before it.
    tel.value = '+48512'
    tel.setSelectionRange(3, 3)
    tel.dispatchEvent(
      new InputEvent('input', { bubbles: true, inputType: 'deleteContentBackward' }),
    )
    expect(tel.value).toBe('+45 12')
  })

  it('formats the value the page came back with, and the check sees the formatted value', async () => {
    const form = await start(
      shell(
        field(
          'phone',
          [said('regex', { source: '^\\+[1-9][0-9 ]{6,18}$', flags: '' })],
          '<input type="tel" name="phone" data-webx-mask="phone" value="+48512345678">',
        ),
      ),
      'mask',
    )
    expect(input('phone').value).toBe('+48 512 345 678')
    globalThis.fetch = vi.fn(async () => json(200, { ok: true }))
    form.querySelector('.webx-form__submit').click()
    await settle()
    expect(error('phone').hidden).toBe(true)
    expect(globalThis.fetch).toHaveBeenCalledTimes(1)
  })
})

describe('the files of a field', () => {
  /** jsdom has no DataTransfer: one that builds the list an input holds. */
  class FakeTransfer {
    constructor() {
      this.list = []
      this.items = { add: (file) => this.list.push(file) }
    }
    get files() {
      return this.list
    }
  }

  /** jsdom's input takes no FileList from a page: the property, as a browser sets it. */
  const settable = (el) => {
    let files = []
    Object.defineProperty(el, 'files', {
      configurable: true,
      get: () => files,
      set: (list) => {
        files = Array.from(list)
      },
    })
  }

  const filesField = (rules, multiple = 'multiple') =>
    shell(
      field(
        'docs',
        rules,
        `<span class="webx-field__file" data-webx-file='{"none":"No file chosen","many":"Files chosen: :count"}' data-webx-files='{"remove":"Remove"}'>
           <input type="file" name="docs${multiple ? '[]' : ''}" ${multiple}>
           <span class="webx-field__file-name">No file chosen</span>
         </span>
         <span class="webx-field__drop" hidden>or drop files here</span>
         <ul class="webx-field__files" hidden></ul>`,
      ),
    )

  async function mount(rules, multiple) {
    globalThis.DataTransfer = FakeTransfer
    document.body.innerHTML = filesField(rules, multiple)
    settable(document.querySelector('input[type=file]'))
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')
    await import('./form.js')
    await import('./field.js')
    await import('./files.js')
    return document.querySelector('form')
  }

  const drop = (target, files) => {
    const event = new Event('drop', { bubbles: true, cancelable: true })
    event.dataTransfer = { files, types: ['Files'] }
    target.dispatchEvent(event)
    return event
  }

  const rows = () => Array.from(document.querySelectorAll('.webx-field__files-item'))

  it('takes dropped files, lists them with their sizes, and adds to what was chosen', async () => {
    const form = await mount([])
    const zone = form.querySelector('[data-webx-field="docs"]')
    expect(form.querySelector('.webx-field__drop').hidden).toBe(false)

    const event = drop(zone, [new File(['x'.repeat(2048)], 'a.pdf', { type: 'application/pdf' })])
    expect(event.defaultPrevented).toBe(true)
    drop(zone, [new File(['y'], 'b.png', { type: 'image/png' })])

    expect(rows().map((row) => row.querySelector('.webx-field__files-name').textContent)).toEqual([
      'a.pdf',
      'b.png',
    ])
    expect(rows()[0].querySelector('.webx-field__files-size').textContent).toMatch(/2.*kB/)
    expect(form.querySelector('input[type=file]').files).toHaveLength(2)
    // The line beside the button follows the input (field.js).
    expect(form.querySelector('.webx-field__file-name').textContent).toBe('Files chosen: 2')
    expect(rows()[1].querySelector('.webx-field__files-remove').getAttribute('aria-label')).toBe(
      'Remove b.png',
    )
  })

  it('removes one before sending, the input holding exactly what the list shows', async () => {
    const form = await mount([])
    drop(form.querySelector('[data-webx-field="docs"]'), [
      new File(['1'], 'a.pdf'),
      new File(['2'], 'b.pdf'),
    ])

    rows()[0].querySelector('.webx-field__files-remove').click()

    expect(rows().map((row) => row.querySelector('.webx-field__files-name').textContent)).toEqual([
      'b.pdf',
    ])
    expect(
      Array.from(form.querySelector('input[type=file]').files).map((file) => file.name),
    ).toEqual(['b.pdf'])
    expect(document.activeElement).toBe(rows()[0].querySelector('.webx-field__files-remove'))
  })

  it('refuses a file of the wrong type or too big as soon as it is dropped, marking its row', async () => {
    const form = await mount([
      said('mimes', {
        values: ['pdf'],
        each: true,
        item: 'docs.0',
        message: 'The docs.0 must be a PDF.',
      }),
      said('max', {
        as: 'file',
        value: 1,
        each: true,
        item: 'docs.0',
        message: 'The docs.0 is over 1 kB.',
      }),
    ])
    globalThis.fetch = vi.fn()
    drop(form.querySelector('[data-webx-field="docs"]'), [
      new File(['x'], 'ok.pdf'),
      new File(['x'], 'photo.png'),
      new File(['x'.repeat(4096)], 'big.pdf'),
    ])

    expect(error('docs').textContent).toBe('The docs.1 must be a PDF. The docs.2 is over 1 kB.')
    expect(rows().map((row) => row.classList.contains('is-invalid'))).toEqual([false, true, true])

    form.querySelector('.webx-form__submit').click()
    await settle()
    expect(globalThis.fetch).not.toHaveBeenCalled()

    rows()[2].querySelector('.webx-field__files-remove').click()
    rows()[1].querySelector('.webx-field__files-remove').click()
    expect(error('docs').hidden).toBe(true)
  })

  it('takes one file in place of the other in a field of one, and lets everything go when unmounted', async () => {
    const form = await mount([], '')
    const zone = form.querySelector('[data-webx-field="docs"]')
    drop(zone, [new File(['1'], 'a.pdf')])
    drop(zone, [new File(['2'], 'b.pdf'), new File(['3'], 'c.pdf')])
    expect(rows().map((row) => row.querySelector('.webx-field__files-name').textContent)).toEqual([
      'b.pdf',
    ])

    window.webx.unmount()
    expect(document.querySelector('.webx-field__files').hidden).toBe(true)
    expect(document.querySelector('.webx-field__drop').hidden).toBe(true)
  })
})
