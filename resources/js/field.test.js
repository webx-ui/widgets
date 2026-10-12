import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
})

async function start(html) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./field.js')
}

const password = `
  <span class="webx-field__password">
    <input type="password" id="p" name="p">
    <button type="button" class="webx-field__show" aria-controls="p" aria-pressed="false" aria-label="Show password" data-webx-password hidden>Show</button>
  </span>`

const file = (multiple = '') => `
  <span class="webx-field__file" data-webx-file='{"none":"No file chosen","many":"Files chosen: :count"}'>
    <input type="file" id="f" name="f" ${multiple}>
    <span class="webx-field__file-button" aria-hidden="true">Choose a file</span>
    <span class="webx-field__file-name" aria-hidden="true">No file chosen</span>
  </span>`

/** jsdom cannot pick a file: the list the input would hold, set as a browser sets it. */
const choose = (input, names) => {
  Object.defineProperty(input, 'files', {
    configurable: true,
    value: names.map((name) => new File(['x'], name)),
  })
  input.dispatchEvent(new Event('change'))
}

describe('the fields of a form', () => {
  it('shows a password and hides it again, the button keeping its name and saying its state', async () => {
    await start(password)
    const input = document.getElementById('p')
    const button = document.querySelector('[data-webx-password]')

    // Hidden without the script: it would do nothing.
    expect(button.hidden).toBe(false)

    button.click()
    expect(input.type).toBe('text')
    expect(button.getAttribute('aria-pressed')).toBe('true')
    expect(button.getAttribute('aria-label')).toBe('Show password')

    button.click()
    expect(input.type).toBe('password')
    expect(button.getAttribute('aria-pressed')).toBe('false')
  })

  it('leaves a password hidden, and its button too, when unmounted', async () => {
    await start(password)
    document.querySelector('[data-webx-password]').click()

    window.webx.unmount()

    expect(document.getElementById('p').type).toBe('password')
    expect(document.querySelector('[data-webx-password]').hidden).toBe(true)
  })

  it('names the file chosen, or how many, in the words of the page', async () => {
    await start(file('multiple'))
    const input = document.getElementById('f')
    const line = document.querySelector('.webx-field__file-name')

    choose(input, ['cv.pdf'])
    expect(line.textContent).toBe('cv.pdf')

    choose(input, ['a.jpg', 'b.jpg', 'c.jpg'])
    expect(line.textContent).toBe('Files chosen: 3')

    choose(input, [])
    expect(line.textContent).toBe('No file chosen')
  })

  it('stops listening when unmounted', async () => {
    await start(file())
    const input = document.getElementById('f')
    const line = document.querySelector('.webx-field__file-name')

    window.webx.unmount()
    choose(input, ['late.pdf'])

    expect(line.textContent).toBe('No file chosen')
  })
})
