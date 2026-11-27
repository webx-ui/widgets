/*
 * The scripts of widgets that wait until they are wanted (spec §4.1).
 *
 * The server lists them in `<script type="application/json" data-webx-on-demand>` instead of
 * printing their tags: per widget (or group of widgets fetched together) its files and the
 * selector of the elements it starts on. A file is fetched when one of those elements comes
 * within 200px of the window, or when the visitor touches, focuses or clicks inside one
 * first; a page where none comes near never fetches it. The files of a group are fetched at once
 * and run one after another, in the server's order: the steps of a form start on the form's script.
 *
 * A click on a link or a button, or a submit, that comes before the script is held, and given again
 * once the widget has started: a link to a picture opens the lightbox rather than the picture's file.
 *
 * Without IntersectionObserver everything is fetched at once, as it was before.
 */

/**
 * How far beyond the window an element counts as near. Not a screen: what waits is usable without
 * its script (a strip that scrolls, a link to a picture, a form that sends as HTML), a touch
 * fetches it before the finger lifts, and a click is held until it starts — while a screen ahead
 * would fetch the widgets of a short page's second block with the first.
 */
const MARGIN = '200px 0px'

/** What the visitor does that wants the widget now, before it comes into view. */
const TOUCHES = ['pointerdown', 'touchstart', 'focusin', 'keydown']

const elements = (root, selector) => {
  const found = root.querySelectorAll ? Array.from(root.querySelectorAll(selector)) : []
  if (root instanceof Element && root.matches(selector)) found.unshift(root)
  return found
}

export function onDemand(load = (src) => import(/* @vite-ignore */ src)) {
  /** @type {Map<string, {src: string[], on: string, state: 'waiting' | 'loading' | 'done', done?: Promise<void>}>} */
  const waiting = new Map()
  const read = new WeakSet()

  const start = (name) => {
    const entry = waiting.get(name)
    if (!entry || entry.state !== 'waiting') return entry?.done ?? Promise.resolve()
    entry.state = 'loading'
    // All of a group's files over the network together; run in order.
    if (entry.src.length > 1) {
      for (const src of entry.src) {
        const link = document.createElement('link')
        link.rel = 'modulepreload'
        link.href = src
        document.head.append(link)
      }
    }
    entry.done = entry.src
      .reduce(
        (before, src) =>
          before.then(() =>
            Promise.resolve(load(src)).catch((error) =>
              console.error(`webx widget script ${src}:`, error),
            ),
          ),
        Promise.resolve(),
      )
      .then(() => {
        entry.state = 'done'
        observer?.disconnect()
        observe(document)
      })
    return entry.done
  }

  const near = (target) => {
    const found = []
    if (!(target instanceof Element)) return found
    for (const [name, entry] of waiting) {
      if (entry.state !== 'done' && target.closest(entry.on)) found.push(name)
    }
    return found
  }

  const observer =
    typeof IntersectionObserver === 'function'
      ? new IntersectionObserver(
          (entries) => {
            for (const { isIntersecting, target } of entries) {
              if (!isIntersecting) continue
              observer.unobserve(target)
              for (const [name, entry] of waiting) {
                if (entry.state === 'waiting' && target.matches(entry.on)) start(name)
              }
            }
          },
          { rootMargin: MARGIN },
        )
      : null

  function observe(root) {
    for (const [name, entry] of waiting) {
      if (entry.state !== 'waiting') continue
      if (!observer) {
        start(name)
        continue
      }
      for (const el of elements(root, entry.on)) observer.observe(el)
    }
  }

  /** The lists inside `root` not read yet: a page's, or one a piece of a page brought later. */
  function scan(root = document) {
    for (const list of elements(root, 'script[data-webx-on-demand]')) {
      if (read.has(list)) continue
      read.add(list)
      let entries = {}
      try {
        entries = JSON.parse(list.textContent || '{}')
      } catch (error) {
        console.error('webx on-demand list:', error)
      }
      for (const [name, { src, on }] of Object.entries(entries)) {
        if (waiting.has(name) || !Array.isArray(src) || typeof on !== 'string') continue
        waiting.set(name, { src, on, state: 'waiting' })
      }
    }
    observe(root)
  }

  const touched = (event) => near(event.target).forEach(start)

  // A click on a link or a button, or a submit, before its widget started: held, and given again
  // once it has — and then finds the widget started, so it goes through. Not a click on a field:
  // one given again would not open the picker of a file or a date.
  const held = (event) => {
    if (event.type === 'click' && !event.target.closest?.('a[href], button, [role="button"]'))
      return
    const names = near(event.target)
    if (names.length === 0) return
    event.preventDefault()
    event.stopImmediatePropagation()
    const target = event.target
    Promise.all(names.map(start)).then(() => {
      if (event.type === 'submit') {
        if (target instanceof HTMLFormElement && target.isConnected) target.requestSubmit()
      } else if (target instanceof Element && target.isConnected) {
        // The same click on the same element — an icon inside a button too, which has no click().
        const { button, clientX, clientY, screenX, screenY, ctrlKey, shiftKey, altKey, metaKey } =
          event
        target.dispatchEvent(
          new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
            composed: true,
            ...{ button, clientX, clientY, screenX, screenY, ctrlKey, shiftKey, altKey, metaKey },
          }),
        )
      }
    })
  }

  const listen = (on) => {
    for (const type of TOUCHES) document[on](type, touched, { capture: true, passive: true })
    document[on]('click', held, true)
    document[on]('submit', held, true)
  }
  listen('addEventListener')

  return {
    scan,
    start,
    stop() {
      listen('removeEventListener')
      observer?.disconnect()
    },
  }
}
