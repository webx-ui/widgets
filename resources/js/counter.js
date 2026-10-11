/*
 * Counter (spec §14). Its own file, claimed by `<x-webx-counter>`.
 *
 * The server printed the final number. A counter below the screen when the page opens drops to
 * zero here and counts up once it comes into view; one already in view, or scrolled past, keeps
 * its number — dropping it would be a flash. Reduced motion, no IntersectionObserver, printing:
 * the number as printed. While it counts, the visible digits are hidden from screen readers and
 * the final number is said instead; at the end the server's own text is put back, so the last
 * frame is exactly what search engines read.
 */

import '../css/counter.css'

const webx = (window.webx ??= {})

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

function settings(root) {
  try {
    return JSON.parse(root.getAttribute('data-webx-counter') || '{}')
  } catch {
    return {}
  }
}

export function counter(root) {
  const number = root.querySelector('.webx-counter__number')
  const { value, decimals = 0, duration = 2000 } = settings(root)
  if (
    !number ||
    !Number.isFinite(value) ||
    reduced() ||
    typeof IntersectionObserver === 'undefined'
  )
    return
  if (root.getBoundingClientRect().top < window.innerHeight) return

  const final = number.textContent
  const lang = root.closest('[lang]')?.getAttribute('lang') || undefined
  let format
  try {
    format = new Intl.NumberFormat(lang, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    })
  } catch {
    format = new Intl.NumberFormat(undefined, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    })
  }
  const said = document.createElement('span')
  said.className = 'webx-counter__final'
  said.textContent = final
  let frame = 0
  let done = false

  const finish = () => {
    if (done) return
    done = true
    cancelAnimationFrame(frame)
    observer.disconnect()
    window.removeEventListener('beforeprint', finish)
    number.textContent = final
    number.removeAttribute('aria-hidden')
    number.style.removeProperty('min-inline-size')
    said.remove()
    root.classList.remove('is-counting')
  }

  const start = () => {
    const begun = performance.now()
    const step = (now) => {
      const progress = duration > 0 ? Math.min(1, (now - begun) / duration) : 1
      if (progress >= 1) return finish()
      // Ease out: fast at first, settling on the number.
      number.textContent = format.format(value * (1 - (1 - progress) ** 3))
      frame = requestAnimationFrame(step)
    }
    frame = requestAnimationFrame(step)
  }

  const observer = new IntersectionObserver((entries) => {
    if (!entries.some((entry) => entry.isIntersecting)) return
    observer.disconnect()
    start()
  })

  // As wide as the final number from the start: the line around it does not move as it grows.
  number.style.minInlineSize = `${number.getBoundingClientRect().width}px`
  number.setAttribute('aria-hidden', 'true')
  number.textContent = format.format(0)
  number.after(said)
  root.classList.add('is-counting')
  observer.observe(root)
  window.addEventListener('beforeprint', finish)

  return finish
}

webx.widget?.('counter', '[data-webx-counter]', counter)
