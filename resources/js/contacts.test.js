import { afterEach, describe, expect, it, vi } from 'vitest'

// The MutationObserver reports after a microtask.
const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

// jsdom has no ResizeObserver; the lift is read again on every report of one.
const resized = []
globalThis.ResizeObserver = class {
  constructor(callback) {
    this.callback = callback
    resized.push(this)
  }
  observe() {}
  disconnect() {}
}

// What HoursView::script() prints: Mon–Fri 9–13 and 14–19, Sat 22:00–02:00, Sun closed.
const hours = (extra = {}) => ({
  zone: 'Europe/Berlin',
  week: {
    1: [
      [540, 780],
      [840, 1140],
    ],
    2: [[540, 1140]],
    3: [[540, 1140]],
    4: [[540, 1140]],
    5: [[540, 1140]],
    6: [[1320, 1560]],
  },
  special: {},
  times: { 540: '09:00', 780: '13:00', 840: '14:00', 1140: '19:00', 1320: '22:00', 120: '02:00' },
  days: {
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
    7: 'Sunday',
  },
  words: {
    open: 'Open until :time',
    always: 'Open 24/7',
    today: 'Closed, opens at :time',
    tomorrow: 'Closed, opens tomorrow at :time',
    later: 'Closed, opens :day at :time',
    closed: 'Closed',
    off: 'Closed today',
    'off-tomorrow': 'Closed today, opens tomorrow at :time',
    'off-later': 'Closed today, opens :day at :time',
  },
  ...extra,
})

// Berlin is UTC+2 until 25 October 2026.
const berlin = (local) => new Date(`${local}:00+02:00`)

async function start(html) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  return import('./contacts.js')
}

const widget = (data) =>
  `<div data-webx-hours='${JSON.stringify(data)}'>
     <span class="webx-hours__status" data-webx-hours-status>Server's words</span>
     <table>
       <tr data-weekdays="1"></tr>
       <tr data-weekdays="2,3,4,5"></tr>
       <tr data-weekdays="6"></tr>
       <tr data-date="2026-10-14"></tr>
     </table>
   </div>`

afterEach(() => {
  vi.useRealTimers()
  document.body.innerHTML = ''
})

describe('opening hours', () => {
  it('says open or closed at the edges of an interval, in the site’s zone', async () => {
    const { status } = await start('')
    const at = (local) => status(hours(), berlin(local))

    expect(at('2026-10-12T08:59')).toMatchObject({ kind: 'today', open: false, minute: 540 })
    expect(at('2026-10-12T09:00')).toMatchObject({ kind: 'open', open: true, minute: 780 })
    expect(at('2026-10-12T13:00')).toMatchObject({ kind: 'today', minute: 840 })
    expect(at('2026-10-12T18:59')).toMatchObject({ kind: 'open', minute: 1140 })
    expect(at('2026-10-12T19:00')).toMatchObject({ kind: 'tomorrow', minute: 540 })

    // Saturday's night runs 22:00–02:00 into Sunday.
    expect(at('2026-10-17T23:00')).toMatchObject({ kind: 'open', minute: 120 })
    expect(at('2026-10-18T01:00')).toMatchObject({ kind: 'open', minute: 120 })
    // Sunday off, and Monday next.
    expect(at('2026-10-18T12:00')).toMatchObject({ kind: 'off-tomorrow', minute: 540 })
    expect(at('2026-10-16T20:00')).toMatchObject({ kind: 'tomorrow', minute: 1320 })

    // A visitor in New York at 03:00 their time is at 09:00 in Berlin: open.
    expect(status(hours(), new Date('2026-10-13T03:00:00-04:00'))).toMatchObject({ kind: 'open' })
  })

  it('takes a special date over its weekday and names the day it opens next', async () => {
    const { status } = await start('')
    const closed = hours({ special: { '2026-10-13': [], '2026-10-14': [] } })

    expect(status(closed, berlin('2026-10-13T10:00'))).toMatchObject({
      kind: 'off-later',
      weekday: 4,
    })
    expect(status(closed, berlin('2026-10-14T10:00'))).toMatchObject({
      kind: 'off-tomorrow',
      minute: 540,
    })
    expect(status(closed, berlin('2026-10-12T20:00'))).toMatchObject({ kind: 'later', weekday: 4 })

    const always = {
      ...hours(),
      week: Object.fromEntries([1, 2, 3, 4, 5, 6, 7].map((d) => [d, [[0, 1440]]])),
    }
    expect(status(always, berlin('2026-10-12T03:00'))).toMatchObject({ kind: 'always', open: true })
    expect(status({ ...hours(), week: {} }, berlin('2026-10-12T03:00'))).toMatchObject({
      kind: 'off',
    })
  })

  it('writes the server’s words again for now, marks today, and again at the next minute', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] })
    vi.setSystemTime(berlin('2026-10-14T18:59'))
    await start(widget(hours()))

    const status = document.querySelector('[data-webx-hours-status]')
    expect(status.textContent).toBe('Open until 19:00')
    expect(status.classList.contains('is-open')).toBe(true)
    expect(document.querySelector('[data-weekdays="2,3,4,5"]').classList.contains('is-today')).toBe(
      true,
    )
    expect(document.querySelector('[data-weekdays="1"]').classList.contains('is-today')).toBe(false)
    expect(document.querySelector('[data-date="2026-10-14"]').classList.contains('is-today')).toBe(
      true,
    )

    vi.advanceTimersByTime(61000)
    expect(status.textContent).toBe('Closed, opens tomorrow at 09:00')
    expect(status.classList.contains('is-closed')).toBe(true)
  })

  it('stops its clock when unmounted', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] })
    vi.setSystemTime(berlin('2026-10-14T18:59'))
    await start(widget(hours()))

    window.webx.unmount(document)
    vi.advanceTimersByTime(61000)
    expect(document.querySelector('[data-webx-hours-status]').textContent).toBe('Open until 19:00')
  })
})

describe('quick contact', () => {
  const page = `
    <div data-webx-contact-button></div>
    <section data-webx-consent-banner hidden></section>`

  it('stands above the cookie banner while the banner is on the screen', async () => {
    await start(page)
    const button = document.querySelector('[data-webx-contact-button]')
    const banner = document.querySelector('[data-webx-consent-banner]')
    banner.getBoundingClientRect = () => ({ height: 213.4 })

    expect(button.style.getPropertyValue('--webx-contact-lift')).toBe('0px')

    banner.hidden = false
    await settle()
    expect(button.style.getPropertyValue('--webx-contact-lift')).toBe('213px')

    banner.getBoundingClientRect = () => ({ height: 120 })
    resized.at(-1).callback()
    expect(button.style.getPropertyValue('--webx-contact-lift')).toBe('120px')

    banner.hidden = true
    await settle()
    expect(button.style.getPropertyValue('--webx-contact-lift')).toBe('0px')
  })

  it('has nothing to do on a page without the banner', async () => {
    await start('<div data-webx-contact-button></div>')
    expect(document.querySelector('[data-webx-contact-button]').getAttribute('style')).toBeNull()
  })
})
