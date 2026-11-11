/*
 * `data-webx-parallax` (spec §14): a picture that fills its frame — an <img>, or a wrapper of the
 * picture and its video — moves slower than the page while the frame crosses the screen. It is
 * drawn a little larger than the frame and shifted with `translate`, never laid out again, so the
 * page around it does not move (no CLS) and the frame's own `overflow` crops the overscan.
 *
 * Where the browser has scroll-driven animations the stylesheet does the motion on the compositor,
 * on a view timeline. Not `view()` of the picture: a timeline follows the nearest scroll container,
 * and the frame that crops the picture is one (`overflow: hidden` makes it so) that never scrolls —
 * the timeline would stand at its middle. So the timeline is named on the outermost ancestor that
 * crops (a slider's viewport, the frame itself), whose own scroller is the page, and only when
 * that ancestor spans the frame's height: a taller one would move the picture at the wrong time.
 * Otherwise, and where the browser has no such timelines, this script is the fallback: an
 * IntersectionObserver says when the frame is on screen, and only then each scroll asks for one
 * frame that reads where the frame stands and writes one custom property — a read, then a write,
 * never a read after a write. The frame is the element's parent: the element's own box carries the
 * shift, and measuring it would measure the shift.
 *
 * The value of the attribute is the strength (`soft`, `medium` or empty, `strong`): the stylesheet
 * reads it, this script does not.
 *
 * Nothing moves without JavaScript, under reduced motion or in print: the picture then stands in
 * its frame at its own size, cropped by nothing but what cropped it before.
 */

import { listeners } from '../core.js'

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

const scrollDriven = () => window.CSS?.supports?.('view-timeline: --webx-parallax block') ?? false

// How many pictures name their timeline on an element: a slider's slides share its viewport.
const named = new WeakMap()

// A scroll container, whatever it shows: `hidden` is one, `clip` is not.
const crops = (el) => !/^(visible|clip|)$/.test(getComputedStyle(el).overflowY)

/** The element to name the timeline on, or null when none follows the frame through the page. */
function subjectOf(frame) {
  let subject = frame
  for (let el = frame.parentElement; el && el !== document.body; el = el.parentElement) {
    if (crops(el)) subject = el
  }
  const a = frame.getBoundingClientRect()
  const b = subject.getBoundingClientRect()
  return Math.abs(a.top - b.top) <= 1 && Math.abs(a.height - b.height) <= 1 ? subject : null
}

export function parallax(el) {
  if (reduced()) return

  const frame = el.parentElement ?? el
  const subject = scrollDriven() ? subjectOf(frame) : null
  // Placed absolutely in its frame, the picture is made taller for its overscan; in the flow, larger.
  const marks =
    getComputedStyle(el).position === 'absolute'
      ? ['webx-parallax', 'webx-parallax--grown']
      : ['webx-parallax']

  if (subject) {
    named.set(subject, (named.get(subject) ?? 0) + 1)
    subject.classList.add('webx-parallax-frame')
    el.classList.add(...marks)
    return () => {
      el.classList.remove(...marks)
      const left = named.get(subject) - 1
      named.set(subject, left)
      if (!left) subject.classList.remove('webx-parallax-frame')
    }
  }

  if (typeof IntersectionObserver !== 'function' || typeof requestAnimationFrame !== 'function')
    return

  const on = listeners()
  let asked = 0
  let seen = false

  el.classList.add(...marks, 'webx-parallax--script')

  // 0 — the frame's top at the bottom of the screen, 1 — its bottom at the top: `cover` of a view timeline.
  const place = () => {
    asked = 0
    const rect = frame.getBoundingClientRect()
    const height = window.innerHeight
    const progress = Math.min(1, Math.max(0, (height - rect.top) / (height + rect.height)))
    el.style.setProperty('--webx-parallax-progress', progress.toFixed(4))
  }

  const ask = () => {
    if (seen && !asked) asked = requestAnimationFrame(place)
  }

  const observer = new IntersectionObserver((entries) => {
    seen = entries[entries.length - 1].isIntersecting
    ask()
  })
  observer.observe(frame)

  on(window, 'scroll', ask, { passive: true })
  on(window, 'resize', ask)

  return () => {
    observer.disconnect()
    on.off()
    if (asked) cancelAnimationFrame(asked)
    el.classList.remove(...marks, 'webx-parallax--script')
    el.style.removeProperty('--webx-parallax-progress')
  }
}
