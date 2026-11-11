/*
 * A day and a time (FORM_STEPS §6.4, §12.1): a calendar of a month and the times of the chosen day,
 * in place of the browser's date and the list of times. Its own file, claimed by
 * `<x-webx-field type="datetime">`: a form without one never loads it.
 *
 * Both controls stay in the form, hidden, and every choice is written back into them with a
 * `change` — so the checks, the errors of a 422, what is sent and the summary are form.js's as for
 * any field. What this adds:
 *
 *  - «today» and «now» in the site's zone (`Intl.DateTimeFormat` with `timeZone`), counted here
 *    rather than printed: a page cached whole would keep yesterday's;
 *  - days gone, days of the week not offered and today with no time left are `aria-disabled` —
 *    the focus goes over them and they say why, but they are not chosen; times of today already
 *    gone are not shown;
 *  - the month of the first day open, ‹ › through months, never before this one;
 *  - the grid of the APG date picker without the dialog: arrows — a day and a week, PageUp/PageDown
 *    — a month, Home/End — the week's ends; the month's name is a live region;
 *  - the times as a group of radios (`fieldset`/`legend` «Times on 12 December»), described by the zone;
 *  - `data-webx-answer` on the field — the day and the time in words, what the summary says.
 */

import '../css/datetime.css'

const webx = (window.webx ??= {})

/** What «now» is: the clock, or a test's stand-in. */
export const clock = { now: () => new Date() }

const DAY = 864e5
const pad = (n) => String(n).padStart(2, '0')
const iso = (time) => new Date(time).toISOString().slice(0, 10)
const time = (day) => Date.parse(`${day}T00:00:00Z`)
const weekday = (day) => ((new Date(time(day)).getUTCDay() + 6) % 7) + 1
const minutes = (clock) => +clock.slice(0, 2) * 60 + +clock.slice(3, 5)

/** The day and the minute of the day it is now in `zone`. */
export function nowIn(zone, date = clock.now()) {
  const parts = {}
  for (const part of new Intl.DateTimeFormat('en-US', {
    timeZone: zone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(date))
    parts[part.type] = part.value
  return {
    day: `${parts.year}-${parts.month}-${parts.day}`,
    minute: (+parts.hour % 24) * 60 + +parts.minute,
  }
}

let serial = 0
const EMPTY = '<td class="webx-datetime__cell"></td>'

export function datetime(root) {
  const field = root.closest('[data-webx-field]')
  const form = root.closest('form')
  const plain = root.querySelector('.webx-datetime__plain')
  const date = root.querySelector('input[type=date]')
  const select = root.querySelector('select')
  const zoneLine = root.querySelector('.webx-datetime__zone')
  let config
  try {
    config = JSON.parse(root.getAttribute('data-webx-datetime'))
  } catch {
    config = null
  }
  if (!field || !plain || !date || !select || !config) return undefined

  const { zone, weekdays, times, words } = config
  const lang = document.documentElement.lang || undefined
  const say = (options, day) =>
    new Intl.DateTimeFormat(lang, { timeZone: 'UTC', ...options }).format(time(day))
  const long = (day) =>
    say({ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }, day)
  const id = `webx-datetime-${++serial}`

  // The picker, between the hidden controls and the zone.
  const picker = document.createElement('div')
  picker.className = 'webx-datetime__picker'
  picker.innerHTML = `<div class="webx-datetime__calendar"><div class="webx-datetime__head"><button type="button" class="webx-datetime__nav" data-go="-1"><span aria-hidden="true">‹</span></button><p class="webx-datetime__month" id="${id}-month" aria-live="polite"></p><button type="button" class="webx-datetime__nav" data-go="1"><span aria-hidden="true">›</span></button></div><table class="webx-datetime__grid" role="grid" aria-labelledby="${id}-month"><thead><tr></tr></thead><tbody></tbody></table></div><fieldset class="webx-datetime__times" hidden><legend class="webx-datetime__legend"></legend><div class="webx-datetime__list"></div></fieldset>`
  const [previous, next] = picker.querySelectorAll('.webx-datetime__nav')
  previous.setAttribute('aria-label', words.previous)
  next.setAttribute('aria-label', words.next)
  const month = picker.querySelector('.webx-datetime__month')
  const body = picker.querySelector('tbody')
  const group = picker.querySelector('.webx-datetime__times')
  const legend = picker.querySelector('legend')
  const list = picker.querySelector('.webx-datetime__list')
  if (zoneLine) {
    picker.querySelector('table').setAttribute('aria-describedby', zoneLine.id)
    group.setAttribute('aria-describedby', zoneLine.id)
  }
  // The days of the week from a Monday (2024-01-01).
  picker.querySelector('thead tr').innerHTML = [1, 2, 3, 4, 5, 6, 7]
    .map(
      (n) =>
        `<th class="webx-datetime__weekday" scope="col" abbr="${say({ weekday: 'long' }, `2024-01-0${n}`)}">${say({ weekday: 'short' }, `2024-01-0${n}`)}</th>`,
    )
    .join('')

  let now = nowIn(zone)
  let focused = null
  let shown = null

  /** The times of a day still to come: today's from the next minute on. */
  const left = (day) =>
    day === now.day ? times.filter((one) => minutes(one.value) > now.minute) : times
  const open = (day) => day >= now.day && weekdays.includes(weekday(day)) && left(day).length > 0
  const first = () => {
    for (let at = time(now.day), n = 0; n < 400; n++, at += DAY) if (open(iso(at))) return iso(at)
    return now.day
  }
  const monthOf = (day) => `${day.slice(0, 7)}-01`
  const thisMonth = () => monthOf(now.day)

  function draw() {
    const start = time(shown)
    const lead = weekday(shown) - 1
    const end = time(monthOf(iso(start + 32 * DAY)))
    // Said only when it changes: the region is live.
    const name = say({ month: 'long', year: 'numeric' }, shown)
    if (month.textContent !== name) month.textContent = name
    previous.disabled = shown <= thisMonth()
    const rows = []
    let row = []
    for (let n = 0; n < lead; n++) row.push(EMPTY)
    for (let at = start; at < end; at += DAY) {
      const day = iso(at)
      const can = open(day)
      const why = can
        ? ''
        : `, ${day === now.day && weekdays.includes(weekday(day)) ? words.none : words.unavailable}`
      const chosen = day === date.value
      row.push(
        `<td class="webx-datetime__cell" role="gridcell" aria-selected="${chosen}"><button type="button" class="webx-datetime__day${can ? ' is-available' : ''}${chosen ? ' is-selected' : ''}${day === now.day ? ' is-today' : ''}" data-day="${day}" tabindex="${day === focused ? 0 : -1}" aria-label="${long(day)}${why}"${can ? '' : ' aria-disabled="true"'}>${+day.slice(8)}</button></td>`,
      )
      if (row.length === 7) rows.push(`<tr>${row.splice(0).join('')}</tr>`)
    }
    if (row.length) rows.push(`<tr>${row.join('')}${EMPTY.repeat(7 - row.length)}</tr>`)
    body.innerHTML = rows.join('')
    for (const button of body.querySelectorAll('button'))
      button.toggleAttribute('data-webx-focus', button.tabIndex === 0)
  }

  function drawTimes() {
    const day = date.value
    group.hidden = !day || !open(day)
    if (group.hidden) return list.replaceChildren()
    legend.textContent = words.times
      .split(':date')
      .join(say({ day: 'numeric', month: 'long' }, day))
    list.replaceChildren(
      ...left(day).map((one) => {
        const label = document.createElement('label')
        label.className = 'webx-datetime__time'
        const radio = document.createElement('input')
        radio.type = 'radio'
        radio.className = 'webx-field__check webx-datetime__radio'
        radio.name = `${id}-time`
        radio.value = one.value
        radio.checked = select.value === one.value
        radio.disabled = select.disabled
        label.append(radio, one.label)
        return label
      }),
    )
  }

  function answer() {
    const chosen = select.selectedOptions[0]
    if (date.value && select.value && chosen)
      field.dataset.webxAnswer = `${long(date.value)}, ${chosen.textContent.trim()} (${zone})`
    else delete field.dataset.webxAnswer
  }

  const tell = (control) => control.dispatchEvent(new Event('change', { bubbles: true }))

  /** Where the focus is among the days: `day`, within the months that may be shown. */
  function move(day, focus = true) {
    if (day < thisMonth()) day = thisMonth()
    focused = day
    if (monthOf(day) !== shown) shown = monthOf(day)
    draw()
    if (focus) body.querySelector(`[data-day="${day}"]`)?.focus()
  }

  function choose(day) {
    if (!open(day)) return
    date.value = day
    // The time of another day may not be on this one's list.
    if (!left(day).some((one) => one.value === select.value)) select.value = ''
    move(day)
    drawTimes()
    answer()
    tell(date)
  }

  function sync() {
    now = nowIn(zone)
    const day = date.value && open(date.value) ? date.value : null
    focused = day ?? first()
    shown = monthOf(focused)
    draw()
    drawTimes()
    answer()
  }

  const onClick = (event) => {
    const go = event.target.closest('[data-go]')
    const day = event.target.closest('[data-day]')
    if (go && !go.disabled) {
      const [y, m] = shown.split('-').map(Number)
      const to = new Date(Date.UTC(y, m - 1 + +go.dataset.go, 1))
      shown = iso(to)
      focused = shown === thisMonth() ? now.day : shown
      draw()
    } else if (day) {
      choose(day.dataset.day)
    }
  }

  const onKey = (event) => {
    const day = event.target.closest?.('[data-day]')?.dataset.day
    if (!day) return
    const at = time(day)
    const [y, m, d] = day.split('-').map(Number)
    const inMonth = (shift) => {
      const last = new Date(Date.UTC(y, m + shift, 0)).getUTCDate()
      return `${iso(Date.UTC(y, m - 1 + shift, 1)).slice(0, 8)}${pad(Math.min(d, last))}`
    }
    const target = {
      ArrowLeft: () => iso(at - DAY),
      ArrowRight: () => iso(at + DAY),
      ArrowUp: () => iso(at - 7 * DAY),
      ArrowDown: () => iso(at + 7 * DAY),
      Home: () => iso(at - (weekday(day) - 1) * DAY),
      End: () => iso(at + (7 - weekday(day)) * DAY),
      PageUp: () => inMonth(-1),
      PageDown: () => inMonth(1),
    }[event.key]
    if (!target) return
    event.preventDefault()
    move(target())
  }

  const onPick = (event) => {
    if (event.target.name !== `${id}-time`) return
    event.stopPropagation()
    select.value = event.target.value
    answer()
    tell(select)
  }

  const onReset = () => setTimeout(sync)

  picker.addEventListener('click', onClick)
  picker.addEventListener('keydown', onKey)
  picker.addEventListener('change', onPick)
  form?.addEventListener('reset', onReset)
  plain.hidden = true
  root.insertBefore(picker, zoneLine)
  root.classList.add('is-enhanced')
  sync()

  return () => {
    form?.removeEventListener('reset', onReset)
    picker.remove()
    plain.hidden = false
    root.classList.remove('is-enhanced')
    delete field.dataset.webxAnswer
  }
}

webx.widget?.('datetime', '[data-webx-datetime]', datetime)
