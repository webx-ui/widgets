/*
 * Widgets by name: a selector and a setup that runs once per element and may return its cleanup.
 *
 * Mounting is idempotent — `webx.mount(root)` after the panel replaced a block, or after a piece
 * of the page arrived late, starts only what is new inside `root`. Unmounting runs the cleanups,
 * so a widget holding a third-party instance lets it go with the element.
 */

const widgets = new Map()
const live = new WeakMap()

const elements = (root, selector) => {
  const found = root.querySelectorAll ? Array.from(root.querySelectorAll(selector)) : []
  if (root instanceof Element && root.matches(selector)) found.unshift(root)
  return found
}

export function define(name, selector, setup) {
  widgets.set(name, { selector, setup })
}

export function mount(root = document, only = null) {
  for (const [name, { selector, setup }] of widgets) {
    if (only && only !== name) continue
    for (const el of elements(root, selector)) {
      let started = live.get(el)
      if (!started) live.set(el, (started = new Map()))
      if (started.has(name)) continue
      started.set(name, null)
      try {
        started.set(name, setup(el) ?? null)
      } catch (error) {
        console.error(`webx widget "${name}":`, error)
      }
    }
  }
}

export function unmount(root = document) {
  for (const [name, { selector }] of widgets) {
    for (const el of elements(root, selector)) {
      const started = live.get(el)
      if (!started?.has(name)) continue
      const cleanup = started.get(name)
      started.delete(name)
      try {
        cleanup?.()
      } catch (error) {
        console.error(`webx widget "${name}":`, error)
      }
    }
  }
}

/** Listeners added through it are removed by calling what it returns. */
export function listeners() {
  const added = []
  const on = (target, type, listener, options) => {
    target.addEventListener(type, listener, options)
    added.push(() => target.removeEventListener(type, listener, options))
  }
  on.off = () => added.splice(0).forEach((remove) => remove())
  return on
}

let ids = 0

/** The element's id, given one if it had none: ARIA links elements by id. */
export function idOf(el, prefix) {
  if (!el.id) el.id = `${prefix}-${++ids}`
  return el.id
}
