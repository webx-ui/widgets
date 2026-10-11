import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  document.head.querySelectorAll('[data-test-added]').forEach((el) => el.remove())
  localStorage.clear()
  history.replaceState(null, '', '/')
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

async function start(html, file) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import(`./${file}.js`)
}

const words = JSON.stringify({
  loading: 'Loading…',
  loaded: 'Page :page of :last loaded.',
  end: 'That is everything.',
  failed: 'The next page could not be loaded.',
}).replace(/"/g, '&quot;')

// What `<x-webx-load-more>` prints for page `page` of `last`, three items a page.
const list = (page, last = 3, { pages = 'covered', key = 'page' } = {}) => {
  const next = page < last ? ` data-next="/news?page=${page + 1}"` : ''
  const items = [1, 2, 3].map((n) => `<article>${(page - 1) * 3 + n}</article>`).join('')
  const numbers = Array.from({ length: last }, (_, i) => i + 1)
    .map((n) =>
      n === page
        ? `<span class="webx-pagination__link is-current" data-page="${n}">${n}</span>`
        : `<a class="webx-pagination__link" href="/news?page=${n}" data-page="${n}">${n}</a>`,
    )
    .join('')
  return `<div class="webx-load-more" data-webx-load-more="${key}" data-pages="${pages}" data-page="${page}" data-last="${last}"${next} data-words="${words}">
    <div class="webx-load-more__list" data-webx-load-more-list>${items}</div>
    ${next ? '<div class="webx-load-more__more"><button type="button" class="webx-load-more__button" hidden>Show more</button></div>' : ''}
    <p class="webx-load-more__status" role="status"></p>
    <div class="webx-load-more__pages"><nav class="webx-pagination">${numbers}${next ? `<a rel="next" href="/news?page=${page + 1}">Next</a>` : ''}</nav></div>
  </div>`
}

const page = (body, head = '') =>
  `<!doctype html><html><head>${head}</head><body>${body}</body></html>`

const respond = (pages) =>
  vi.fn(async (url) => {
    const found = pages[new URL(url).search]
    if (found === undefined) return { ok: false, status: 500, text: async () => '' }
    return { ok: true, status: 200, text: async () => found }
  })

const flush = async () => {
  for (let i = 0; i < 5; i++) await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('show more', () => {
  it('shows the button, covers the links and appends the next page in order', async () => {
    const fetch = respond({ '?page=2': page(list(2)), '?page=3': page(list(3)) })
    vi.stubGlobal('fetch', fetch)
    history.replaceState(null, '', '/news')
    await start(list(1), 'load-more')

    const root = document.querySelector('[data-webx-load-more]')
    const button = root.querySelector('.webx-load-more__button')
    expect(button.hidden).toBe(false)
    expect(root.querySelector('.webx-load-more__pages').classList.contains('is-covered')).toBe(true)

    button.click()
    expect(root.classList.contains('is-loading')).toBe(true)
    expect(button.getAttribute('aria-disabled')).toBe('true')
    // A second press while loading asks for nothing more.
    button.click()
    await flush()

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(fetch.mock.calls[0][0]).toBe(`${location.origin}/news?page=2`)
    const items = [...root.querySelectorAll('[data-webx-load-more-list] > article')]
    expect(items.map((el) => el.textContent)).toEqual(['1', '2', '3', '4', '5', '6'])
    // The focus on the first new item, the address and the next link of the page just loaded.
    expect(document.activeElement).toBe(items[3])
    expect(items[3].getAttribute('tabindex')).toBe('-1')
    expect(location.search).toBe('?page=2')
    expect(root.getAttribute('data-next')).toBe('/news?page=3')
    expect(root.querySelector('a[rel="next"]').getAttribute('href')).toBe('/news?page=3')
    expect(root.querySelector('.webx-load-more__status').textContent).toBe('Page 2 of 3 loaded.')
    expect(root.querySelector('.webx-load-more__pages').classList.contains('is-covered')).toBe(true)
    expect(root.classList.contains('is-loading')).toBe(false)

    // The last page: no button; from page one, everything is on the screen and the links stay covered.
    button.click()
    await flush()
    expect(root.querySelectorAll('[data-webx-load-more-list] > article')).toHaveLength(9)
    expect(root.querySelector('.webx-load-more__more').hidden).toBe(true)
    expect(root.hasAttribute('data-next')).toBe(false)
    expect(root.querySelector('.webx-load-more__status').textContent).toBe(
      'Page 3 of 3 loaded. That is everything.',
    )
    expect(root.querySelector('.webx-load-more__pages').classList.contains('is-covered')).toBe(true)
    expect(location.search).toBe('?page=3')
  })

  it('from a later page, the links lead back once the last one came', async () => {
    vi.stubGlobal('fetch', respond({ '?page=3': page(list(3)) }))
    history.replaceState(null, '', '/news?page=2')
    await start(list(2), 'load-more')

    document.querySelector('.webx-load-more__button').click()
    await flush()
    expect(document.querySelector('.webx-load-more__pages').classList.contains('is-covered')).toBe(
      false,
    )
  })

  it('shown: the links stay under the button and mark the pages on the screen', async () => {
    vi.stubGlobal('fetch', respond({ '?page=2': page(list(2, 4, { pages: 'shown' })) }))
    await start(list(1, 4, { pages: 'shown' }), 'load-more')

    const pages = () => document.querySelector('.webx-load-more__pages')
    expect(pages().classList.contains('is-covered')).toBe(false)
    document.querySelector('.webx-load-more__button').click()
    await flush()

    expect(pages().classList.contains('is-covered')).toBe(false)
    expect(pages().querySelector('[data-page="1"]').classList.contains('is-loaded')).toBe(true)
    expect(pages().querySelector('[data-page="2"]').classList.contains('is-current')).toBe(true)
    expect(pages().querySelector('[data-page="3"]').classList.contains('is-loaded')).toBe(false)
  })

  it('takes the list with its own key and starts the widgets inside the new items', async () => {
    const other = list(5, 9, { key: 'reviews' })
    const next = page(
      `${other}${list(2).replace('<article>4</article>', '<article data-webx-tabs>4</article>')}`,
      '<link rel="stylesheet" href="/vendor/webx-widgets/tabs-extra.css">',
    )
    vi.stubGlobal('fetch', respond({ '?page=2': next }))
    await start(list(1), 'load-more')
    const mount = vi.spyOn(window.webx, 'mount')

    document.querySelector('.webx-load-more__button').click()
    await flush()

    const texts = [...document.querySelectorAll('[data-webx-load-more-list] > article')].map(
      (el) => el.textContent,
    )
    expect(texts).toEqual(['1', '2', '3', '4', '5', '6'])
    expect(mount).toHaveBeenCalledWith(document.querySelector('[data-webx-tabs]'))
    // A stylesheet the next page has and this one has not.
    const added = document.head.querySelector('link[href$="tabs-extra.css"]')
    expect(added).not.toBeNull()
    added.setAttribute('data-test-added', '')
  })

  it('a failure says so, uncovers the links and lets the button try again', async () => {
    const fetch = vi.fn(async () => {
      throw new TypeError('offline')
    })
    vi.stubGlobal('fetch', fetch)
    history.replaceState(null, '', '/news')
    await start(list(1), 'load-more')

    const root = document.querySelector('[data-webx-load-more]')
    const button = root.querySelector('.webx-load-more__button')
    button.click()
    await flush()

    expect(root.querySelector('.webx-load-more__status').textContent).toBe(
      'The next page could not be loaded.',
    )
    expect(root.querySelector('.webx-load-more__pages').classList.contains('is-covered')).toBe(
      false,
    )
    expect(root.querySelector('a[rel="next"]').getAttribute('href')).toBe('/news?page=2')
    expect(location.search).toBe('')
    expect(button.hasAttribute('aria-disabled')).toBe(false)

    // A page that answered without the list is a failure too.
    vi.stubGlobal('fetch', respond({ '?page=2': page('<p>Maintenance</p>') }))
    button.click()
    await flush()
    expect(root.querySelectorAll('[data-webx-load-more-list] > article')).toHaveLength(3)
    expect(root.querySelector('.webx-load-more__status').textContent).toBe(
      'The next page could not be loaded.',
    )
  })

  it('unmounting hides the button and gives the links back', async () => {
    await start(list(1), 'load-more')
    window.webx.unmount()
    expect(document.querySelector('.webx-load-more__button').hidden).toBe(true)
    expect(document.querySelector('.webx-load-more__pages').classList.contains('is-covered')).toBe(
      false,
    )
  })
})

const bar = (version = 'abc123', close = true) =>
  `<a class="skip" href="#content">Skip</a><section class="webx-notice-bar" aria-label="Announcement" data-webx-notice-bar="${version}"><div class="webx-notice-bar__inner"><div class="webx-notice-bar__content">Open on <a href="/hours">Saturdays</a></div>${close ? '<button type="button" class="webx-notice-bar__close" aria-label="Close the announcement" hidden>×</button>' : ''}</div></section>`

describe('notice bar', () => {
  it('shows its close button, and closing remembers the version and hands the keyboard to the top', async () => {
    await start(bar(), 'notice-bar')

    const root = document.querySelector('[data-webx-notice-bar]')
    const button = root.querySelector('.webx-notice-bar__close')
    expect(button.hidden).toBe(false)
    const heard = vi.fn()
    document.addEventListener('webx:notice-bar', heard, { once: true })

    button.focus()
    button.click()

    expect(root.hidden).toBe(true)
    expect(JSON.parse(localStorage.getItem('webx-notice-bar'))).toEqual(['abc123'])
    expect(document.activeElement).toBe(document.body)
    expect(document.body.getAttribute('tabindex')).toBe('-1')
    expect(heard.mock.calls[0][0].detail).toEqual({ version: 'abc123' })
    document.body.dispatchEvent(new FocusEvent('blur'))
    expect(document.body.hasAttribute('tabindex')).toBe(false)
  })

  it('a closed version stays closed; new words come back', async () => {
    localStorage.setItem('webx-notice-bar', JSON.stringify(['abc123']))
    await start(bar('abc123') + bar('def456'), 'notice-bar')

    const [closed, fresh] = document.querySelectorAll('[data-webx-notice-bar]')
    expect(closed.hidden).toBe(true)
    expect(fresh.hidden).toBe(false)
    expect(fresh.querySelector('.webx-notice-bar__close').hidden).toBe(false)
  })

  it('keeps the newest twenty versions, and a refused storage closes for this page only', async () => {
    localStorage.setItem(
      'webx-notice-bar',
      JSON.stringify(Array.from({ length: 20 }, (_, i) => `old${i}`)),
    )
    await start(bar('new'), 'notice-bar')
    document.querySelector('.webx-notice-bar__close').click()
    const kept = JSON.parse(localStorage.getItem('webx-notice-bar'))
    expect(kept).toHaveLength(20)
    expect(kept.at(-1)).toBe('new')
    expect(kept[0]).toBe('old1')

    window.webx.unmount()
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('quota')
    })
    await start(bar('again'), 'notice-bar')
    document.querySelector('.webx-notice-bar__close').click()
    expect(document.querySelector('[data-webx-notice-bar]').hidden).toBe(true)
  })

  it('a bar that cannot be closed has no button to show, and unmounting hides it again', async () => {
    await start(bar('fixed', false), 'notice-bar')
    expect(document.querySelector('.webx-notice-bar__close')).toBeNull()

    await start(bar(), 'notice-bar')
    window.webx.unmount()
    expect(document.querySelector('.webx-notice-bar__close').hidden).toBe(true)
  })
})
