/*
 * What every map of `<x-webx-map>` does whoever draws it (spec §11): the notice of §9.4 and its
 * buttons, the wait for consent to `media`, the wait for the frame to come near the screen, and
 * the sleep until a click or the focus. Leaflet (map.js) and Google Maps (map-google.js) are the
 * drivers: each puts its map in a canvas and says how to wake it, put it to sleep and let it go.
 * Bundled into each of the two files, never loaded on its own: a page has one provider.
 *
 * A driver is `driver(canvas, config, { wake, fail })` returning `{ wake(), sleep(), resize(),
 * remove() }` — and `ownHint: true` when its map says itself how to wake it; it calls `wake` when its map was clicked or focused, and `fail` when there is no
 * map to be had — the shell then shows the place again, with the line of `config.failed`.
 */

const coarse = () => typeof matchMedia === 'function' && matchMedia('(pointer: coarse)').matches

export { coarse }

export function settings(root) {
  try {
    return JSON.parse(root.getAttribute('data-webx-map') || '{}')
  } catch {
    return {}
  }
}

export function shell(root, config, driver) {
  const webx = (window.webx ??= {})
  const place = root.querySelector('.webx-map__place')
  let handle = null
  let canvas = null
  let hint = null
  let failure = null
  let hintTimer = 0
  let active = false
  let watcher = null
  let resizer = null

  const wake = () => {
    if (!handle || active) return
    active = true
    handle.wake()
    root.classList.add('is-active')
    hint?.classList.remove('is-visible')
  }

  const sleep = () => {
    if (!handle || !active) return
    active = false
    handle.sleep()
    root.classList.remove('is-active')
  }

  const takeDown = () => {
    resizer?.disconnect()
    handle?.remove()
    canvas?.remove()
    handle = canvas = resizer = null
    active = false
    root.classList.remove('is-loaded', 'is-active')
  }

  // No map to be had — a key Google refused, a script that never came: the place again, said why.
  const fail = () => {
    takeDown()
    root.classList.add('is-failed')
    if (!place) return
    place.hidden = false
    if (config.failed && !failure) {
      failure = document.createElement('p')
      failure.className = 'webx-map__notice webx-map__failed'
      failure.textContent = config.failed
      const card = place.querySelector('.webx-map__card') ?? place
      card.append(failure)
    }
  }

  const build = () => {
    if (handle || root.classList.contains('is-failed')) return
    canvas = document.createElement('div')
    canvas.className = 'webx-map__canvas'
    root.prepend(canvas)

    const made = driver(canvas, config, { wake, fail })
    // The driver may have failed at once: fail() took the canvas away.
    if (!canvas) {
      made?.remove()
      return
    }
    handle = made
    if (place) place.hidden = true
    root.classList.add('is-loaded')

    // The map follows the window; the frame may change with the column alone.
    if (typeof ResizeObserver === 'function') {
      resizer = new ResizeObserver(() => handle?.resize())
      resizer.observe(root)
    }
  }

  // Near the screen, or at once where nothing tells when it is.
  const load = () => {
    if (handle || watcher) return
    if (typeof IntersectionObserver !== 'function') return build()
    watcher = new IntersectionObserver(
      (entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return
        watcher.disconnect()
        watcher = null
        build()
      },
      { rootMargin: '200px' },
    )
    watcher.observe(root)
  }

  const unblock = () => {
    root.classList.remove('is-blocked')
    root.querySelector('.webx-map__notice:not(.webx-map__failed)')?.remove()
    root.querySelector('.webx-map__actions')?.remove()
  }

  const onClick = (event) => {
    const target = event.target instanceof Element ? event.target : null
    if (target?.closest('[data-webx-map-load]')) {
      unblock()
      build()
    } else if (target?.closest('[data-webx-map-always]')) {
      // Without the banner's script there is no consent to give: this one loads all the same.
      if (webx.consent?.set) webx.consent.set([...webx.consent.categories, 'media'])
      unblock()
      build()
    }
  }

  const onConsent = (event) => {
    if (!event.detail?.categories?.includes('media')) return
    unblock()
    load()
  }

  const onWheel = () => {
    if (!handle || active || handle.ownHint) return
    if (!hint) {
      hint = document.createElement('p')
      hint.className = 'webx-map__hint'
      hint.setAttribute('aria-hidden', 'true')
      hint.textContent = config.hint || ''
      root.append(hint)
    }
    hint.classList.add('is-visible')
    clearTimeout(hintTimer)
    hintTimer = setTimeout(() => hint?.classList.remove('is-visible'), 1500)
  }

  const onPress = (event) => {
    if (!(event.target instanceof Node) || !root.contains(event.target)) sleep()
  }

  root.addEventListener('click', onClick)
  root.addEventListener('wheel', onWheel, { passive: true })
  root.addEventListener('mouseleave', sleep)
  document.addEventListener('pointerdown', onPress)
  document.addEventListener('webx:consent', onConsent)
  root.classList.add('is-ready')

  if (
    config.consent === false ||
    !root.classList.contains('is-blocked') ||
    webx.consent?.has?.('media')
  ) {
    unblock()
    load()
  }

  return () => {
    root.removeEventListener('click', onClick)
    root.removeEventListener('wheel', onWheel)
    root.removeEventListener('mouseleave', sleep)
    document.removeEventListener('pointerdown', onPress)
    document.removeEventListener('webx:consent', onConsent)
    watcher?.disconnect()
    clearTimeout(hintTimer)
    takeDown()
    hint?.remove()
    failure?.remove()
    hint = watcher = failure = null
    if (place) place.hidden = false
    root.classList.remove('is-ready', 'is-failed')
  }
}
