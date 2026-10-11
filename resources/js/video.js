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
 *
 * The background of a first screen (`variant="background"`, §10) lives here too, not in a file
 * of its own: it is a few lines, and a hero claims one file rather than two.
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

/** What the visitor chose with the pause button, kept across pages: a pause, never a play. */
const PAUSED = 'webx-video-background'

const remembered = () => {
  try {
    return localStorage.getItem(PAUSED) === 'paused' ? 'pause' : null
  } catch {
    return null
  }
}

const remember = (choice) => {
  try {
    if (choice === 'pause') localStorage.setItem(PAUSED, 'paused')
    else localStorage.removeItem(PAUSED)
  } catch {
    // Storage refused: the choice holds for this page.
  }
}

const motion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)')

/** Reduced motion or a connection saving data: the poster is enough, until the visitor says play. */
const quiet = () => Boolean(motion()?.matches || navigator.connection?.saveData)

/**
 * The background of a first screen (`variant="background"`): plays muted and looping while it is
 * on screen and the tab is in front, never by itself under reduced motion or on a connection
 * saving data. The poster stands until the first frame is up. The pause button is always there
 * (WCAG 2.2.2); the visitor's pause is remembered, their play holds for the page.
 */
export function background(root) {
  const media = root.querySelector('.webx-video__background')
  if (!media) return
  const button = root.querySelector('.webx-video__pause')
  const query = motion()
  let chosen = remembered()
  let seen = typeof IntersectionObserver === 'undefined'

  const wanted = () => (chosen ? chosen === 'play' : !quiet())

  const show = (paused) => {
    if (!button) return
    button.classList.toggle('is-paused', paused)
    button.setAttribute('aria-label', (paused ? button.dataset.play : button.dataset.pause) ?? '')
  }

  const update = () => {
    const run = wanted() && seen && !document.hidden
    if (run && media.paused) {
      // A browser that refuses even a muted play (a phone saving power): paused, the button says so.
      media.play()?.catch?.(() => {
        chosen = 'pause'
        show(true)
      })
    } else if (!run && !media.paused) {
      media.pause()
    }
    show(!wanted())
  }

  const toggle = () => {
    chosen = wanted() ? 'pause' : 'play'
    remember(chosen)
    update()
  }

  const framed = () => media.classList.add('is-playing')

  const observer =
    typeof IntersectionObserver === 'undefined'
      ? null
      : new IntersectionObserver((entries) => {
          const entry = entries.at(-1)
          if (!entry) return
          seen = entry.isIntersecting
          update()
        })

  media.muted = true
  media.addEventListener('playing', framed)
  button?.addEventListener('click', toggle)
  document.addEventListener('visibilitychange', update)
  query?.addEventListener?.('change', update)
  observer?.observe(root)
  update()

  return () => {
    observer?.disconnect()
    media.removeEventListener('playing', framed)
    button?.removeEventListener('click', toggle)
    document.removeEventListener('visibilitychange', update)
    query?.removeEventListener?.('change', update)
    media.pause()
  }
}

webx.widget?.('video', '[data-webx-video]', video)
webx.widget?.('video-background', '[data-webx-video-background]', background)
