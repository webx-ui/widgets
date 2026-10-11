/*
 * Table of contents (spec §14). Its own file, claimed by `<x-webx-toc>`.
 *
 * The server made the list and gave the headings their ids: every link works without this file.
 * Here two things only. The section being read is marked (`is-current`, `aria-current`) as the
 * page scrolls: the last heading above a line a quarter down the screen under the header, or the
 * last one in view at the very bottom of the page. And where the stylesheet puts the list above
 * the text — a narrow container — the list folds into a bar that says that section and opens it
 * as a dropdown (§6.3): Esc, a click elsewhere or a link closes it.
 *
 * Folded, the bar sticks under the header, so a heading reached by a link would stop under the
 * bar: each heading of the list gets the bar's height as its `scroll-margin-top`.
 */

import '../css/toc.css'

const webx = (window.webx ??= {})

const headerHeight = () =>
  parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--webx-header-height')) ||
  0

export function toc(root) {
  const nav = root.querySelector('.webx-toc__nav')
  const toggle = root.querySelector('.webx-toc__toggle')
  const panel = root.querySelector('.webx-toc__panel')
  const title = root.querySelector('.webx-toc__title')
  const current = root.querySelector('.webx-toc__current')
  if (!nav || !panel) return

  const entries = Array.from(nav.querySelectorAll('.webx-toc__link'))
    .map((link) => {
      const id = decodeURIComponent((link.getAttribute('href') || '').slice(1))
      return { link, target: id ? document.getElementById(id) : null }
    })
    .filter((entry) => entry.target)
  if (entries.length === 0) return

  const folds = root.getAttribute('data-fold') !== 'never' && toggle
  const ready = [nav, title, toggle, panel].filter(Boolean)
  let marked = null
  let folded = false

  const mark = () => {
    const line = headerHeight() + window.innerHeight / 4
    const bottom =
      Math.ceil(window.scrollY + window.innerHeight) >= document.documentElement.scrollHeight - 2
    let found = null
    for (const entry of entries) {
      const top = entry.target.getBoundingClientRect().top
      if (bottom ? top < window.innerHeight : top <= line) found = entry
    }
    if (found === marked) return
    marked?.link.classList.remove('is-current')
    marked?.link.removeAttribute('aria-current')
    marked = found
    if (found) {
      found.link.classList.add('is-current')
      found.link.setAttribute('aria-current', 'true')
    }
    if (current) current.textContent = found ? found.link.textContent : ''
  }

  const open = (yes) => {
    panel.classList.toggle('is-open', yes)
    toggle?.setAttribute('aria-expanded', String(yes))
  }

  // Whether the stylesheet folded the list here: the bar is shown only then.
  const measure = () => {
    folded = Boolean(folds) && getComputedStyle(toggle).display !== 'none'
    if (!folded) open(false)
    const margin = folded ? `${nav.getBoundingClientRect().height + 8}px` : ''
    for (const { target } of entries) target.style.scrollMarginTop = margin
  }

  let frame = 0
  const update = () => {
    if (frame) return
    frame = requestAnimationFrame(() => {
      frame = 0
      mark()
    })
  }

  const click = () => open(!panel.classList.contains('is-open'))
  const follow = (event) => {
    if (event.target.closest('.webx-toc__link') && folded) open(false)
  }
  const away = (event) => {
    if (panel.classList.contains('is-open') && !nav.contains(event.target)) open(false)
  }
  const key = (event) => {
    if (event.key !== 'Escape' || !panel.classList.contains('is-open')) return
    open(false)
    toggle?.focus()
  }

  if (folds) {
    for (const el of ready) el.classList.add('is-ready')
    toggle.addEventListener('click', click)
  }
  panel.addEventListener('click', follow)
  document.addEventListener('pointerdown', away)
  nav.addEventListener('keydown', key)
  window.addEventListener('scroll', update, { passive: true })
  window.addEventListener('resize', update, { passive: true })
  const sizes = new ResizeObserver(() => {
    measure()
    update()
  })
  sizes.observe(root)
  measure()
  mark()

  return () => {
    cancelAnimationFrame(frame)
    sizes.disconnect()
    toggle?.removeEventListener('click', click)
    panel.removeEventListener('click', follow)
    document.removeEventListener('pointerdown', away)
    nav.removeEventListener('keydown', key)
    window.removeEventListener('scroll', update)
    window.removeEventListener('resize', update)
    for (const el of ready) el.classList.remove('is-ready', 'is-open')
    toggle?.setAttribute('aria-expanded', 'false')
    for (const { link, target } of entries) {
      link.classList.remove('is-current')
      link.removeAttribute('aria-current')
      target.style.removeProperty('scroll-margin-top')
    }
    if (current) current.textContent = ''
  }
}

webx.widget?.('toc', '[data-webx-toc]', toc)
