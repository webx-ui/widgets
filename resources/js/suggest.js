/*
 * Suggestions while typing (spec §18, W6.5). Its own file, claimed by `<x-webx-suggest>`.
 *
 * The server printed a GET form to the results page; that is the search without JavaScript.
 * Here its field becomes an ARIA combobox (the APG pattern: the focus stays in the field, the
 * option chosen by keys is `aria-activedescendant`) and the panel under it a listbox. As the
 * visitor types, after a pause, the address of suggestions is asked with the words; a request
 * the next keystroke made stale is cancelled, and an answer that still comes late is dropped.
 *
 * The answer is HTML, and the panel shows it as it is: the site's sections, headings, counts and
 * cards. Only `data-webx-option` is the shell's — what can be chosen: a link inside it is
 * followed, a value in the attribute is searched for (or, in a field inside another form, put into
 * the field with a `change`) — and `data-webx-section` with a heading,
 * which becomes a named group. An answer without options says «nothing found»: the site's own
 * words when it sent any, the shell's when the answer was empty.
 *
 * Recent searches, when the component keeps any, are in the visitor's browser only, per results
 * page, shown while the field is empty.
 */

import '../css/suggest.css'

const webx = (window.webx ??= {})

let ids = 0
const idOf = (el, prefix) => el.id || (el.id = `${prefix}-${++ids}`)

const length = (text) => Array.from(text).length

// The link an option leads to: the option itself, or the first inside it.
const linkOf = (option) => (option.matches('a[href]') ? option : option.querySelector('a[href]'))

export function suggest(root) {
  const input = root.querySelector('.webx-suggest__input')
  const panel = root.querySelector('.webx-suggest__panel')
  const status = root.querySelector('.webx-suggest__status')
  const address = root.getAttribute('data-webx-suggest')
  if (!input || !panel || !address) return

  const source = new URL(address, location.href)
  // Another site's HTML is not put into this page.
  if (source.origin !== location.origin) return

  let words = {}
  try {
    words = JSON.parse(root.getAttribute('data-words') || '{}')
  } catch {
    // The shell works without its words; only the status line and «nothing found» are silent.
  }

  const min = Math.max(1, Number(root.getAttribute('data-min')) || 2)
  const delay = Math.max(0, Number(root.getAttribute('data-delay') ?? 250) || 0)
  const keep = Math.max(0, Number(root.getAttribute('data-recent')) || 0)
  // A search box is a form of its own; without `action` the field stands inside another form.
  const own = root instanceof HTMLFormElement
  const param = root.getAttribute('data-param') || 'q'
  // Recent searches per results page; a field's per address of its suggestions.
  const store = `webx-suggest:${own ? new URL(root.getAttribute('action') || location.pathname, location.href).pathname : source.pathname}`

  const added = []
  const on = (target, type, listener, options) => {
    target.addEventListener(type, listener, options)
    added.push(() => target.removeEventListener(type, listener, options))
  }

  const before = {
    role: input.getAttribute('role'),
    autocomplete: input.getAttribute('autocomplete'),
  }
  input.setAttribute('role', 'combobox')
  input.setAttribute('aria-autocomplete', 'list')
  input.setAttribute('aria-expanded', 'false')
  input.setAttribute('aria-controls', idOf(panel, 'webx-suggest-panel'))
  // The browser's own list of earlier entries would cover the panel.
  input.setAttribute('autocomplete', 'off')
  panel.setAttribute('role', 'listbox')
  if (words.label) panel.setAttribute('aria-label', words.label)

  let options = []
  let active = -1
  let timer = 0
  let aborter = null
  let asked = 0
  const answers = new Map()

  const emit = (name, detail, cancelable = false) =>
    root.dispatchEvent(
      new CustomEvent(`webx:suggest:${name}`, { bubbles: true, cancelable, detail }),
    )

  const say = (text) => {
    if (status) status.textContent = text || ''
  }

  const term = () => input.value.trim()

  // The room under the field down to the keyboard: a phone's screen shrinks under it, and the
  // visual viewport is what is left.
  const fit = () => {
    if (panel.hidden) return
    const view = window.visualViewport
    const bottom = view ? view.offsetTop + view.height : window.innerHeight
    const top = panel.getBoundingClientRect().top
    root.style.setProperty(
      '--webx-suggest-room',
      `${Math.max(120, Math.floor(bottom - top - 8))}px`,
    )
  }

  const choose = (index) => {
    active = index
    options.forEach((option, i) =>
      option.setAttribute('aria-selected', i === index ? 'true' : 'false'),
    )
    if (index < 0) {
      input.removeAttribute('aria-activedescendant')
      return
    }
    input.setAttribute('aria-activedescendant', options[index].id)
    options[index].scrollIntoView?.({ block: 'nearest' })
  }

  const open = () => {
    panel.hidden = false
    root.classList.add('is-open')
    input.setAttribute('aria-expanded', 'true')
    fit()
  }

  const close = () => {
    choose(-1)
    panel.hidden = true
    root.classList.remove('is-open')
    input.setAttribute('aria-expanded', 'false')
  }

  const stop = () => {
    clearTimeout(timer)
    aborter?.abort()
    aborter = null
    asked++
    root.classList.remove('is-loading')
  }

  // What the site sent, or the shell built, made into a listbox: options, and sections as groups.
  const mark = () => {
    for (const section of panel.querySelectorAll('[data-webx-section]')) {
      section.classList.add('webx-suggest__section')
      section.setAttribute('role', 'group')
      const heading = section.querySelector('[data-webx-heading], h2, h3, h4, h5, h6')
      if (heading) {
        heading.classList.add('webx-suggest__heading')
        section.setAttribute('aria-labelledby', idOf(heading, 'webx-suggest-heading'))
      }
    }
    options = Array.from(panel.querySelectorAll('[data-webx-option]'))
    for (const option of options) {
      option.classList.add('webx-suggest__option')
      option.setAttribute('role', 'option')
      option.setAttribute('aria-selected', 'false')
      idOf(option, 'webx-suggest-option')
      // The focus stays in the field: Tab leaves the search rather than walking the panel.
      for (const el of [
        option,
        ...option.querySelectorAll('a[href], button, input, select, textarea'),
      ]) {
        if (el.matches('a[href], button, input, select, textarea'))
          el.setAttribute('tabindex', '-1')
      }
    }
    active = -1
    input.removeAttribute('aria-activedescendant')
  }

  const empty = (text) => {
    const p = document.createElement('p')
    p.className = 'webx-suggest__empty'
    p.textContent = text || ''
    return p
  }

  const fill = (content) => {
    webx.unmount?.(panel)
    panel.replaceChildren()
    if (typeof content === 'string') panel.innerHTML = content
    else panel.append(...content)
    mark()
    webx.mount?.(panel)
  }

  const show = (query, html) => {
    if (term() !== query || document.activeElement !== input) return
    if (html.trim() === '') fill([empty(words.nothing)])
    else fill(html)
    open()
    const count = options.length
    say(count ? (words.count || '').replace(':count', String(count)) : words.nothing)
    emit('results', { query, count })
  }

  const ask = async (query) => {
    stop()
    const n = asked
    if (answers.has(query)) {
      show(query, answers.get(query))
      return
    }

    aborter = typeof AbortController === 'function' ? new AbortController() : null
    root.classList.add('is-loading')
    const url = new URL(source.href)
    url.searchParams.set(param, query)

    let html
    try {
      const response = await fetch(url.href, {
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal: aborter?.signal,
      })
      if (!response.ok) throw new Error(`HTTP ${response.status}`)
      html = response.status === 204 ? '' : await response.text()
    } catch (error) {
      // A later question took this one's place: nothing to say about it.
      if (n !== asked || error?.name === 'AbortError') return
      root.classList.remove('is-loading')
      if (term() !== query || document.activeElement !== input) return
      fill([empty(words.failed)])
      open()
      say(words.failed)
      return
    }

    // An answer to words no longer in the field, come after the request for the new ones.
    if (n !== asked) return
    aborter = null
    root.classList.remove('is-loading')
    answers.set(query, html)
    if (answers.size > 20) answers.delete(answers.keys().next().value)
    show(query, html)
  }

  const recents = () => {
    if (!keep) return []
    try {
      const list = JSON.parse(localStorage.getItem(store) || '[]')
      return Array.isArray(list)
        ? list.filter((item) => typeof item === 'string' && item !== '').slice(0, keep)
        : []
    } catch {
      return []
    }
  }

  const remember = (query) => {
    if (!keep || !query) return
    const lower = query.toLowerCase()
    try {
      const list = [query, ...recents().filter((item) => item.toLowerCase() !== lower)].slice(
        0,
        keep,
      )
      localStorage.setItem(store, JSON.stringify(list))
    } catch {
      // A private window or a full storage: the search works, only without memory.
    }
  }

  const forget = () => {
    try {
      localStorage.removeItem(store)
    } catch {
      // Nothing was kept.
    }
  }

  // Built here, with text only: what the visitor typed is never read as HTML.
  const showRecent = () => {
    stop()
    const list = recents()
    if (!list.length) {
      close()
      return
    }
    const section = document.createElement('div')
    section.setAttribute('data-webx-section', 'recent')
    const heading = document.createElement('p')
    heading.setAttribute('data-webx-heading', '')
    heading.textContent = words.recent || ''
    section.append(heading)
    for (const item of list) {
      const option = document.createElement('div')
      option.setAttribute('data-webx-option', item)
      option.textContent = item
      section.append(option)
    }
    const clear = document.createElement('div')
    clear.setAttribute('data-webx-option', '')
    clear.setAttribute('data-webx-clear', '')
    clear.className = 'webx-suggest__clear'
    clear.textContent = words.clear || ''
    section.append(clear)
    fill([section])
    open()
    say('')
  }

  // The panel for what is in the field now: the recent searches, a known answer, or a question.
  const reopen = () => {
    const query = term()
    if (length(query) < min) showRecent()
    else ask(query)
  }

  on(input, 'input', () => {
    clearTimeout(timer)
    const query = term()
    if (length(query) < min) {
      if (query === '') showRecent()
      else {
        stop()
        close()
      }
      return
    }
    timer = setTimeout(() => ask(query), delay)
  })

  on(input, 'focus', () => {
    if (term() === '') showRecent()
  })

  on(input, 'keydown', (event) => {
    if (event.isComposing) return
    const shown = !panel.hidden
    switch (event.key) {
      case 'ArrowDown':
      case 'ArrowUp': {
        event.preventDefault()
        if (!shown) {
          reopen()
          return
        }
        if (event.altKey || !options.length) return
        const last = options.length - 1
        if (event.key === 'ArrowDown') choose(active >= last ? 0 : active + 1)
        else choose(active <= 0 ? last : active - 1)
        return
      }
      case 'Home':
      case 'End':
        // Only inside the list: before an option is chosen, they move the caret in the words.
        if (shown && active >= 0 && options.length) {
          event.preventDefault()
          choose(event.key === 'Home' ? 0 : options.length - 1)
        }
        return
      case 'Enter':
        if (shown && active >= 0) {
          event.preventDefault()
          const option = options[active]
          ;(linkOf(option) ?? option).click()
        }
        return
      case 'Escape':
        if (shown) {
          // The browser would empty a search field on Escape; the first one only closes.
          event.preventDefault()
          stop()
          close()
        }
        return
      case 'Tab':
        stop()
        close()
        return
    }
  })

  // The panel takes no focus: a press on it keeps the field focused and the panel open.
  on(panel, 'mousedown', (event) => event.preventDefault())

  on(panel, 'click', (event) => {
    const option =
      event.target instanceof Element ? event.target.closest('[data-webx-option]') : null
    if (!option || !panel.contains(option)) return

    if (option.hasAttribute('data-webx-clear')) {
      event.preventDefault()
      forget()
      close()
      input.focus()
      return
    }

    const link = linkOf(option)
    const value = option.getAttribute('data-webx-option') || ''
    const query = term()
    if (!emit('select', { option, value, href: link?.href ?? null, query }, true)) {
      event.preventDefault()
      return
    }

    if (link) {
      // The browser follows it, a new tab included.
      remember(query)
      stop()
      close()
      return
    }

    event.preventDefault()
    if (!value) return
    input.value = value
    stop()
    close()
    remember(value)
    if (!own) {
      // A field of another form: chosen, not sent — that form's checks hear a change.
      input.dispatchEvent(new Event('change', { bubbles: true }))
      return
    }
    if (typeof root.requestSubmit === 'function') root.requestSubmit()
    else root.submit()
  })

  const form = own ? root : input.form
  if (form) {
    on(form, 'submit', () => {
      remember(term())
      stop()
      close()
    })
  }

  on(root, 'focusout', (event) => {
    if (event.relatedTarget instanceof Node && root.contains(event.relatedTarget)) return
    stop()
    close()
  })

  on(window, 'resize', fit)
  if (window.visualViewport) {
    on(window.visualViewport, 'resize', fit)
    on(window.visualViewport, 'scroll', fit)
  }

  return () => {
    added.splice(0).forEach((remove) => remove())
    stop()
    close()
    webx.unmount?.(panel)
    panel.replaceChildren()
    panel.removeAttribute('role')
    panel.removeAttribute('aria-label')
    for (const name of [
      'aria-autocomplete',
      'aria-expanded',
      'aria-controls',
      'aria-activedescendant',
    ]) {
      input.removeAttribute(name)
    }
    for (const [name, value] of Object.entries(before)) {
      if (value === null) input.removeAttribute(name)
      else input.setAttribute(name, value)
    }
    root.style.removeProperty('--webx-suggest-room')
    say('')
  }
}

webx.widget?.('suggest', '[data-webx-suggest]', suggest)
