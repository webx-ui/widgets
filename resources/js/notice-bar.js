/*
 * The announcement bar (spec §14). Its own file, claimed by `<x-webx-notice-bar>`.
 *
 * Closing it remembers its version — a hash of what it says — in `localStorage`, and the bar of
 * that version does not come back on any page; new words are a new version and come back for
 * everybody. The head already hid a remembered version before the body was painted (a few lines
 * `Widgets::finish()` writes there): here a remembered bar only leaves the page for good.
 *
 * The close button is the script's: the server prints it `hidden`, since without JavaScript there
 * is nothing to remember a click with. A keyboard that closed the bar goes on from the top of the
 * page, where the bar was.
 */

import '../css/notice-bar.css'

const webx = (window.webx ??= {})

export const STORAGE = 'webx-notice-bar'

/** The newest versions closed: enough for every bar a site has had in a while, and no more. */
const KEPT = 20

function closed() {
  try {
    const list = JSON.parse(localStorage.getItem(STORAGE) || '[]')
    return Array.isArray(list) ? list.filter((item) => typeof item === 'string') : []
  } catch {
    return []
  }
}

function remember(version) {
  const list = closed().filter((item) => item !== version)
  list.push(version)
  try {
    localStorage.setItem(STORAGE, JSON.stringify(list.slice(-KEPT)))
  } catch {
    // Storage refused (a private window, a full quota): closed on this page only.
  }
}

export function noticeBar(root) {
  const version = root.getAttribute('data-webx-notice-bar') || ''
  if (version && closed().includes(version)) {
    root.hidden = true
    return
  }

  const button = root.querySelector('.webx-notice-bar__close')
  if (!button) return

  const close = () => {
    const focused = root.contains(document.activeElement)
    if (version) remember(version)
    root.hidden = true
    // The keyboard goes on from the top of the page: the next Tab is "Skip to content".
    if (focused) {
      const body = document.body
      body.setAttribute('tabindex', '-1')
      body.focus({ preventScroll: true })
      body.addEventListener('blur', () => body.removeAttribute('tabindex'), { once: true })
    }
    root.dispatchEvent(new CustomEvent('webx:notice-bar', { bubbles: true, detail: { version } }))
  }

  button.hidden = false
  button.addEventListener('click', close)

  return () => {
    button.removeEventListener('click', close)
    button.hidden = true
  }
}

webx.widget?.('notice-bar', '[data-webx-notice-bar]', noticeBar)
