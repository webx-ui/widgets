/*
 * Countdown (spec §14). Its own file, claimed by `<x-webx-countdown>`.
 *
 * The server printed the time left at the response; the page may have waited in a cache since,
 * so the digits are worked out again at once and on every second from the moment it ends (a
 * timestamp: the site's time zone is already in it). At zero the `ended` text comes in, or the
 * timer goes, and with it the nearest `[data-webx-countdown-scope]` — the block it was the point of.
 */

import '../css/countdown.css'

const webx = (window.webx ??= {})

const UNITS = { days: 86400, hours: 3600, minutes: 60, seconds: 1 }

function settings(root) {
  try {
    return JSON.parse(root.getAttribute('data-webx-countdown') || '{}')
  } catch {
    return {}
  }
}

export function countdown(root) {
  const { end } = settings(root)
  if (!Number.isFinite(end) || root.classList.contains('is-over')) return
  const values = Array.from(root.querySelectorAll('.webx-countdown__value'))
  let timer = 0

  const over = () => {
    root.classList.add('is-over')
    root.querySelector('.webx-countdown__units')?.remove()
    root.querySelector('.webx-countdown__date')?.remove()
    const text = root.querySelector('.webx-countdown__ended')
    if (text) text.hidden = false
    else (root.closest('[data-webx-countdown-scope]') ?? root).hidden = true
  }

  const tick = () => {
    const ms = end - Date.now()
    let left = Math.max(0, Math.ceil(ms / 1000))
    if (left === 0) return over()
    for (const value of values) {
      const length = UNITS[value.dataset.unit]
      if (!length) continue
      const count = Math.floor(left / length)
      left -= count * length
      const digits = value.dataset.unit === 'days' ? String(count) : String(count).padStart(2, '0')
      if (value.textContent !== digits) value.textContent = digits
    }
    // On the turn of the next second, not a second after this one: timers drift.
    timer = setTimeout(tick, (ms % 1000 || 1000) + 10)
  }

  tick()

  return () => clearTimeout(timer)
}

webx.widget?.('countdown', '[data-webx-countdown]', countdown)
