import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  vi.useRealTimers()
  vi.restoreAllMocks()
})

const TIMES = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30']

const config = (zone, weekdays = [1, 2, 3, 4, 5, 6, 7]) =>
  JSON.stringify({
    step: 30,
    weekdays,
    zone,
    times: TIMES.map((value) => ({ value, label: value })),
    words: {
      previous: 'Previous month',
      next: 'Next month',
      none: 'No times left today',
      unavailable: 'unavailable',
      time: 'Time',
      times: 'Times on :date',
    },
  }).replace(/"/g, '&quot;')

/** The field as `<x-webx-field type="datetime">` prints it, in a form of the widgets. */
async function start({ now, zone = 'Asia/Tokyo', weekdays, date = '', time = '' }) {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(now))
  document.documentElement.lang = 'en'
  document.body.innerHTML = `
    <form data-webx-send action="/send" method="post">
      <fieldset class="webx-field webx-field--datetime" data-webx-field="fields.meeting">
        <legend class="webx-field__label webx-field__label--legend">Meeting</legend>
        <div class="webx-datetime" data-webx-datetime="${config(zone, weekdays)}">
          <div class="webx-datetime__plain">
            <input type="date" id="meeting" name="fields[meeting][date]" value="${date}">
            <select id="meeting-time" name="fields[meeting][time]">
              <option value="">Choose…</option>
              ${TIMES.map((one) => `<option value="${one}"${one === time ? ' selected' : ''}>${one}</option>`).join('')}
            </select>
          </div>
          <p class="webx-datetime__zone" id="meeting-zone">Times are in ${zone}</p>
        </div>
        <p class="webx-field__error" hidden></p>
      </fieldset>
      <button type="submit">Send</button>
    </form>`
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./form.js')
  await import('./summary.js')
  await import('./datetime.js')
  return document.querySelector('form')
}

const day = (iso) => document.querySelector(`[data-day="${iso}"]`)
const month = () => document.querySelector('.webx-datetime__month').textContent
const times = () =>
  Array.from(document.querySelectorAll('.webx-datetime__time input'), (one) => one.value)
const next = () => document.querySelectorAll('.webx-datetime__nav')[1].click()
const key = (target, name) =>
  target.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true }))

describe('datetime', () => {
  it('counts today in the zone of the site, not of the browser', async () => {
    // 23:30 of the 11th in UTC — the browser's zone here — is 08:30 of the 12th in Tokyo.
    await start({ now: '2026-12-11T23:30:00Z' })

    expect(month()).toBe('December 2026')
    expect(day('2026-12-11').getAttribute('aria-disabled')).toBe('true')
    expect(day('2026-12-11').getAttribute('aria-label')).toBe(
      'Friday, December 11, 2026, unavailable',
    )
    expect(day('2026-12-12').classList.contains('is-today')).toBe(true)
    expect(day('2026-12-12').hasAttribute('aria-disabled')).toBe(false)
    // The first day open takes the focus of the grid.
    expect(day('2026-12-12').tabIndex).toBe(0)
    expect(document.querySelector('.webx-datetime__plain').hidden).toBe(true)
  })

  it('shows only the times of today still to come, in the zone of the site', async () => {
    // 10:10 in Tokyo.
    await start({ now: '2026-12-12T01:10:00Z' })

    day('2026-12-12').click()
    expect(times()).toEqual(['10:30', '11:00', '11:30'])
    expect(document.querySelector('.webx-datetime__legend').textContent).toBe(
      'Times on December 12',
    )

    day('2026-12-13').click()
    expect(times()).toEqual(TIMES)
  })

  it('says a today with no time left, and opens on the next day that has one', async () => {
    // 12:00 in Tokyo: the last time, 11:30, is gone.
    await start({ now: '2026-12-12T03:00:00Z' })

    expect(day('2026-12-12').getAttribute('aria-label')).toContain('No times left today')
    expect(day('2026-12-13').tabIndex).toBe(0)
  })

  it('does not choose a day of the week that is not offered', async () => {
    // Monday to Friday; 2026-12-12 is a Saturday.
    const form = await start({ now: '2026-12-10T00:00:00Z', weekdays: [1, 2, 3, 4, 5] })

    day('2026-12-12').click()
    expect(form.querySelector('[name="fields[meeting][date]"]').value).toBe('')
    expect(document.querySelector('.webx-datetime__times').hidden).toBe(true)
    expect(day('2026-12-12').getAttribute('aria-disabled')).toBe('true')
  })

  it('writes the day and the time into the controls the form sends', async () => {
    const form = await start({ now: '2026-12-10T00:00:00Z' })
    const changed = vi.fn()
    form.addEventListener('change', changed)

    day('2026-12-15').click()
    const radio = document.querySelector('.webx-datetime__time input[value="10:30"]')
    radio.checked = true
    radio.dispatchEvent(new Event('change', { bubbles: true }))

    const data = new FormData(form)
    expect(data.get('fields[meeting][date]')).toBe('2026-12-15')
    expect(data.get('fields[meeting][time]')).toBe('10:30')
    // The day's input and the list each told the form once; the radio's own change stays inside.
    expect(changed.mock.calls.map(([event]) => event.target.name)).toEqual([
      'fields[meeting][date]',
      'fields[meeting][time]',
    ])
    expect(form.querySelector('[data-webx-field]').dataset.webxAnswer).toBe(
      'Tuesday, December 15, 2026, 10:30 (Asia/Tokyo)',
    )
    expect(day('2026-12-15').classList.contains('is-selected')).toBe(true)
  })

  it('a time of another day that is not on this one is let go', async () => {
    // 10:10 in Tokyo: today has no 09:30.
    const form = await start({ now: '2026-12-12T01:10:00Z', date: '2026-12-14', time: '09:30' })

    expect(times()).toEqual(TIMES)
    day('2026-12-12').click()
    expect(form.querySelector('select').value).toBe('')
  })

  it('moves through the days with the keyboard as the date picker of the APG does', async () => {
    await start({ now: '2026-12-10T00:00:00Z' })
    const grid = document.querySelector('[role="grid"]')

    day('2026-12-10').focus()
    key(document.activeElement, 'ArrowRight')
    expect(document.activeElement.dataset.day).toBe('2026-12-11')
    key(document.activeElement, 'ArrowDown')
    expect(document.activeElement.dataset.day).toBe('2026-12-18')
    key(document.activeElement, 'Home')
    expect(document.activeElement.dataset.day).toBe('2026-12-14')
    key(document.activeElement, 'End')
    expect(document.activeElement.dataset.day).toBe('2026-12-20')
    key(document.activeElement, 'PageDown')
    expect(document.activeElement.dataset.day).toBe('2027-01-20')
    expect(month()).toBe('January 2027')
    key(document.activeElement, 'ArrowUp')
    expect(document.activeElement.dataset.day).toBe('2027-01-13')
    // Never before this month: the focus stops at its first day.
    key(document.activeElement, 'PageUp')
    key(document.activeElement, 'PageUp')
    expect(document.activeElement.dataset.day).toBe('2026-12-01')
    expect(grid.querySelectorAll('[tabindex="0"]')).toHaveLength(1)
    // A day gone takes the focus too, and says so; it is not chosen.
    expect(document.activeElement.getAttribute('aria-disabled')).toBe('true')
  })

  it('pages through months with ‹ ›, never back past this one', async () => {
    await start({ now: '2026-12-10T00:00:00Z' })
    const [previous, next] = document.querySelectorAll('.webx-datetime__nav')

    expect(previous.disabled).toBe(true)
    expect(previous.getAttribute('aria-label')).toBe('Previous month')
    next.click()
    expect(month()).toBe('January 2027')
    expect(previous.disabled).toBe(false)
    previous.click()
    expect(month()).toBe('December 2026')
  })

  it('comes back as the page left it and starts again after a reset', async () => {
    const form = await start({ now: '2026-12-10T00:00:00Z', date: '2027-02-03', time: '11:00' })

    expect(month()).toBe('February 2027')
    expect(day('2027-02-03').classList.contains('is-selected')).toBe(true)
    expect(document.querySelector('.webx-datetime__time input:checked').value).toBe('11:00')

    // A reset is back to what the page printed: the day it came back with.
    day('2027-02-10').click()
    form.reset()
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(form.querySelector('input[type=date]').value).toBe('2027-02-03')
    expect(day('2027-02-03').classList.contains('is-selected')).toBe(true)
  })

  it('starts again after the reset of a fresh form', async () => {
    const form = await start({ now: '2026-12-10T00:00:00Z' })

    next()
    day('2027-01-12').click()
    form.reset()
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(month()).toBe('December 2026')
    expect(document.querySelector('.webx-datetime__times').hidden).toBe(true)
    expect(form.querySelector('[data-webx-field]').dataset.webxAnswer).toBeUndefined()
  })

  it('puts the focus of an error on the day of the grid, not on the hidden date', async () => {
    const form = await start({ now: '2026-12-10T00:00:00Z' })
    form
      .querySelector('[data-webx-field]')
      .setAttribute(
        'data-webx-rules',
        JSON.stringify([{ rule: 'required', message: 'Choose a day.' }]),
      )

    form.requestSubmit()
    expect(form.querySelector('.webx-field__error').textContent).toBe('Choose a day.')
    expect(document.activeElement).toBe(day('2026-12-10'))
  })

  it('leaves the controls as they were when it is taken away', async () => {
    await start({ now: '2026-12-10T00:00:00Z' })

    window.webx.unmount()
    expect(document.querySelector('.webx-datetime__picker')).toBeNull()
    expect(document.querySelector('.webx-datetime__plain').hidden).toBe(false)
  })
})
