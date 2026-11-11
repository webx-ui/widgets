/*
 * Google Maps' "Embed a map" (spec §11.2): Google's own frame in the frame of `<x-webx-map embed>`,
 * claimed instead of map.js and map-google.js — no library, no key, a few lines that put an
 * <iframe> in. The notice, the waits for consent to `media` and for the screen are the shell's
 * (map-shell.js), as for every map: the frame is not even in the page before "Load", "Always load
 * maps" or the banner's consent, and then only when it comes near the screen.
 *
 * Google's frame says itself how to zoom (Ctrl and the wheel, two fingers): the shell's hint stays
 * away, and there is nothing to wake or put to sleep — the frame is Google's, not ours to drive.
 */

import '../css/map-embed.css'
// The query makes it this file's own copy: a chunk shared with map.js would be one more request.
import { settings, shell } from './map-shell.js?embed'

const webx = (window.webx ??= {})

/** Only Google's frame, whatever the attribute says: the server checked it, this checks again. */
const GOOGLE =
  /^https:\/\/(?:www\.google\.com\/maps\/embed\?|(?:www|maps)\.google\.com\/maps\?)[^\s"'<>]+$/

export function driver(canvas, config) {
  const frame = document.createElement('iframe')
  frame.className = 'webx-map__frame'
  frame.src = config.src
  frame.title = config.title || ''
  // Attributes, as Google's code has them: what a browser does not know as a property it still reads.
  frame.setAttribute('loading', 'lazy')
  frame.setAttribute('referrerpolicy', 'no-referrer-when-downgrade')
  frame.setAttribute('allowfullscreen', '')
  canvas.append(frame)

  return {
    ownHint: true,
    wake: () => {},
    sleep: () => {},
    // The frame follows its box by itself.
    resize: () => {},
    remove: () => frame.remove(),
  }
}

export function map(root) {
  const config = settings(root)
  // A map that does not wait for consent has its frame from the server: nothing left to do.
  if (root.querySelector('.webx-map__frame')) return
  if (config.provider !== 'embed' || typeof config.src !== 'string' || !GOOGLE.test(config.src))
    return

  return shell(root, config, driver)
}

webx.widget?.('map-embed', '[data-webx-map]', map)
