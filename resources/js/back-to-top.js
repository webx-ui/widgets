/*
 * Back to top (spec §14). Its own file, claimed by `<x-webx-back-to-top>`.
 *
 * The server prints a link to #top in the flow; here it becomes the button in the corner of the
 * window, shown once the page is `after` screens down. A click scrolls up — smoothly, unless the
 * visitor asked for reduced motion — and puts the focus at the top of the page, so that Tab goes
 * on from the skip link rather than from the footer the button was in.
 *
 * What else stands at the bottom of the window — the cookie banner, the contact bar, the
 * quick-contact button of the same corner — lifts it: it never covers them, nor they it.
 */

import '../css/back-to-top.css'

const webx = (window.webx ??= {})

/** What stands at the bottom of the window and may be under the button. */
const BELOW = '[data-webx-consent-banner], .webx-contact-bar__bar, [data-webx-contact-button]'

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

function settings(root) {
  try {
    return JSON.parse(root.getAttribute('data-webx-back-to-top') || '{}')
  } catch {
    return {}
  }
}

/** How far up from the bottom of the window the things standing there reach. */
function reach(root) {
  const corner = root.classList.contains('webx-back-to-top--bottom-start')
    ? 'bottom-start'
    : 'bottom-end'
  const below = [
    ...document.querySelectorAll('[data-webx-consent-banner], .webx-contact-bar__bar'),
    ...Array.from(document.querySelectorAll('[data-webx-contact-button]')).filter((button) =>
      button.classList.contains(`webx-contact-button--${corner}`),
    ),
  ]
  let height = 0
  for (const el of below) {
    if (el.hidden) continue
    const rect = el.getBoundingClientRect()
    if (!rect.height || rect.top >= window.innerHeight) continue
    height = Math.max(height, window.innerHeight - rect.top)
  }
  return Math.round(height)
}

export function backToTop(root) {
  const { after = 2 } = settings(root)
  const sizes = new ResizeObserver(() => lift())
  const shown = new MutationObserver(() => lift())
  const below = Array.from(document.querySelectorAll(BELOW))
  let frame = 0

  const lift = () => root.style.setProperty('--webx-back-to-top-lift', `${reach(root)}px`)

  const update = () => {
    frame = 0
    root.classList.toggle('is-shown', window.scrollY > after * window.innerHeight)
    lift()
  }
  const schedule = () => {
    if (!frame) frame = requestAnimationFrame(update)
  }

  const top = (event) => {
    event.preventDefault()
    window.scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' })
    // The page itself takes the focus: the next Tab is the first thing on it.
    const body = document.body
    const had = body.hasAttribute('tabindex')
    if (!had) body.setAttribute('tabindex', '-1')
    body.focus({ preventScroll: true })
    if (!had) body.addEventListener('blur', () => body.removeAttribute('tabindex'), { once: true })
  }

  root.classList.add('is-ready')
  for (const el of below) {
    sizes.observe(el)
    shown.observe(el, { attributes: true, attributeFilter: ['hidden', 'class'] })
    // The quick-contact button rises over the banner and settles back with a transition: where
    // it ends is known only then.
    el.addEventListener('transitionend', lift)
  }
  window.addEventListener('scroll', schedule, { passive: true })
  window.addEventListener('resize', schedule)
  root.addEventListener('click', top)
  update()

  return () => {
    cancelAnimationFrame(frame)
    below.forEach((el) => el.removeEventListener('transitionend', lift))
    sizes.disconnect()
    shown.disconnect()
    window.removeEventListener('scroll', schedule)
    window.removeEventListener('resize', schedule)
    root.removeEventListener('click', top)
    root.classList.remove('is-ready', 'is-shown')
    root.style.removeProperty('--webx-back-to-top-lift')
  }
}

webx.widget?.('back-to-top', '[data-webx-back-to-top]', backToTop)
