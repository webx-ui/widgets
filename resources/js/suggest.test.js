import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  localStorage.clear()
  vi.useRealTimers()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

const words = JSON.stringify({
  label: 'Search',
  nothing: 'Nothing found',
  recent: 'Recent searches',
  clear: 'Clear recent searches',
  count: 'Suggestions: :count',
  failed: 'Suggestions are not available right now.',
}).replace(/"/g, '&quot;')

// What `<x-webx-suggest>` prints.
const box = ({ recent = 0, delay = 250, min = 2, value = '' } = {}) => `
  <form class="webx-suggest" method="get" action="/catalog/search" role="search" data-webx-suggest="/catalog/suggest"
        data-min="${min}" data-delay="${delay}" data-recent="${recent}" data-words="${words}">
    <label class="webx-suggest__label" for="suggest-1">Search</label>
    <div class="webx-suggest__box">
      <input class="webx-suggest__input" type="search" id="suggest-1" name="q" value="${value}">
      <button type="submit" class="webx-suggest__submit">Search</button>
      <div class="webx-suggest__panel" id="suggest-1-panel" hidden></div>
    </div>
    <p class="webx-suggest__status" role="status"></p>
  </form>
  <button type="button" id="elsewhere">Elsewhere</button>`

// What a site's address answers: a section of products with cards, and «see all».
const answer = (query, count = 2) => `
  <div data-webx-section>
    <h3>Products (${count})</h3>
    ${Array.from({ length: count }, (_, i) => `<a href="/p/${i + 1}" data-webx-option><span>${query} ${i + 1}</span> <b>$${i + 10}</b></a>`).join('')}
  </div>
  <a href="/catalog/search?q=${query}" data-webx-option>See all</a>`

async function start(
  html,
  respond = vi.fn(async (url) => ({
    ok: true,
    status: 200,
    text: async () => answer(new URL(url).searchParams.get('q')),
  })),
) {
  vi.stubGlobal('fetch', respond)
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./suggest.js')
  const root = document.querySelector('[data-webx-suggest]')
  return {
    root,
    respond,
    input: root.querySelector('input'),
    panel: root.querySelector('.webx-suggest__panel'),
    status: root.querySelector('.webx-suggest__status'),
  }
}

const flush = async () => {
  for (let i = 0; i < 5; i++) await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

const type = (input, value) => {
  input.focus()
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
}

const key = (input, name, options = {}) => {
  const event = new KeyboardEvent('keydown', {
    key: name,
    bubbles: true,
    cancelable: true,
    ...options,
  })
  input.dispatchEvent(event)
  return event
}

const selected = (panel) => Array.from(panel.querySelectorAll('[aria-selected="true"]'))

describe('suggest', () => {
  it('makes the field a combobox and the panel a listbox, and gives it all back on unmount', async () => {
    const { root, input, panel } = await start(box())

    expect(input.getAttribute('role')).toBe('combobox')
    expect(input.getAttribute('aria-autocomplete')).toBe('list')
    expect(input.getAttribute('aria-expanded')).toBe('false')
    expect(input.getAttribute('aria-controls')).toBe('suggest-1-panel')
    expect(input.getAttribute('autocomplete')).toBe('off')
    expect(panel.getAttribute('role')).toBe('listbox')
    expect(panel.getAttribute('aria-label')).toBe('Search')

    window.webx.unmount(root)

    for (const name of [
      'role',
      'aria-autocomplete',
      'aria-expanded',
      'aria-controls',
      'autocomplete',
    ]) {
      expect(input.hasAttribute(name)).toBe(false)
    }
    expect(panel.hasAttribute('role')).toBe(false)
  })

  it('asks after the pause, once for a burst of keys, and shows the site’s HTML as options and groups', async () => {
    vi.useFakeTimers()
    const { input, panel, status, respond } = await start(box())

    type(input, 'l')
    type(input, 'la')
    type(input, 'lap')
    expect(respond).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(249)
    expect(respond).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    await vi.runAllTimersAsync()

    expect(respond).toHaveBeenCalledTimes(1)
    const [url, init] = respond.mock.calls[0]
    expect(url).toBe(`${location.origin}/catalog/suggest?q=lap`)
    expect(init.headers.Accept).toBe('text/html')

    expect(panel.hidden).toBe(false)
    expect(input.getAttribute('aria-expanded')).toBe('true')
    const options = panel.querySelectorAll('[role="option"]')
    expect(options).toHaveLength(3)
    for (const option of options) {
      expect(option.id).not.toBe('')
      expect(option.getAttribute('aria-selected')).toBe('false')
      expect(option.getAttribute('tabindex')).toBe('-1')
    }
    const group = panel.querySelector('[role="group"]')
    expect(document.getElementById(group.getAttribute('aria-labelledby')).textContent).toBe(
      'Products (2)',
    )
    expect(status.textContent).toBe('Suggestions: 3')
  })

  it('does not ask below the minimum', async () => {
    vi.useFakeTimers()
    const { input, panel, respond } = await start(box({ min: 3 }))

    type(input, 'la')
    await vi.runAllTimersAsync()

    expect(respond).not.toHaveBeenCalled()
    expect(panel.hidden).toBe(true)
  })

  it('cancels the stale request and drops an answer that comes late', async () => {
    const pending = []
    const respond = vi.fn(
      (url, init) =>
        new Promise((resolve) => {
          pending.push({ q: new URL(url).searchParams.get('q'), init, resolve })
        }),
    )
    const { input, panel } = await start(box({ delay: 0 }), respond)

    type(input, 'lap')
    await new Promise((resolve) => setTimeout(resolve, 5))
    type(input, 'laptop')
    await new Promise((resolve) => setTimeout(resolve, 5))

    expect(pending.map((p) => p.q)).toEqual(['lap', 'laptop'])
    expect(pending[0].init.signal.aborted).toBe(true)
    expect(pending[1].init.signal.aborted).toBe(false)

    pending[1].resolve({ ok: true, status: 200, text: async () => answer('laptop', 1) })
    await flush()
    // The old answer comes after all, a fetch that ignored the signal.
    pending[0].resolve({ ok: true, status: 200, text: async () => answer('lap', 5) })
    await flush()

    expect(panel.querySelectorAll('[role="option"]')).toHaveLength(2)
    expect(panel.textContent).toContain('laptop 1')
    expect(panel.textContent).not.toContain('lap 5')
  })

  it('walks the options with the arrows, wraps, and Home/End only inside the list', async () => {
    const { input, panel } = await start(box({ delay: 0 }))
    type(input, 'lap')
    await flush()
    await flush()
    const options = Array.from(panel.querySelectorAll('[role="option"]'))

    expect(key(input, 'Home').defaultPrevented).toBe(false)
    key(input, 'ArrowDown')
    expect(selected(panel)).toEqual([options[0]])
    expect(input.getAttribute('aria-activedescendant')).toBe(options[0].id)
    key(input, 'ArrowDown')
    key(input, 'ArrowDown')
    expect(selected(panel)).toEqual([options[2]])
    key(input, 'ArrowDown')
    expect(selected(panel)).toEqual([options[0]])
    key(input, 'ArrowUp')
    expect(selected(panel)).toEqual([options[2]])
    expect(key(input, 'Home').defaultPrevented).toBe(true)
    expect(selected(panel)).toEqual([options[0]])
    key(input, 'End')
    expect(input.getAttribute('aria-activedescendant')).toBe(options[2].id)
  })

  it('Enter follows the chosen option’s link and remembers the words; without a choice the form is sent', async () => {
    const { root, input, panel } = await start(box({ delay: 0, recent: 5 }))
    type(input, 'lap')
    await flush()
    await flush()

    const followed = vi.fn((event) => event.preventDefault())
    panel.querySelector('a[href="/p/2"]').addEventListener('click', followed)
    const chosen = vi.fn()
    root.addEventListener('webx:suggest:select', chosen)

    key(input, 'ArrowDown')
    key(input, 'ArrowDown')
    const enter = key(input, 'Enter')

    expect(enter.defaultPrevented).toBe(true)
    expect(followed).toHaveBeenCalledTimes(1)
    expect(chosen.mock.calls[0][0].detail).toMatchObject({
      query: 'lap',
      href: `${location.origin}/p/2`,
    })
    expect(panel.hidden).toBe(true)
    expect(JSON.parse(localStorage.getItem('webx-suggest:/catalog/search'))).toEqual(['lap'])

    // No option chosen: Enter is the form's.
    type(input, 'lapt')
    await flush()
    await flush()
    expect(key(input, 'Enter').defaultPrevented).toBe(false)
  })

  it('a cancelled select stays on the page', async () => {
    const { root, input, panel } = await start(box({ delay: 0 }))
    type(input, 'lap')
    await flush()
    await flush()
    root.addEventListener('webx:suggest:select', (event) => event.preventDefault())

    const click = new MouseEvent('click', { bubbles: true, cancelable: true })
    panel.querySelector('a[href="/p/1"]').dispatchEvent(click)

    expect(click.defaultPrevented).toBe(true)
  })

  it('an option with a value searches for it', async () => {
    const respond = vi.fn(async () => ({
      ok: true,
      status: 200,
      text: async () => '<ul><li data-webx-option="laptop stand">laptop stand</li></ul>',
    }))
    const { root, input, panel } = await start(box({ delay: 0, recent: 3 }), respond)
    const sent = vi.fn((event) => event.preventDefault())
    root.addEventListener('submit', sent)
    root.requestSubmit = vi.fn(() => root.dispatchEvent(new Event('submit', { cancelable: true })))

    type(input, 'lap')
    await flush()
    await flush()
    panel
      .querySelector('li')
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))

    expect(input.value).toBe('laptop stand')
    expect(sent).toHaveBeenCalledTimes(1)
    expect(JSON.parse(localStorage.getItem('webx-suggest:/catalog/search'))).toEqual([
      'laptop stand',
    ])
  })

  it('inside another form: a value fills the field and is not sent; Enter without a choice is that form’s', async () => {
    const respond = vi.fn(async () => ({
      ok: true,
      status: 200,
      text: async () =>
        '<ul><li data-webx-option="Kraków">Kraków</li><li data-webx-option="Krosno">Krosno</li></ul>',
    }))
    const { root, input, panel } = await start(
      `<form id="order" action="/order" method="post">
        <div class="webx-suggest webx-suggest--field" data-webx-suggest="/cities" data-param="city" data-min="2" data-delay="0" data-recent="3" data-words="${words}">
          <label class="webx-suggest__label" for="suggest-1">City</label>
          <div class="webx-suggest__box">
            <input class="webx-suggest__input" type="text" id="suggest-1" name="delivery[city]" value="">
            <div class="webx-suggest__panel" id="suggest-1-panel" hidden></div>
          </div>
          <p class="webx-suggest__status" role="status"></p>
        </div>
      </form>`,
      respond,
    )
    const form = document.getElementById('order')
    const sent = vi.fn((event) => event.preventDefault())
    form.addEventListener('submit', sent)
    form.requestSubmit = vi.fn()
    const changed = vi.fn()
    input.addEventListener('change', changed)

    type(input, 'Kr')
    await flush()
    await flush()
    expect(new URL(respond.mock.calls[0][0]).searchParams.get('city')).toBe('Kr')

    key(input, 'ArrowDown')
    key(input, 'Enter')

    expect(input.value).toBe('Kraków')
    expect(changed).toHaveBeenCalledTimes(1)
    expect(form.requestSubmit).not.toHaveBeenCalled()
    expect(panel.hidden).toBe(true)
    expect(JSON.parse(localStorage.getItem('webx-suggest:/cities'))).toEqual(['Kraków'])
    expect(root.tagName).toBe('DIV')

    // Nothing chosen: Enter is left to the form around it.
    type(input, 'Kro')
    await flush()
    await flush()
    expect(key(input, 'Enter').defaultPrevented).toBe(false)
  })

  it('Escape closes first and leaves the words; Tab and leaving close', async () => {
    const { input, panel } = await start(box({ delay: 0 }))
    type(input, 'lap')
    await flush()
    await flush()

    const escape = key(input, 'Escape')
    expect(escape.defaultPrevented).toBe(true)
    expect(panel.hidden).toBe(true)
    expect(input.value).toBe('lap')
    expect(input.getAttribute('aria-expanded')).toBe('false')
    expect(key(input, 'Escape').defaultPrevented).toBe(false)

    key(input, 'ArrowDown')
    await flush()
    await flush()
    expect(panel.hidden).toBe(false)
    expect(selected(panel)).toEqual([])

    document.getElementById('elsewhere').focus()
    expect(panel.hidden).toBe(true)
  })

  it('says nothing found: its own words for an empty answer, the site’s when it sent some', async () => {
    let html = ''
    const respond = vi.fn(async () => ({ ok: true, status: 200, text: async () => html }))
    const { input, panel, status } = await start(box({ delay: 0 }), respond)

    type(input, 'zzz')
    await flush()
    await flush()
    expect(panel.hidden).toBe(false)
    expect(panel.querySelector('.webx-suggest__empty').textContent).toBe('Nothing found')
    expect(status.textContent).toBe('Nothing found')

    html = '<p class="site-nothing">No products for «zzzz». Try the catalogue.</p>'
    type(input, 'zzzz')
    await flush()
    await flush()
    expect(panel.querySelector('.site-nothing')).not.toBeNull()
    expect(panel.querySelector('.webx-suggest__empty')).toBeNull()
    expect(status.textContent).toBe('Nothing found')
  })

  it('a failed answer says so and leaves the search to Enter', async () => {
    const respond = vi.fn(async () => ({ ok: false, status: 500, text: async () => '' }))
    const { input, panel, status } = await start(box({ delay: 0 }), respond)

    type(input, 'lap')
    await flush()
    await flush()

    expect(panel.textContent).toContain('not available')
    expect(status.textContent).toBe('Suggestions are not available right now.')
    expect(key(input, 'Enter').defaultPrevented).toBe(false)
  })

  it('asks the same words once', async () => {
    const { input, respond } = await start(box({ delay: 0 }))
    type(input, 'lap')
    await flush()
    await flush()
    type(input, 'lapt')
    await flush()
    await flush()
    type(input, 'lap')
    await flush()
    await flush()

    expect(respond.mock.calls.map(([url]) => new URL(url).searchParams.get('q'))).toEqual([
      'lap',
      'lapt',
    ])
  })

  it('shows recent searches in an empty field, as text, and clears them', async () => {
    localStorage.setItem(
      'webx-suggest:/catalog/search',
      JSON.stringify(['<img src=x onerror=alert(1)>', 'laptop']),
    )
    const { input, panel } = await start(box({ recent: 5 }))

    input.focus()
    expect(panel.hidden).toBe(false)
    expect(panel.querySelector('img')).toBeNull()
    const options = Array.from(
      panel.querySelectorAll('[role="option"]'),
      (option) => option.textContent,
    )
    expect(options).toEqual(['<img src=x onerror=alert(1)>', 'laptop', 'Clear recent searches'])
    expect(
      document.getElementById(panel.querySelector('[role="group"]').getAttribute('aria-labelledby'))
        .textContent,
    ).toBe('Recent searches')

    key(input, 'End')
    expect(selected(panel)).toEqual([])
    key(input, 'ArrowUp')
    key(input, 'Enter')

    expect(localStorage.getItem('webx-suggest:/catalog/search')).toBeNull()
    expect(panel.hidden).toBe(true)
  })

  it('keeps no recent searches unless asked', async () => {
    localStorage.setItem('webx-suggest:/catalog/search', JSON.stringify(['laptop']))
    const { root, input, panel } = await start(box())
    root.addEventListener('submit', (event) => event.preventDefault())

    input.focus()
    expect(panel.hidden).toBe(true)
    input.value = 'phone'
    root.dispatchEvent(new Event('submit', { cancelable: true }))
    expect(JSON.parse(localStorage.getItem('webx-suggest:/catalog/search'))).toEqual(['laptop'])
  })

  it('does not put another site’s HTML into the page', async () => {
    const { input, respond } = await start(
      box({ delay: 0 }).replace(
        'data-webx-suggest="/catalog/suggest"',
        'data-webx-suggest="https://elsewhere.example/suggest"',
      ),
    )

    type(input, 'lap')
    await flush()

    expect(respond).not.toHaveBeenCalled()
    expect(input.hasAttribute('role')).toBe(false)
  })

  it('unmounting cancels the pending request and empties the panel', async () => {
    let signal = null
    const respond = vi.fn((url, init) => {
      signal = init.signal
      return new Promise(() => {})
    })
    const { root, input, panel } = await start(box({ delay: 0 }), respond)

    type(input, 'lap')
    await flush()
    window.webx.unmount(root)

    expect(signal.aborted).toBe(true)
    expect(panel.hidden).toBe(true)
    expect(panel.innerHTML).toBe('')
  })
})
