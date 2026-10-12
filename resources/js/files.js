/*
 * The files of a field (spec §17.5). Its own file, claimed by `<x-webx-field type="file">`.
 *
 * On top of the input W6.1 drew, which stays the control a keyboard and a screen reader use:
 *
 *  - files dropped on the field are taken as if chosen (and added to the chosen, when it takes
 *    several — choosing again adds rather than replaces);
 *  - the chosen are listed under it — the name, the size in the page's language, a picture's
 *    preview — each with a button that removes it before sending;
 *  - what changed is told by a `change` on the input: the line beside the button follows it, the
 *    form checks the type and the size by the rules of the server (form.js), and marks the files
 *    it refused here (`is-invalid` on their row).
 *
 * The input's FileList is set through DataTransfer. A browser without it keeps what it has: the
 * input as the browser draws it, no list, no dropping.
 */

import '../css/files.css'

const webx = (window.webx ??= {})

const same = (a, b) => a.name === b.name && a.size === b.size && a.lastModified === b.lastModified

/** `1.2 MB`, `340 kB` in the page's language; no word of ours. */
export function size(bytes, locale = document.documentElement.lang || undefined) {
  const [unit, value] =
    bytes >= 1024 * 1024
      ? ['megabyte', bytes / 1024 / 1024]
      : bytes >= 1024
        ? ['kilobyte', bytes / 1024]
        : ['byte', bytes]
  try {
    return new Intl.NumberFormat(locale, {
      style: 'unit',
      unit,
      // 'short' says '70 byte' in English; the narrow byte is 'B' everywhere.
      unitDisplay: unit === 'byte' ? 'narrow' : 'short',
      maximumFractionDigits: value < 10 && unit !== 'byte' ? 1 : 0,
    }).format(value)
  } catch {
    return `${Math.round(value)} ${unit === 'byte' ? 'B' : unit === 'kilobyte' ? 'kB' : 'MB'}`
  }
}

export function files(root) {
  const input = root.querySelector('input[type=file]')
  const field = root.closest('[data-webx-field]') ?? root.parentElement
  const list = field?.querySelector('.webx-field__files')
  const drop = field?.querySelector('.webx-field__drop')
  if (!input || !list || typeof DataTransfer !== 'function') return undefined

  let words = {}
  try {
    words = JSON.parse(root.getAttribute('data-webx-files') || '{}')
  } catch {
    // The default below.
  }

  let chosen = Array.from(input.files ?? [])
  let previews = []
  let ours = false

  const forget = () => {
    previews.forEach((url) => URL.revokeObjectURL(url))
    previews = []
  }

  const draw = () => {
    forget()
    list.replaceChildren(
      ...chosen.map((file, index) => {
        const row = document.createElement('li')
        row.className = 'webx-field__files-item'
        row.dataset.webxFileIndex = String(index)

        if (/^image\//.test(file.type) && typeof URL.createObjectURL === 'function') {
          const picture = document.createElement('img')
          picture.className = 'webx-field__files-preview'
          picture.alt = ''
          picture.src = URL.createObjectURL(file)
          previews.push(picture.src)
          row.append(picture)
        }

        const name = document.createElement('span')
        name.className = 'webx-field__files-name'
        name.textContent = file.name
        const weight = document.createElement('span')
        weight.className = 'webx-field__files-size'
        weight.textContent = size(file.size)
        const remove = document.createElement('button')
        remove.type = 'button'
        remove.className = 'webx-field__files-remove'
        // The visible word starts the name (WCAG 2.5.3); the file's name tells one from another.
        remove.textContent = words.remove || 'Remove'
        remove.setAttribute('aria-label', `${remove.textContent} ${file.name}`)
        remove.addEventListener('click', () => {
          chosen.splice(index, 1)
          set()
          const next =
            list.querySelectorAll('.webx-field__files-remove')[index] ??
            list.querySelectorAll('.webx-field__files-remove')[index - 1]
          ;(next ?? input).focus()
        })

        row.append(name, weight, remove)
        return row
      }),
    )
    list.hidden = chosen.length === 0
  }

  /** The input holds exactly what the list shows, and everyone is told. */
  const set = () => {
    const transfer = new DataTransfer()
    chosen.forEach((file) => transfer.items.add(file))
    ours = true
    input.files = transfer.files
    // Drawn first: the form's check that follows marks the rows of the files it refuses.
    draw()
    input.dispatchEvent(new Event('change', { bubbles: true }))
    ours = false
  }

  const take = (added) => {
    if (!added.length) return
    chosen = input.multiple
      ? [...chosen, ...added.filter((file) => !chosen.some((one) => same(one, file)))]
      : [added[0]]
  }

  const picked = () => {
    if (ours) return
    // The picker replaced what was there; for a field of several, what was there stays.
    const now = Array.from(input.files ?? [])
    const before = chosen
    chosen = []
    if (input.multiple) take(before)
    take(now)
    if (input.multiple && before.length) set()
    else draw()
  }

  const over = (event) => {
    if (input.disabled || !event.dataTransfer?.types?.includes('Files')) return
    event.preventDefault()
    root.classList.add('is-dragging')
  }
  const out = (event) => {
    if (!field.contains(event.relatedTarget)) root.classList.remove('is-dragging')
  }
  const dropped = (event) => {
    if (input.disabled || !event.dataTransfer?.files?.length) return
    event.preventDefault()
    root.classList.remove('is-dragging')
    take(Array.from(event.dataTransfer.files))
    set()
  }

  input.addEventListener('change', picked)
  field.addEventListener('dragover', over)
  field.addEventListener('dragleave', out)
  field.addEventListener('drop', dropped)
  if (drop) drop.hidden = false
  draw()

  return () => {
    input.removeEventListener('change', picked)
    field.removeEventListener('dragover', over)
    field.removeEventListener('dragleave', out)
    field.removeEventListener('drop', dropped)
    forget()
    list.replaceChildren()
    list.hidden = true
    if (drop) drop.hidden = true
  }
}

webx.widget?.('files', '[data-webx-files]', files)
