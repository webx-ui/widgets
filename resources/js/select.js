/*
 * A list with a search (spec §17, decision 36 of the delivery spec). Its own file, claimed by
 * `<x-webx-select searchable>`.
 *
 * The server printed a `<select>` (or, not strict, a text field): that is the list without
 * JavaScript and it stays the holder of the value — the owner listens to its `change`. Here a
 * combobox stands over it (the APG pattern: the focus stays in the field, the option chosen by
 * keys is `aria-activedescendant`): the options already loaded are filtered as the visitor types,
 * and the server, when the component has an address, is asked for the rest after a pause — the
 * request the next keystroke made stale cancelled, an answer that still comes late dropped. Its
 * answer is JSON, `{options: [{value, label, hint}], more}`; `more` adds «Show more», the next page.
 *
 * Strict: only an option is the value; leaving the field puts the chosen label back. Not strict:
 * the text field is the combobox itself and an option only fills it.
 */

import '../css/select.css'

const webx = (window.webx ??= {})

let ids = 0
const idOf = (el, prefix) => el.id || (el.id = `${prefix}-${++ids}`)

/** Lower case without accents: «Ó» finds «o» as the server's normaliser does. */
export const fold = (text) =>
  String(text ?? '')
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[’`ʼ]/g, "'")
    .toLowerCase()
    .trim()

/** The options an element holds: `<option>`s of a select or of a `<template>`, the empty one left out. */
function optionsOf(holder) {
  if (!holder) return []
  const list = holder instanceof HTMLTemplateElement ? holder.content.children : holder.children
  return Array.from(list)
    .filter((option) => option instanceof HTMLOptionElement && option.value !== '')
    .map((option) => ({
      value: option.value,
      label: option.textContent.trim(),
      hint: option.getAttribute('data-hint') || null,
      disabled: option.disabled,
    }))
}

/** What the server answered, made safe: options with a value and a label only. */
export function fromAnswer(body) {
  const list = Array.isArray(body) ? body : Array.isArray(body?.options) ? body.options : []
  return {
    options: list
      .filter((o) => o && o.value !== undefined && o.value !== null && o.label)
      .map((o) => ({
        value: String(o.value),
        label: String(o.label),
        hint: o.hint ? String(o.hint) : null,
        disabled: Boolean(o.disabled),
      })),
    more: Boolean(body?.more),
  }
}

export function select(root) {
  const native = root.querySelector('.webx-select__native')
  const status = root.querySelector('.webx-select__status')
  if (!native) return

  const strict = root.getAttribute('data-strict') !== 'false'
  const address = root.getAttribute('data-webx-select') || ''
  const source = address ? new URL(address, location.href) : null
  // Another site's answer is not put into this page.
  if (source && source.origin !== location.origin) return

  let words = {}
  try {
    words = JSON.parse(root.getAttribute('data-words') || '{}')
  } catch {
    // Silent words: the list still works.
  }

  const param = root.getAttribute('data-param') || 'q'
  const min = Math.max(0, Number(root.getAttribute('data-min') ?? 2) || 0)
  const delay = Math.max(0, Number(root.getAttribute('data-delay') ?? 250) || 0)
  const loaded = optionsOf(strict ? native : root.querySelector('.webx-select__options'))

  const added = []
  const on = (target, type, listener, options) => {
    target.addEventListener(type, listener, options)
    added.push(() => target.removeEventListener(type, listener, options))
  }

  // Strict: a field of its own over the hidden select; free: the text field is the combobox.
  const box = document.createElement('div')
  box.className = 'webx-select__box'
  const input = strict ? document.createElement('input') : native
  const list = document.createElement('ul')
  list.className = 'webx-select__list'
  // In a dialog the list opens in the flow: hanging over the content, its edge would cut it.
  if (root.getAttribute('data-inline') === 'true') list.classList.add('webx-select__list--inline')
  list.hidden = true
  list.setAttribute('role', 'listbox')
  idOf(list, 'webx-select-list')

  const label = root.querySelector('.webx-select__label')
  const nativeId = native.id
  if (strict) {
    input.type = 'text'
    input.className = 'webx-select__input'
    input.autocomplete = 'off'
    input.spellcheck = false
    input.placeholder = native.options[0]?.textContent?.trim() || ''
    input.disabled = native.disabled
    if (native.required) input.setAttribute('aria-required', 'true')
    for (const name of ['aria-describedby', 'aria-invalid']) {
      if (native.hasAttribute(name)) input.setAttribute(name, native.getAttribute(name))
    }
    // The label names the field the visitor types into; the select is out of the way.
    native.id = ''
    input.id = nativeId || idOf(input, 'webx-select-input')
    native.hidden = true
    native.tabIndex = -1
    native.setAttribute('aria-hidden', 'true')
    root.insertBefore(box, native)
    box.append(input, list)
  } else {
    native.classList.add('webx-select__input')
    root.insertBefore(box, native)
    box.append(native, list)
  }
  if (label && !label.htmlFor) label.htmlFor = input.id
  input.setAttribute('role', 'combobox')
  input.setAttribute('aria-autocomplete', 'list')
  input.setAttribute('aria-expanded', 'false')
  input.setAttribute('aria-controls', list.id)

  const chosenLabel = () => {
    if (!strict) return native.value
    const option = native.selectedOptions?.[0]
    return option && option.value !== '' ? option.textContent.trim() : ''
  }
  if (strict) input.value = chosenLabel()

  let shown = []
  let active = -1
  let page = 1
  let query = ''
  let more = false
  let timer = null
  let request = null
  let turn = 0
  // What the list says when it has no option: nothing while the server is asked, «Nothing found»
  // only once an answer (or the loaded options alone) came back empty.
  let state = 'done'

  const say = (text) => {
    if (status) status.textContent = text
  }

  const isOpen = () => !list.hidden

  const open = () => {
    if (isOpen()) return
    list.hidden = false
    root.classList.add('is-open')
    input.setAttribute('aria-expanded', 'true')
  }

  const close = () => {
    list.hidden = true
    root.classList.remove('is-open')
    input.setAttribute('aria-expanded', 'false')
    input.removeAttribute('aria-activedescendant')
    active = -1
  }

  const items = () => Array.from(list.querySelectorAll('[role="option"]'))

  const highlight = (index) => {
    const all = items()
    if (all.length === 0) return
    active = (index + all.length) % all.length
    all.forEach((item, i) => item.setAttribute('aria-selected', String(i === active)))
    input.setAttribute('aria-activedescendant', all[active].id)
    all[active].scrollIntoView?.({ block: 'nearest' })
  }

  const draw = () => {
    list.replaceChildren()
    active = -1
    input.removeAttribute('aria-activedescendant')

    shown.forEach((option, index) => {
      const item = document.createElement('li')
      item.className = 'webx-select__option'
      item.id = `${list.id}-${index}`
      item.setAttribute('role', 'option')
      item.setAttribute('aria-selected', 'false')
      if (option.disabled) item.setAttribute('aria-disabled', 'true')
      if (strict && option.value === native.value) item.classList.add('is-chosen')
      item.dataset.index = String(index)
      const text = document.createElement('span')
      text.textContent = option.label
      item.append(text)
      if (option.hint) {
        const hint = document.createElement('span')
        hint.className = 'webx-select__hint'
        hint.textContent = option.hint
        item.append(hint)
      }
      list.append(item)
    })

    if (more) {
      const item = document.createElement('li')
      item.className = 'webx-select__more'
      item.id = `${list.id}-more`
      item.setAttribute('role', 'option')
      item.setAttribute('aria-selected', 'false')
      item.dataset.more = 'true'
      item.textContent = words.more || 'Show more'
      list.append(item)
    }

    const note =
      state === 'searching'
        ? words.searching || 'Searching…'
        : state === 'failed'
          ? words.failed || 'The list could not be loaded.'
          : words.nothing || 'Nothing found'

    if (shown.length === 0) {
      const empty = document.createElement('li')
      empty.className = 'webx-select__empty'
      empty.textContent = note
      list.append(empty)
    }

    say(
      state !== 'done' || shown.length === 0
        ? note
        : (words.count || ':count').replace(':count', String(shown.length)),
    )
  }

  /** Nothing to show yet — an empty field, or too few characters to ask: no list at all. */
  const hush = () => {
    list.replaceChildren()
    close()
    say('')
  }

  /** The loaded options the text matches anywhere in the label or the hint. */
  const local = (text) => {
    const key = fold(text)
    if (key === '') return loaded.slice()
    return loaded.filter((o) => fold(`${o.label} ${o.hint ?? ''}`).includes(key))
  }

  const stop = () => {
    clearTimeout(timer)
    request?.abort()
    request = null
    root.classList.remove('is-loading')
  }

  const ask = async (text, next = 1) => {
    stop()
    const mine = ++turn
    const url = new URL(source.href)
    url.searchParams.set(param, text)
    if (next > 1) url.searchParams.set('page', String(next))
    else url.searchParams.delete('page')
    request = new AbortController()
    root.classList.add('is-loading')
    state = 'searching'
    say(words.searching || '')
    try {
      const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: request.signal,
      })
      if (!response.ok) throw new Error(String(response.status))
      const answer = fromAnswer(await response.json())
      if (mine !== turn) return
      page = next
      more = answer.more
      state = 'done'
      shown = next > 1 ? shown.concat(answer.options) : answer.options
      draw()
      open()
      if (next > 1) highlight(shown.length - answer.options.length)
    } catch (error) {
      if (error?.name === 'AbortError' || mine !== turn) return
      state = 'failed'
      if (shown.length === 0) draw()
      else say(words.failed || '')
    } finally {
      if (mine === turn) {
        root.classList.remove('is-loading')
        request = null
      }
    }
  }

  const search = (text) => {
    query = text
    stop()
    shown = local(text)
    more = false
    const key = fold(text)
    const asks = source && Array.from(key).length >= min && (key !== '' || loaded.length === 0)

    if (asks) {
      // The loaded options that match stay in view; with none, «Searching…» until the answer.
      state = 'searching'
      draw()
      open()
      timer = setTimeout(() => ask(text, 1), delay)
      return
    }

    state = 'done'
    // The loaded options alone are the whole search: an empty filter is a real «Nothing found».
    if (shown.length > 0 || (key !== '' && !source)) {
      draw()
      open()
      return
    }

    hush()
  }

  const choose = (option) => {
    if (!option || option.disabled) return
    if (strict) {
      let held = Array.from(native.options).find((o) => o.value === option.value)
      if (!held) {
        held = new Option(option.label, option.value)
        if (option.hint) held.setAttribute('data-hint', option.hint)
        native.append(held)
      }
      native.value = option.value
      input.value = option.label
    } else {
      native.value = option.label
    }
    close()
    native.dispatchEvent(new Event('input', { bubbles: true }))
    native.dispatchEvent(new Event('change', { bubbles: true }))
  }

  const pick = (item) => {
    if (!item) return
    if (item.dataset.more) {
      ask(query, page + 1)
      return
    }
    choose(shown[Number(item.dataset.index)])
  }

  on(input, 'focus', () => {
    if (strict) input.select?.()
  })

  on(input, 'click', () => {
    if (isOpen()) return
    search(strict ? '' : input.value)
  })

  on(input, 'input', () => search(input.value))

  on(input, 'keydown', (event) => {
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        if (!isOpen()) search(strict ? '' : input.value)
        highlight(active + 1)
        break
      case 'ArrowUp':
        event.preventDefault()
        if (!isOpen()) search(strict ? '' : input.value)
        highlight(active - 1)
        break
      case 'Home':
      case 'End':
        if (isOpen() && strict) {
          event.preventDefault()
          highlight(event.key === 'Home' ? 0 : items().length - 1)
        }
        break
      case 'Enter':
        if (isOpen() && active >= 0) {
          event.preventDefault()
          pick(items()[active])
        } else if (isOpen() && strict) {
          // Enter in a list does not send the form under it half chosen.
          event.preventDefault()
        }
        break
      case 'Escape':
        if (isOpen()) {
          event.preventDefault()
          event.stopPropagation()
          stop()
          close()
          if (strict) input.value = chosenLabel()
        }
        break
      case 'Tab':
        close()
        break
    }
  })

  // The field keeps the focus while a pointer chooses.
  on(list, 'mousedown', (event) => event.preventDefault())
  on(list, 'click', (event) => {
    const item = event.target instanceof Element ? event.target.closest('[role="option"]') : null
    if (item && list.contains(item)) pick(item)
  })

  on(input, 'blur', () => {
    stop()
    close()
    if (strict) input.value = chosenLabel()
  })

  // The owner may change the select itself: the field follows.
  on(native, 'change', () => {
    if (strict && document.activeElement !== input) input.value = chosenLabel()
  })

  return () => {
    added.splice(0).forEach((remove) => remove())
    stop()
    if (strict) {
      input.remove()
      native.hidden = false
      native.removeAttribute('tabindex')
      native.removeAttribute('aria-hidden')
      native.id = nativeId
      root.insertBefore(native, box)
    } else {
      native.classList.remove('webx-select__input')
      for (const name of [
        'role',
        'aria-autocomplete',
        'aria-expanded',
        'aria-controls',
        'aria-activedescendant',
      ]) {
        native.removeAttribute(name)
      }
      root.insertBefore(native, box)
    }
    box.remove()
    root.classList.remove('is-open', 'is-loading')
    say('')
  }
}

webx.widget?.('select', '[data-webx-select]', select)
