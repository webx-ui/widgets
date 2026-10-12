/*
 * The fields of a form (spec §17.2). Its own file, claimed by `<x-webx-field>`.
 *
 * Two small things the server cannot do: "Show" on a password, which turns the input into text
 * and back (the button is hidden until this runs), and the line beside a file input in the page's
 * words — the name chosen, or how many. Everything else of a field is HTML.
 */

import '../css/field.css'

const webx = (window.webx ??= {})

export function password(button) {
  const input = document.getElementById(button.getAttribute('aria-controls') || '')
  if (!input) return undefined

  const toggle = () => {
    const shown = input.type === 'password'
    input.type = shown ? 'text' : 'password'
    button.setAttribute('aria-pressed', String(shown))
  }

  button.hidden = false
  button.addEventListener('click', toggle)

  return () => {
    button.removeEventListener('click', toggle)
    input.type = 'password'
    button.setAttribute('aria-pressed', 'false')
    button.hidden = true
  }
}

export function file(root) {
  const input = root.querySelector('input[type=file]')
  const line = root.querySelector('.webx-field__file-name')
  if (!input || !line) return undefined

  let words = {}
  try {
    words = JSON.parse(root.getAttribute('data-webx-file') || '{}')
  } catch {
    // The defaults below.
  }

  const show = () => {
    const files = input.files ? Array.from(input.files) : []
    line.textContent =
      files.length === 0
        ? words.none || ''
        : files.length === 1
          ? files[0].name
          : String(words.many || ':count').replace(':count', String(files.length))
  }

  input.addEventListener('change', show)
  // A browser that keeps the choice over a reload or a step back has it already.
  show()

  return () => input.removeEventListener('change', show)
}

webx.widget?.('field-password', '[data-webx-password]', password)
webx.widget?.('field-file', '[data-webx-file]', file)
