import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const KEY = 'webx_attribution'

/** The page as a visitor reaches it: its address, where they came from, what they agreed to. */
async function visit({ url = '/', referrer = '', statistics = false } = {}) {
  window.history.replaceState(null, '', url)
  Object.defineProperty(document, 'referrer', { value: referrer, configurable: true })
  delete window.webx
  window.webx = { consent: { has: (category) => statistics && category === 'statistics' } }
  vi.resetModules()
  return import('./attribution.js')
}

/** What form.js dispatches before it sends: the form's data, which listeners may add to. */
function submit({ attribution = true, fields = {} } = {}) {
  document.body.innerHTML = `<form${attribution ? ' data-webx-attribution' : ''}></form>`
  const form = document.querySelector('form')
  const data = new FormData()
  for (const [name, value] of Object.entries(fields)) data.append(name, value)
  form.dispatchEvent(
    new CustomEvent('webx:form:submit', { bubbles: true, cancelable: true, detail: { data } }),
  )
  return Object.fromEntries(data.entries())
}

const kept = () => JSON.parse(localStorage.getItem(KEY) ?? 'null')

beforeEach(() => localStorage.clear())
afterEach(() => {
  localStorage.clear()
  document.body.innerHTML = ''
})

describe('attribution', () => {
  it('sends the campaign and the outside referrer of this page, and keeps nothing without consent', async () => {
    await visit({
      url: '/offer?utm_source=test&utm_medium=cpc&gclid=abc',
      referrer: 'https://search.example/q',
    })

    expect(submit()).toEqual({
      utm_source: 'test',
      utm_medium: 'cpc',
      webx_referrer: 'https://search.example/q',
      webx_landing: `${location.origin}/offer?utm_source=test&utm_medium=cpc&gclid=abc`,
    })
    expect(localStorage.length).toBe(0)
  })

  it('names no landing page and no referrer when the visitor came from another page of the site', async () => {
    await visit({ url: '/contacts', referrer: `${location.origin}/` })

    expect(submit()).toEqual({})
  })

  it('leaves a form that did not ask for it alone, and what the form has already', async () => {
    await visit({ url: '/?utm_source=test' })

    expect(submit({ attribution: false })).toEqual({})
    expect(submit({ fields: { utm_source: 'own' } }).utm_source).toBe('own')
  })

  it('keeps the first touch with consent and never writes over it', async () => {
    await visit({ url: '/?utm_source=first', referrer: 'https://a.example/', statistics: true })
    await visit({
      url: '/sale?utm_source=second',
      referrer: 'https://b.example/',
      statistics: true,
    })
    await visit({ url: '/contacts', referrer: `${location.origin}/sale`, statistics: true })

    expect(kept().first.utm).toEqual({ utm_source: 'first' })
    expect(kept().last.utm).toEqual({ utm_source: 'second' })
    expect(submit()).toEqual({
      utm_source: 'second',
      webx_referrer: 'https://b.example/',
      webx_landing: `${location.origin}/sale?utm_source=second`,
      'webx_utm_first[utm_source]': 'first',
    })
  })

  it('starts again when the first touch is older than 90 days', async () => {
    const old = Date.now() - 91 * 86400000
    localStorage.setItem(
      KEY,
      JSON.stringify({
        first: { utm: { utm_source: 'old' }, referrer: '', landing: '/', at: old },
        last: { utm: { utm_source: 'old' }, referrer: '', landing: '/', at: old },
      }),
    )

    await visit({ url: '/?utm_source=new', statistics: true })

    expect(kept().first.utm).toEqual({ utm_source: 'new' })
  })

  it('takes the key away when consent is taken back', async () => {
    await visit({ url: '/?utm_source=first', statistics: true })
    expect(kept()).not.toBeNull()

    window.webx.consent.has = () => false
    document.dispatchEvent(new CustomEvent('webx:consent', { detail: { categories: [] } }))

    expect(localStorage.getItem(KEY)).toBeNull()
  })

  it('forgets what was kept before when the page opens without consent', async () => {
    await visit({ url: '/?utm_source=first', statistics: true })
    await visit({ url: '/' })

    expect(localStorage.getItem(KEY)).toBeNull()
  })

  it('waits for the page to load before it asks about consent: consent.js comes after it', async () => {
    Object.defineProperty(document, 'readyState', { value: 'interactive', configurable: true })
    try {
      await visit({ url: '/?utm_source=first', statistics: true })
      expect(localStorage.getItem(KEY)).toBeNull()

      window.dispatchEvent(new Event('load'))
      expect(kept().first.utm).toEqual({ utm_source: 'first' })
    } finally {
      delete document.readyState
    }
  })
})
