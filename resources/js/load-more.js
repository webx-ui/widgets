/*
 * "Show more" over a Laravel pagination (spec §14). Its own file, claimed by `<x-webx-load-more>`.
 *
 * The server printed the list and the links of the pages. Here the button takes the links' place:
 * it fetches the next page as any visitor would get it, finds the list with the same
 * `data-webx-load-more` in its HTML and adds that list's items here — no endpoint of its own, so
 * an item looks exactly as the module (or the theme overriding it) prints it. Then the address
 * becomes the page just loaded, the links become that page's links, the focus goes to the first
 * new item and the status line says which page came.
 *
 * The address is replaced, not pushed: Back leaves the list rather than taking it apart one page
 * at a time, and a reload or a shared link lands on the page last loaded, whose links lead back.
 *
 * A failure says so and uncovers the links: "next" works as it would without JavaScript.
 */

import '../css/load-more.css'

const webx = (window.webx ??= {})

// What a fetched page loads and this one does not yet: a widget inside the new items.
function assets(doc) {
  const have = new Set(
    Array.from(document.querySelectorAll('link[rel="stylesheet"][href], script[src]'), (el) =>
      el.tagName === 'LINK' ? el.href : el.src,
    ),
  )
  for (const link of doc.querySelectorAll('link[rel="stylesheet"][href]')) {
    const href = new URL(link.getAttribute('href'), location.href).href
    if (have.has(href)) continue
    const el = document.createElement('link')
    el.rel = 'stylesheet'
    el.href = href
    document.head.append(el)
  }
  for (const script of doc.querySelectorAll('script[type="module"][src]')) {
    const src = new URL(script.getAttribute('src'), location.href).href
    if (have.has(src)) continue
    const el = document.createElement('script')
    el.type = 'module'
    el.src = src
    document.body.append(el)
  }
}

export function loadMore(root) {
  const key = root.getAttribute('data-webx-load-more')
  const list = root.querySelector('[data-webx-load-more-list]')
  const button = root.querySelector('.webx-load-more__button')
  const status = root.querySelector('.webx-load-more__status')
  if (!list || !button) return

  let words = {}
  try {
    words = JSON.parse(root.getAttribute('data-words') || '{}')
  } catch {
    // The status line stays silent; the list still grows.
  }

  const first = Number(root.getAttribute('data-page')) || 1
  // `shown`: the links stay under the button; `covered`: the button stands in for them.
  const shown = root.getAttribute('data-pages') === 'shown'
  let pages = root.querySelector('.webx-load-more__pages')

  const cover = (covered) => pages?.classList.toggle('is-covered', covered && !shown)

  // The pages on the screen besides the current one, in the links that follow what was loaded.
  const mark = (page) => {
    for (const link of pages?.querySelectorAll('[data-page]') ?? []) {
      const number = Number(link.getAttribute('data-page'))
      link.classList.toggle('is-loaded', number >= first && number < page)
    }
  }
  let busy = false
  let aborter = null

  const say = (text) => {
    if (status) status.textContent = text
  }

  const idle = () => {
    busy = false
    aborter = null
    root.classList.remove('is-loading')
    button.removeAttribute('aria-disabled')
  }

  const fail = () => {
    idle()
    say(words.failed || '')
    cover(false)
  }

  const more = async () => {
    const next = root.getAttribute('data-next')
    if (busy || !next) return
    const url = new URL(next, location.href)
    // Another site's page is a link to follow, not a list to read.
    if (url.origin !== location.origin) {
      location.assign(url.href)
      return
    }

    busy = true
    aborter = typeof AbortController === 'function' ? new AbortController() : null
    root.classList.add('is-loading')
    button.setAttribute('aria-disabled', 'true')
    say(words.loading || '')

    let doc
    try {
      const response = await fetch(url.href, {
        headers: { Accept: 'text/html' },
        credentials: 'same-origin',
        signal: aborter?.signal,
      })
      if (!response.ok) throw new Error(`HTTP ${response.status}`)
      doc = new DOMParser().parseFromString(await response.text(), 'text/html')
    } catch (error) {
      if (error?.name !== 'AbortError') fail()
      return
    }

    const there = Array.from(doc.querySelectorAll('[data-webx-load-more]')).find(
      (el) => el.getAttribute('data-webx-load-more') === key,
    )
    const items = there?.querySelector('[data-webx-load-more-list]')
    if (!there || !items) return fail()

    assets(doc)
    const added = Array.from(items.children, (item) => document.importNode(item, true))
    list.append(...added)
    for (const item of added) webx.mount?.(item)

    const after = there.getAttribute('data-next')
    if (after) root.setAttribute('data-next', after)
    else root.removeAttribute('data-next')
    const page = there.getAttribute('data-page') || ''
    root.setAttribute('data-page', page)

    // That page's links: previous, the numbers around it, next.
    const fresh = there.querySelector('.webx-load-more__pages')
    if (fresh) {
      const copy = document.importNode(fresh, true)
      if (pages) pages.replaceWith(copy)
      else (status ?? list).after(copy)
      pages = copy
    } else {
      pages?.remove()
      pages = null
    }

    history.replaceState(history.state, '', url.href)
    idle()

    let text = (words.loaded || '')
      .replace(':page', page)
      .replace(':last', there.getAttribute('data-last') || '')
    if (after) {
      cover(true)
    } else {
      // The last page: no button. From the first page on, everything is on the screen and the
      // links would only repeat it; from a later one, they still lead to the pages before.
      button.closest('.webx-load-more__more')?.setAttribute('hidden', '')
      text = `${text} ${words.end || ''}`
      cover(first === 1)
    }
    mark(Number(page))
    say(text.trim())

    const target = added[0]
    if (target instanceof HTMLElement) {
      if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1')
      target.focus()
    }
  }

  button.hidden = false
  cover(true)
  button.addEventListener('click', more)

  return () => {
    button.removeEventListener('click', more)
    aborter?.abort()
    idle()
    button.hidden = true
    cover(false)
  }
}

webx.widget?.('load-more', '[data-webx-load-more]', loadMore)
