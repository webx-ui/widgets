/*
 * How a swipe decides, for everything of the package that pages sideways — the slider and the
 * photos of a card: one feel across the site. Not a widget: the scripts that page import it.
 *
 * A drag let go pages when it went further than `threshold` of the width, or faster than
 * `velocity` (a flick: short, but quick). Past the first or the last it gives way by
 * `resistance`. A touchpad pages too: its sideways wheel adds up to `wheel` pixels — one step —
 * and the gesture's momentum after that is waited out for `pause` milliseconds after its last
 * movement, so one gesture is one step. The values are the package's (`webx-widgets.swipe`),
 * handed to the scripts in their settings; the defaults here hold for a page printed without.
 */

export const SWIPE = Object.freeze({
  threshold: 0.15,
  velocity: 0.4,
  resistance: 0.3,
  wheel: 60,
  pause: 400,
})

/** The settings the server sent (JSON numbers), over the defaults; anything but a number at or above 0 is the default. */
export function swipeSettings(own) {
  const settings = { ...SWIPE }
  for (const key in SWIPE) {
    const value = own?.[key]
    if (typeof value === 'number' && value >= 0) settings[key] = value
  }
  return settings
}

/**
 * Where a drag let go pages: 1 — to the next (it went left), -1 — back, 0 — it stays.
 * `dx` in pixels, `ms` how long the drag took, `width` what is paged.
 */
export function flick(dx, ms, width, settings = SWIPE) {
  if (!dx) return 0
  const far = width > 0 && Math.abs(dx) > width * settings.threshold
  const fast = Math.abs(dx) / Math.max(1, ms) > settings.velocity
  if (!far && !fast) return 0
  return dx < 0 ? 1 : -1
}

/**
 * A touchpad's sideways scroll as steps: the listener for `wheel` (not passive), calling
 * `step(1)` or `step(-1)`. A wheel more down than sideways is the page's and is left alone.
 *
 * `can(direction)` says whether a gesture starting that way is this one's to page; one that is
 * not (the last photo of a card, scrolled on) is left untouched to whatever pages around it.
 * A gesture once taken stays taken to its end, momentum and all: one gesture moves one level.
 * The listener answers whether it took the event.
 */
export function wheelSteps(
  step,
  settings = SWIPE,
  now = () => performance.now(),
  can = () => true,
) {
  let sum = 0
  let last = -Infinity
  let quiet = -Infinity

  return (event) => {
    if (Math.abs(event.deltaX) <= Math.abs(event.deltaY)) return false
    const at = now()
    const fresh = at >= quiet && at - last > settings.pause
    if (fresh && !can(event.deltaX > 0 ? 1 : -1)) return false
    event.preventDefault()
    // The momentum of a gesture that already paged: waited out, however long it runs.
    if (at < quiet) {
      quiet = at + settings.pause
      return true
    }
    if (fresh) sum = 0
    last = at
    sum += event.deltaX
    if (Math.abs(sum) < settings.wheel) return true
    const direction = sum > 0 ? 1 : -1
    sum = 0
    quiet = at + settings.pause
    step(direction)
    return true
  }
}
