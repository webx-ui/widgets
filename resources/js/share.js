/*
 * Share (spec §14). Its own file, claimed by `<x-webx-share>`.
 *
 * The links the server printed work as they are. Here "Copy link", which needs a script, and on
 * a phone the system's share sheet: where `navigator.share` exists and the pointer is a finger,
 * one "Share" button stands in for the row of networks — the sheet knows the apps the visitor
 * has. A desktop browser with `navigator.share` keeps the links: its sheet is a poorer list.
 */

import '../css/share.css'

const webx = (window.webx ??= {})

function settings(root) {
  try {
    return JSON.parse(root.getAttribute('data-webx-share') || '{}')
  } catch {
    return {}
  }
}

/** The clipboard API, or the selection a browser without it — or without a secure page — copies. */
async function copy(text) {
  if (navigator.clipboard?.writeText) {
    try {
      await navigator.clipboard.writeText(text)
      return true
    } catch {
      // Refused (no permission, not a secure page): the old way below.
    }
  }
  const field = document.createElement('textarea')
  field.value = text
  field.setAttribute('readonly', '')
  field.style.position = 'fixed'
  field.style.opacity = '0'
  document.body.append(field)
  field.select()
  let done = false
  try {
    done = document.execCommand('copy')
  } catch {
    done = false
  }
  field.remove()
  return done
}

export function share(root) {
  const { url = location.href, title = '', copied = '', failed = '' } = settings(root)
  const list = root.querySelector('.webx-share__list')
  const native = root.querySelector('[data-webx-share-native]')
  const status = root.querySelector('.webx-share__status')
  const copyButton = root.querySelector('[data-webx-share-copy]')
  let timer = 0

  const say = (text) => {
    if (!status) return
    status.textContent = text
    clearTimeout(timer)
    timer = setTimeout(() => (status.textContent = ''), 3000)
  }

  const onCopy = async () => say((await copy(url)) ? copied : failed)

  const sheet = () => {
    navigator.share({ url, ...(title ? { title } : {}) }).catch(() => {
      // Closed without sharing, or refused: nothing to say.
    })
  }

  const phone =
    typeof navigator.share === 'function' &&
    (window.matchMedia?.('(pointer: coarse)').matches ?? false)

  if (phone && native && list) {
    native.hidden = false
    list.hidden = true
    root.classList.add('is-native')
    native.addEventListener('click', sheet)
  }
  copyButton?.addEventListener('click', onCopy)
  root.classList.add('is-ready')

  return () => {
    clearTimeout(timer)
    copyButton?.removeEventListener('click', onCopy)
    native?.removeEventListener('click', sheet)
    if (native) native.hidden = true
    if (list) list.hidden = false
    root.classList.remove('is-native', 'is-ready')
  }
}

webx.widget?.('share', '[data-webx-share]', share)
