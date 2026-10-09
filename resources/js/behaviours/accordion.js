/*
 * `data-webx-accordion` on a container of `<details>`: the browser already opens and closes them,
 * with the keyboard and without JavaScript. `data-webx-accordion="single"` keeps one open at a
 * time — opening one closes its siblings.
 */

import { listeners } from '../core.js'

export function accordion(root) {
  if (root.getAttribute('data-webx-accordion') !== 'single') return

  const on = listeners()

  // `toggle` does not bubble; captured on the container it still arrives from every <details>.
  on(
    root,
    'toggle',
    (event) => {
      const opened = event.target
      if (!(opened instanceof HTMLDetailsElement) || !opened.open || opened.parentElement !== root)
        return
      for (const other of root.querySelectorAll(':scope > details[open]')) {
        if (other !== opened) other.open = false
      }
    },
    true,
  )

  return on.off
}
