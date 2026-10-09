/*
 * Video (spec §10): the facade of a YouTube or Vimeo video, and the placeholder before consent
 * (§9.4). Its own file, claimed by `<x-webx-video>`; it registers through `webx.widget`.
 *
 * The server prints the state the cookie says, so nothing flashes: a link to the video with its
 * poster, and — while `media` is not agreed to — the notice over it with "Load" and "Always load
 * videos". Here the link becomes a play button, and the player goes in only on a click on it or
 * on "Load", in place of the facade, with the focus in it. "Always load videos" is the consent:
 * through `webx.consent.set()`, so the banner is answered too and every placeholder on the page
 * becomes a facade without a reload (`webx:consent`), and the one clicked plays. A file of the
 * site is a plain <video> and needs nothing from here.
 */

import '../css/video.css'

const webx = (window.webx ??= {})

const ALLOW =
  'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen'

function settings(root) {
  try {
    return JSON.parse(root.getAttribute('data-webx-video') || '{}')
  } catch {
    return {}
  }
}

/** The link the server printed, as a button with the same insides: it plays here, it does not leave. */
function asButton(facade) {
  if (!(facade instanceof HTMLAnchorElement)) return facade
  const button = document.createElement('button')
  button.type = 'button'
  button.className = facade.className
  button.setAttribute('aria-label', facade.getAttribute('aria-label') ?? '')
  button.append(...facade.childNodes)
  facade.replaceWith(button)
  return button
}

export function video(root) {
  const { embed, title } = settings(root)
  if (!embed || root.querySelector('.webx-video__frame')) return

  const consent = root.querySelector('.webx-video__consent')
  let facade = root.querySelector('.webx-video__facade')

  const play = () => {
    if (root.querySelector('.webx-video__frame')) return
    const frame = document.createElement('iframe')
    frame.className = 'webx-video__frame'
    frame.src = embed
    frame.title = title || ''
    frame.allow = ALLOW
    frame.allowFullscreen = true
    frame.referrerPolicy = 'strict-origin-when-cross-origin'
    consent?.remove()
    root.classList.remove('is-blocked')
    root.classList.add('is-playing')
    if (facade) facade.replaceWith(frame)
    else root.prepend(frame)
    facade = null
    frame.focus()
  }

  // Agreed to: the notice goes, the play button is the way in.
  const unblock = () => {
    if (!root.classList.contains('is-blocked')) return
    root.classList.remove('is-blocked')
    consent?.remove()
    if (facade) facade.inert = false
  }

  const onClick = (event) => {
    const target = event.target instanceof Element ? event.target : null
    if (!target) return
    if (facade && facade.contains(target) && !root.classList.contains('is-blocked')) {
      event.preventDefault()
      play()
    } else if (target.closest('[data-webx-video-load]')) {
      play()
    } else if (target.closest('[data-webx-video-always]')) {
      // Without the banner's script there is no consent to give: this one plays all the same.
      if (webx.consent?.set) webx.consent.set([...webx.consent.categories, 'media'])
      play()
    }
  }

  const onConsent = (event) => {
    if (event.detail?.categories?.includes('media')) unblock()
  }

  facade = asButton(facade)
  // Behind the notice the play button is not a way in, for the mouse or for Tab.
  if (facade && root.classList.contains('is-blocked')) facade.inert = true
  if (root.classList.contains('is-blocked') && webx.consent?.has?.('media')) unblock()
  root.classList.add('is-ready')

  root.addEventListener('click', onClick)
  document.addEventListener('webx:consent', onConsent)

  return () => {
    root.removeEventListener('click', onClick)
    document.removeEventListener('webx:consent', onConsent)
    root.classList.remove('is-ready')
  }
}

webx.widget?.('video', '[data-webx-video]', video)
