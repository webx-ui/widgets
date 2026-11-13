/*
 * `data-webx-overlap` (spec §14.1): a section slides over the one above it. The section above —
 * the "pinned" one, the rendered element before this one (or before its parent, when this one
 * is the first) — stays where it is while this one comes up over it, `position: sticky`; its
 * words and layers, the elements marked `data-webx-overlap-fade`, fade out as it is covered.
 * Once this section has come up as far as it covers the pinned one, the pinned one goes up with
 * the page again, under it, until it is out of the screen. The edge picture over this section's
 * top (the fog) is printed by the server and stands there without any script.
 *
 * Distances, measured here and written as custom properties — the only layout this script
 * reads, again on resize, never on scroll. For the pinned section of height H, this one of
 * height Ho and a screen of height V (lengths along this section's view timeline, `cover`):
 *   - top   = min(0, V - H): a pinned section taller than the screen stops with its bottom at
 *             the bottom of the screen, so all of it has been seen;
 *   - start = max(0, V - H): from where this section's top is at the bottom of the screen to
 *             where the pinned one stops;
 *   - hold  = min(Ho, V, H): how far this one comes up over the pinned one. No more than its own
 *             height: when the pinned one goes up again it must stay covered, and a short section
 *             covers no more than itself;
 *   - leave = min(H, V): how far the pinned one then goes up, with the page, until it is gone.
 * The words fade over `hold`, the pinned section's `translate` takes it back up over `leave`.
 *
 * The edge picture. At rest only its lowest `--webx-overlap-peek` of the screen shows over the
 * section above (a clip in the stylesheet); in motion this section waits below its place with the
 * edge whole, so that the same part of the edge shows, and comes back faster than the page — the
 * fog climbs over the words above. For an edge of height E (no taller than `--webx-overlap-edge-max`
 * of the screen) and P = min(E, peek × V):
 *   - sink = E - P: how far below its place this section waits — its edge then stands P over the
 *            one above, and the edge covers the gap under it;
 *   - rise = min(hold, Ho - start): over how much of the way it comes back. No more than `hold`,
 *            so that it is in place before the pinned one goes up, and no more than it takes this
 *            section's bottom to come into the screen: what follows it is never seen covered.
 * A `translate`, written on this section: its place, its view timeline and the sizes above stay.
 *
 * The edge runs ahead of its section over the same `rise`, so that the fog covers the words above
 * while the section's own words are still low on the screen: a `transform` of its own, furthest
 * ahead halfway and a little ahead at the end and after it (the curve is the stylesheet's,
 * `ahead()` below is the same). The section goes D = rise + sink on the screen meanwhile; with
 * `--webx-overlap-edge-peak` (p) and `--webx-overlap-edge-rest` (r) parts of the screen:
 *   - rest = min(r × V, lead): how far ahead it stays — the section's words close under the fog;
 *   - lead = min(p × V, V / 2, rest + 0.3 × D): how far ahead it is halfway. On the way back the edge
 *            goes at most 1.5 × (lead − rest) over D / 2, so it still climbs on the screen, slower
 *            than the section, and never uncovers what it has covered.
 * The gap under the edge is filled with the section's background (`--webx-overlap-fill`, read here):
 * the section seems to start that much higher, and its words come after.
 *
 * Chrome keeps the ranges it resolved first when a size changes a frame after the animations
 * start (a second measure as the header or a picture comes): the pinned section's words then fade
 * before it is pinned. A moment's `webx-overlap-settle` after each measure makes it read them again.
 *
 * Where the browser has scroll-driven animations the stylesheet does both on this section's view
 * timeline, named per pair and lifted to the parent of the pinned section (`timeline-scope`):
 * the pinned section is not inside this one. Otherwise an IntersectionObserver and one frame per
 * scroll while either is on screen write how far along both are — a read, then writes. The pin
 * itself is `position: sticky` on both paths, so it does not shake with the scroll.
 *
 * Nothing is pinned without JavaScript, under reduced motion or in print: the sections stand one
 * after another and the edge picture lies over the bottom of the one above.
 */

import { listeners } from '../core.js'

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

const scrollDriven = () =>
  (window.CSS?.supports?.('view-timeline: --webx-overlap block') &&
    window.CSS?.supports?.('timeline-scope: --webx-overlap')) ??
  false

// What the page shows: a closed dialog, a hidden notice, a <style> of a banner are not sections.
const shown = (el) => {
  if (['STYLE', 'SCRIPT', 'TEMPLATE', 'LINK', 'META', 'NOSCRIPT'].includes(el.tagName)) return false
  if (el.hidden) return false
  const style = getComputedStyle(el)
  return style.display !== 'none' && style.position !== 'absolute' && style.position !== 'fixed'
}

/** The section this one slides over: the shown element before it, or before its parent. */
function pinnedOf(el) {
  for (let node = el; node && node !== document.body; node = node.parentElement) {
    for (let before = node.previousElementSibling; before; before = before.previousElementSibling) {
      if (shown(before) && before.offsetHeight > 0) return before
    }
  }
  return null
}

let count = 0
// The timelines lifted to an element: two pairs may share the parent.
const scopes = new WeakMap()

// How many sections stand over the page in a parent: its clip goes with the last of them.
const clipped = new WeakMap()

const edgesOf = (el) => Array.from(el.querySelectorAll(':scope > .webx-overlap__edge'))

// A part of the screen's height from the stylesheet (`--webx-overlap-peek`).
// How far ahead the edge is at u of the way, a part of `lead` (the stylesheet's keyframes, smooth).
const ahead = (u, share) => {
  if (u <= 0.5) return 4 * u * (1 - u)
  const t = (u - 0.5) / 0.5
  const s = t * t * (3 - 2 * t)
  return 1 - s + share * s
}

const part = (el, name, fallback) => {
  const value = parseFloat(getComputedStyle(el).getPropertyValue(name))
  return Number.isFinite(value) && value >= 0 ? value : fallback
}

/** The section over the one above, its edge as wide as the page: the part that never moves. */
function stand(el) {
  const parent = el.parentElement
  const edges = edgesOf(el)
  el.classList.add('webx-overlap')
  edges.forEach((edge) => edge.classList.add('webx-overlap__edge--page'))
  if (parent) {
    clipped.set(parent, (clipped.get(parent) ?? 0) + 1)
    parent.classList.add('webx-overlap-scope')
  }
  return () => {
    el.classList.remove('webx-overlap')
    edges.forEach((edge) => edge.classList.remove('webx-overlap__edge--page'))
    if (!parent) return
    const left = clipped.get(parent) - 1
    clipped.set(parent, left)
    if (!left) parent.classList.remove('webx-overlap-scope')
  }
}

export function overlap(el) {
  const sit = stand(el)
  const pinned = reduced() ? null : pinnedOf(el)
  if (!pinned) return sit

  const driven = scrollDriven()
  if (
    !driven &&
    (typeof IntersectionObserver !== 'function' || typeof requestAnimationFrame !== 'function')
  )
    return sit

  const on = listeners()
  const fading = Array.from(pinned.querySelectorAll('[data-webx-overlap-fade]'))
  const edges = edgesOf(el)
  const name = `--webx-overlap-${++count}`
  const scope = pinned.parentElement
  let sizes = { start: 0, hold: 1, leave: 1, sink: 0, rise: 1, lead: 0, rest: 0, share: 0 }
  // Where this section is now, below its place: the script's path writes it, the focus reads it.
  let sunk = 0

  // Measures, then writes: no height here changes with a `translate` or the edge's clip.
  const measure = () => {
    const screen = document.documentElement.clientHeight || window.innerHeight
    const high = pinned.offsetHeight
    const own = el.offsetHeight
    const start = Math.max(0, screen - high)
    const hold = Math.max(1, Math.min(own, screen, high))
    const edge = Math.max(0, ...edges.map((picture) => picture.offsetHeight))
    const peek = Math.min(edge, screen * part(el, '--webx-overlap-peek', 0.18))
    const rise = Math.min(hold, own - start)
    const sink = rise >= 1 ? Math.round(Math.max(0, edge - peek)) : 0
    const travel = rise + sink
    const lead =
      rise >= 1
        ? Math.round(
            Math.min(
              screen * part(el, '--webx-overlap-edge-peak', 0.33),
              screen / 2,
              screen * part(el, '--webx-overlap-edge-rest', 0.03) + 0.3 * travel,
            ),
          )
        : 0
    const rest = Math.round(Math.min(lead, screen * part(el, '--webx-overlap-edge-rest', 0.03)))
    sizes = {
      start,
      hold,
      leave: Math.max(1, Math.min(high, screen)),
      sink,
      rise: Math.max(1, rise),
      lead,
      rest,
      share: lead > 0 ? rest / lead : 0,
    }
    pinned.style.setProperty('--webx-overlap-top', `${Math.min(0, screen - high)}px`)
    pinned.style.setProperty('--webx-overlap-start', `${sizes.start}px`)
    pinned.style.setProperty('--webx-overlap-hold', `${sizes.hold}px`)
    pinned.style.setProperty('--webx-overlap-leave', `${sizes.leave}px`)
    el.style.setProperty('--webx-overlap-start', `${sizes.start}px`)
    el.style.setProperty('--webx-overlap-sink', `${sizes.sink}px`)
    el.style.setProperty('--webx-overlap-rise', `${sizes.rise}px`)
    el.style.setProperty('--webx-overlap-lead', `${sizes.lead}px`)
    el.style.setProperty('--webx-overlap-rest', `${sizes.rest}px`)
    el.style.setProperty('--webx-overlap-rest-share', sizes.share.toFixed(4))
    if (!settling && typeof requestAnimationFrame === 'function')
      settling = requestAnimationFrame(settle)
  }

  // The ranges read again (see above), on the frame after the sizes: a class for a moment, a read
  // of the style in between.
  let settling = 0
  const settle = () => {
    settling = 0
    if (!pinned.classList.contains('webx-overlap-pinned')) return
    const moving = [pinned, el, ...fading, ...edges]
    moving.forEach((target) => target.classList.add('webx-overlap-settle'))
    void getComputedStyle(el).animationRangeStart
    moving.forEach((target) => target.classList.remove('webx-overlap-settle'))
  }

  measure()
  pinned.style.setProperty('--webx-overlap-timeline', name)
  el.style.setProperty('--webx-overlap-timeline', name)
  pinned.classList.add('webx-overlap-pinned', 'webx-overlap-pinned--held')
  el.classList.add('webx-overlap--rising')
  edges.forEach((picture) =>
    picture.classList.add('webx-overlap__edge--whole', 'webx-overlap__edge--ahead'),
  )
  // The section's own colour under the edge that runs ahead; a section with none takes the token.
  const colour = getComputedStyle(el).backgroundColor
  if (colour && !/^(transparent|rgba\(.*,\s*0\))$/.test(colour))
    el.style.setProperty('--webx-overlap-fill', colour)
  fading.forEach((target) => target.classList.add('webx-overlap-fade'))

  // This section's top in its place: without the `translate` it comes up with.
  const placeTop = () => {
    const now = parseFloat(getComputedStyle(el).translate.split(' ')[1])
    return el.getBoundingClientRect().top - (Number.isFinite(now) ? now : sunk)
  }

  // Focus that comes into the section above — Shift+Tab back up, a link to a button in it — would
  // land on words faded out under this section. The page goes back to where they were whole:
  // where this section's top is still at the point the pin starts.
  on(pinned, 'focusin', () => {
    const screen = document.documentElement.clientHeight || window.innerHeight
    const travelled = screen - placeTop()
    if (travelled <= sizes.start) return
    const top = window.scrollY - (travelled - sizes.start)
    // The smooth scroll's own jump, when it glides the page: it would otherwise glide back.
    if (typeof window.webx?.scrollTo === 'function') window.webx.scrollTo(top, { immediate: true })
    else window.scrollTo({ top, behavior: 'instant' })
  })

  const resizing = typeof ResizeObserver === 'function' ? new ResizeObserver(() => measure()) : null
  resizing?.observe(pinned)
  resizing?.observe(el)
  // The edge's file comes after the script, when the page did not give its size.
  edges.forEach((picture) => resizing?.observe(picture))

  let observer = null
  let asked = 0

  if (driven) {
    el.style.setProperty('view-timeline', `${name} block`)
    const names = scopes.get(scope) ?? []
    names.push(name)
    scopes.set(scope, names)
    scope.style.setProperty('timeline-scope', names.join(', '))
    on(window, 'resize', measure)
  } else {
    pinned.classList.add('webx-overlap-pinned--script')
    el.classList.add('webx-overlap--rising-script')
    edges.forEach((picture) => picture.classList.add('webx-overlap__edge--ahead-script'))
    fading.forEach((target) => target.classList.add('webx-overlap-fade--script'))
    let seen = false

    // How far this section has come up from the bottom of the screen, then where both are.
    const place = () => {
      asked = 0
      const screen = document.documentElement.clientHeight || window.innerHeight
      const travelled = screen - (el.getBoundingClientRect().top - sunk)
      const fade = Math.min(1, Math.max(0, (travelled - sizes.start) / sizes.hold))
      const lift = Math.min(sizes.leave, Math.max(0, travelled - sizes.start - sizes.hold))
      const back = Math.min(1, Math.max(0, (travelled - sizes.start) / sizes.rise))
      sunk = sizes.sink * (1 - back)
      el.style.setProperty('--webx-overlap-ahead', ahead(back, sizes.share).toFixed(4))
      pinned.style.setProperty('--webx-overlap-progress', fade.toFixed(4))
      pinned.style.setProperty('--webx-overlap-lift', `${lift.toFixed(1)}px`)
      el.style.setProperty('--webx-overlap-sunk', `${sunk.toFixed(1)}px`)
    }

    const ask = () => {
      if (seen && !asked) asked = requestAnimationFrame(place)
    }

    const near = new Set()
    observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) near.add(entry.target)
        else near.delete(entry.target)
      }
      // One last frame as both leave the screen: a fast scroll ends where it ends.
      seen = true
      ask()
      seen = near.size > 0
    })
    observer.observe(pinned)
    observer.observe(el)
    // Now, not on the observer's first answer: the edge is whole from this frame on.
    place()
    on(window, 'scroll', ask, { passive: true })
    on(window, 'resize', () => {
      measure()
      seen = true
      ask()
      seen = near.size > 0
    })
  }

  return () => {
    observer?.disconnect()
    resizing?.disconnect()
    on.off()
    if (asked) cancelAnimationFrame(asked)
    if (settling) cancelAnimationFrame(settling)
    sit()
    el.style.removeProperty('view-timeline')
    el.classList.remove('webx-overlap--rising', 'webx-overlap--rising-script')
    edges.forEach((picture) =>
      picture.classList.remove(
        'webx-overlap__edge--whole',
        'webx-overlap__edge--ahead',
        'webx-overlap__edge--ahead-script',
      ),
    )
    for (const property of [
      'timeline',
      'start',
      'sink',
      'rise',
      'sunk',
      'lead',
      'rest',
      'rest-share',
      'ahead',
      'fill',
    ])
      el.style.removeProperty(`--webx-overlap-${property}`)
    fading.forEach((target) =>
      target.classList.remove('webx-overlap-fade', 'webx-overlap-fade--script'),
    )
    pinned.classList.remove(
      'webx-overlap-pinned',
      'webx-overlap-pinned--held',
      'webx-overlap-pinned--script',
    )
    for (const property of ['timeline', 'top', 'start', 'hold', 'leave', 'progress', 'lift'])
      pinned.style.removeProperty(`--webx-overlap-${property}`)
    const names = (scopes.get(scope) ?? []).filter((other) => other !== name)
    scopes.set(scope, names)
    if (names.length) scope.style.setProperty('timeline-scope', names.join(', '))
    else scope.style.removeProperty('timeline-scope')
  }
}
