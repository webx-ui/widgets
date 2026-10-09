/*
 * The contacts widgets (spec §12): the phones, the hours, quick contact, the bottom bar and the
 * networks. Its own file, claimed by their components, after the runtime: it registers through
 * `webx.widget` and imports nothing the runtime has. The phones' and the hours' dropdowns are the
 * runtime's; what is here is what only these need.
 *
 * Hours: the server wrote the status as of the response, but a page may lie in a cache for hours.
 * So the status is worked out again here, in the site's time zone — never the visitor's — and
 * again at every minute after. Every word it may say comes in `data-webx-hours`, already written
 * in the page's language: the script picks one and formats nothing (`toLocaleString` would write
 * the time the browser's way, not the page's).
 *
 * Quick contact: while the cookie banner is on the screen, the button stands above it.
 */

import '../css/contacts.css'

const webx = (window.webx ??= {})
const DAY = 1440
const HORIZON = 14

/** The date, the weekday and the minute of the day of `now` on the clocks of `zone`. */
function clock(now, zone) {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-US', {
      timeZone: zone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    })
      .formatToParts(now)
      .map(({ type, value }) => [type, value]),
  )
  return {
    date: `${parts.year}-${parts.month}-${parts.day}`,
    minute: Number(parts.hour) * 60 + Number(parts.minute),
  }
}

/** A date `days` after `date`, and its ISO weekday — by the calendar, whatever the clocks do. */
function shift(date, days) {
  const [y, m, d] = date.split('-').map(Number)
  const day = new Date(Date.UTC(y, m - 1, d + days))
  return { date: day.toISOString().slice(0, 10), weekday: day.getUTCDay() || 7 }
}

/** The same answer `Hours::status()` gives on the server, from the same data. */
export function status(hours, now = new Date()) {
  const { date, minute } = clock(now, hours.zone)
  const on = (offset) => {
    const day = shift(date, offset)
    return hours.special[day.date] ?? hours.week[day.weekday] ?? []
  }

  let until = null
  for (const [opens, closes] of on(0)) {
    if (opens <= minute && minute < closes) until = { offset: 0, minute: closes }
  }
  for (const [, closes] of on(-1)) {
    if (closes > DAY && minute < closes - DAY) until = { offset: -1, minute: closes }
  }

  if (until) {
    // Into the next day's opening at midnight is not the end: 24/7 is open until never.
    for (let hop = 0; hop < 8; hop++) {
      const offset = until.offset + Math.floor(until.minute / DAY)
      const at = until.minute % DAY
      const next = on(offset).find(([opens, closes]) => opens <= at && at < closes)
      if (!next || offset * DAY + next[1] <= until.offset * DAY + until.minute) {
        return { kind: 'open', open: true, minute: until.minute % DAY }
      }
      until = { offset, minute: next[1] }
    }
    return { kind: 'always', open: true }
  }

  const prefix = on(0).length === 0 ? 'off-' : ''
  for (let ahead = 0; ahead <= HORIZON; ahead++) {
    for (const [opens] of on(ahead)) {
      if (ahead > 0 || opens > minute) {
        const kind = ahead === 0 ? 'today' : ahead === 1 ? `${prefix}tomorrow` : `${prefix}later`
        return { kind, open: false, minute: opens % DAY, weekday: shift(date, ahead).weekday }
      }
    }
  }
  return { kind: prefix ? 'off' : 'closed', open: false }
}

function sentence(hours, answer) {
  return (hours.words[answer.kind] ?? '')
    .replace(':time', hours.times[answer.minute] ?? '')
    .replace(':day', hours.days[answer.weekday] ?? '')
}

function openingHours(root) {
  let hours
  try {
    hours = JSON.parse(root.dataset.webxHours)
  } catch {
    return
  }
  let timer = 0

  const update = () => {
    const now = new Date()
    const answer = status(hours, now)
    for (const el of root.querySelectorAll('[data-webx-hours-status]')) {
      el.textContent = sentence(hours, answer)
      el.classList.toggle('is-open', answer.open)
      el.classList.toggle('is-closed', !answer.open)
    }

    const { date } = clock(now, hours.zone)
    const weekday = shift(date, 0).weekday
    for (const row of root.querySelectorAll('[data-weekdays]')) {
      const days = row.dataset.weekdays.split(',').map(Number)
      row.classList.toggle('is-today', !hours.special[date] && days.includes(weekday))
    }
    for (const row of root.querySelectorAll('[data-date]')) {
      row.classList.toggle('is-today', row.dataset.date === date)
    }

    // At the turn of the next minute, when "open" may become "closed".
    timer = setTimeout(update, 60000 - (now.getTime() % 60000) + 50)
  }

  update()
  return () => clearTimeout(timer)
}

function contactButton(root) {
  const banner = document.querySelector('[data-webx-consent-banner]')
  if (!banner) return

  const lift = () => {
    const height = banner.hidden ? 0 : banner.getBoundingClientRect().height
    root.style.setProperty('--webx-contact-lift', `${Math.round(height)}px`)
  }
  const sizes = new ResizeObserver(lift)
  const shown = new MutationObserver(lift)
  sizes.observe(banner)
  shown.observe(banner, { attributes: true, attributeFilter: ['hidden'] })
  lift()

  return () => {
    sizes.disconnect()
    shown.disconnect()
    root.style.removeProperty('--webx-contact-lift')
  }
}

webx.widget?.('hours', '[data-webx-hours]', openingHours)
webx.widget?.('contact-button', '[data-webx-contact-button]', contactButton)
