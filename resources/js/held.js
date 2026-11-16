/*
 * The pictures nobody saw yet (WIDGETS §14.2): the server printed a slide's past the first in
 * view, a card's photos past the first and the tabs' past the first with their addresses under
 * `data-webx-src` / `data-webx-srcset`, because a horizontal strip on the screen is fetched whole,
 * `loading="lazy"` or not. The widget gives them back when it shows the slide, the photo or the
 * panel — and the next one ahead of time, so the picture is there when it slides in.
 *
 * A `<source>` before its `<img>`: the browser picks from the `<picture>` once the `<img>` has an
 * address, so the sources are given back first — document order does it.
 */

const HELD = '[data-webx-src], [data-webx-srcset]'

/** Gives back the held addresses of `element` and of everything in it. */
export function unhold(element) {
  if (!element || typeof element.querySelectorAll !== 'function') return
  const held = element.matches?.(HELD) ? [element] : []
  held.push(...element.querySelectorAll(HELD))
  for (const node of held) {
    const { webxSrcset: srcset, webxSrc: src } = node.dataset
    if (srcset !== undefined) {
      node.setAttribute('srcset', srcset)
      delete node.dataset.webxSrcset
    }
    if (src !== undefined) {
      node.setAttribute('src', src)
      delete node.dataset.webxSrc
    }
  }
}
