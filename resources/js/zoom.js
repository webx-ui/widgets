/*
 * The zoom of a picture (spec §14). Its own file, claimed by `<x-webx-zoom>` and by any element
 * with `data-webx-zoom` — a gallery's lightbox link, a slide.
 *
 * A mouse over the picture magnifies it: inside its own frame, following the cursor (`inner`), or
 * as a lens on the picture and the magnified view in a panel beside it (`side`). The panel needs
 * room beside the frame inside its container — the nearest ancestor with a container type that
 * is not a widget's own — and where there is none, `side` is `inner`: the column decides, not the
 * window. A press hides it until the mouse moves again: a click is the lightbox's, a drag the
 * slider's.
 *
 * A finger: two pinch and pan inside the frame, one pans once it is magnified, a double tap is 2×
 * and back. One finger on a picture at its own size is the page's and the slider's, as before. A
 * single tap still opens the lightbox, a moment later: the moment a second tap would take.
 *
 * The window of `inner` is the picture's own box; `data-zoom-frame="container"` makes it the whole
 * `.webx-zoom`, for a picture that stands in a frame of another shape —
 * a portrait photo in a gallery's 4:3. The magnified copy then rests against the window's edges and
 * stands in its middle where it is smaller than the window. Fingers keep to the picture's box.
 *
 * The large picture is `data-webx-zoom` (empty: the link's address), fetched on the first hover or
 * touch only; until it comes, the picture shown stands in for it, magnified.
 */

import '../css/zoom.css'

const webx = (window.webx ??= {})

/** The time a second tap has to come in to be a double tap, in milliseconds. */
const TAP = 300

const clamp = (value, min, max) => Math.min(max, Math.max(min, value))

const set = (el, style) => {
  for (const key in style)
    el.style[key] = typeof style[key] === 'number' ? `${style[key]}px` : style[key]
}

const make = (tag, name, parent) => {
  const el = document.createElement(tag)
  el.className = name
  el.setAttribute('aria-hidden', 'true')
  return parent.appendChild(el)
}

/**
 * The room right of the frame, up to the right edge of its container — the nearest ancestor with a
 * container type, a widget's own (`webx-*`, a slider's) skipped, since it is no wider than what it
 * holds. No container at all: the page. Right whatever the language: as before and after (W5.3),
 * the panel is where a shop puts it, and the budget had no room for a second convention.
 */
export function room(root, box) {
  for (let el = root.parentElement; el && el !== document.body; el = el.parentElement) {
    const style = getComputedStyle(el)
    if (
      style.containerType &&
      style.containerType !== 'normal' &&
      !/^webx-/.test(style.containerName)
    ) {
      return el.getBoundingClientRect().right - box.right
    }
  }
  return document.documentElement.clientWidth - box.right
}

export function zoom(root) {
  const picture = root.querySelector('img')
  if (!picture) return

  const scale = clamp(Number(root.dataset.zoomScale) || 2.5, 1.25, 6)
  const most = Math.max(4, scale)
  const side = root.dataset.zoomMode === 'side'
  const badge = root.querySelector('.webx-zoom__badge')
  const classes = root.classList
  // The component prints the class; an element with only the attribute gets it here.
  const own = !classes.contains('webx-zoom')
  classes.add('webx-zoom')

  let view, large, lens, panel, shown, fetched, pointer, pressed, gesture, swiper, held, lastTap
  // The window's box, and where the picture stands in `.webx-zoom`: `frame()` measures them.
  let pending, passing, win, x0, y0
  let touchedAt = -1e9
  // The magnification of a finger and where the magnified picture stands in the frame.
  let z = 1
  let tx = 0
  let ty = 0

  const dress = (img) => {
    const style = getComputedStyle(picture)
    set(img, { objectFit: style.objectFit, objectPosition: style.objectPosition })
    img.src = shown || picture.currentSrc || picture.src
    return img
  }

  // The original once, on the first hover or touch; the picture shown stands in until it comes.
  const fetch = () => {
    if (fetched) return
    fetched = true
    const url =
      root.dataset.webxZoom ||
      (root.href ?? root.querySelector('a[href]')?.href) ||
      picture.currentSrc ||
      picture.src
    const full = new Image()
    full.onload = () => {
      shown = url
      for (const img of [large, panel?.firstChild]) if (img) img.src = url
    }
    full.src = url
  }

  /**
   * Where the picture stands now, and the view laid over it — over the picture, or over the window
   * when a mouse asks for one (`win`): the frame may have moved or grown.
   */
  const frame = (over = picture) => {
    view ??= make('span', 'webx-zoom__view', root)
    large ??= dress(make('img', 'webx-zoom__large', view))
    const box = picture.getBoundingClientRect()
    const outer = root.getBoundingClientRect()
    win = over.getBoundingClientRect()
    x0 = box.left - outer.left - root.clientLeft
    y0 = box.top - outer.top - root.clientTop
    set(view, {
      left: win.left - outer.left - root.clientLeft,
      top: win.top - outer.top - root.clientTop,
      width: win.width,
      height: win.height,
      borderRadius: getComputedStyle(over).borderRadius,
    })
    return box
  }

  /**
   * Where a copy stands in its window, `spare` the window's length less the copy's and `under` the
   * place that keeps the point under the cursor: as near to it as the window's edges let where the
   * copy is the longer, in the middle where it is the shorter.
   */
  const rest = (spare, under) => (spare > 0 ? spare / 2 : clamp(under, spare, 0))

  const place = (img, width, height, x, y) =>
    set(img, { width, height, transform: `translate(${x}px, ${y}px)` })

  // ——— A mouse ———

  const show = (on, beside) => {
    classes.toggle('is-active', on)
    classes.toggle('is-side', on && beside)
    view?.classList.toggle('is-active', on && !beside)
    lens?.classList.toggle('is-active', on && beside)
    panel?.classList.toggle('is-active', on && beside)
    badge?.classList.toggle('is-hidden', on)
  }

  const hover = (x, y) => {
    // The window of a mouse: the picture's own box, or the whole frame it stands in.
    const box = frame(root.dataset.zoomFrame === 'container' ? root : picture)
    const { width: w, height: h } = box
    const at = { x: clamp(x - box.left, 0, w), y: clamp(y - box.top, 0, h) }
    let fits = null

    if (side) {
      panel ??= make('div', 'webx-zoom__panel', document.body)
      if (!panel.firstChild) dress(make('img', 'webx-zoom__large', panel))
      lens ??= make('span', 'webx-zoom__lens', root)
      // Most of a frame's width beside it, or the panel is not worth it: inside the frame then.
      const width = Math.min(
        w,
        room(root, box) - (parseFloat(getComputedStyle(panel).marginLeft) || 0),
      )
      if (width >= w * 0.6) fits = width
    }

    if (fits) {
      // The lens covers what the panel shows: the panel's size, made smaller by the scale.
      const lw = fits / scale
      const lh = h / scale
      const left = clamp(at.x - lw / 2, 0, w - lw)
      const top = clamp(at.y - lh / 2, 0, h - lh)
      set(lens, { left: x0 + left, top: y0 + top, width: lw, height: lh })
      set(panel, {
        top: box.top,
        left: box.right,
        width: fits,
        height: h,
      })
      place(panel.firstChild, w * scale, h * scale, -left * scale, -top * scale)
    } else {
      // The point under the cursor stays under it, magnified, as far as the window's edges let.
      place(
        large,
        w * scale,
        h * scale,
        rest(win.width - w * scale, box.left - win.left - at.x * (scale - 1)),
        rest(win.height - h * scale, box.top - win.top - at.y * (scale - 1)),
      )
    }
    show(true, !!fits)
  }

  const scrolled = () => pointer && hover(pointer.x, pointer.y)

  const end = () => {
    removeEventListener('scroll', scrolled, true)
    show(false)
  }

  const over = (event) => {
    if (event.pointerType !== 'mouse' || z > 1) return
    pointer = { x: event.clientX, y: event.clientY }
    if (pressed) return
    if (!classes.contains('is-active')) {
      fetch()
      addEventListener('scroll', scrolled, { capture: true, passive: true })
    }
    hover(pointer.x, pointer.y)
  }

  const out = (event) => {
    if (event.pointerType !== 'mouse') return
    pointer = null
    end()
  }

  const released = () => (pressed = false)

  // A click is the lightbox's and a drag the slider's: the zoom steps aside until the mouse moves.
  const press = (event) => {
    if (event.pointerType !== 'mouse') return
    pressed = true
    addEventListener('pointerup', released, { once: true })
    end()
  }

  // ——— Fingers ———

  const paint = (box, easing) => {
    z = clamp(z, 1, most)
    tx = clamp(tx, box.width * (1 - z), 0)
    ty = clamp(ty, box.height * (1 - z), 0)
    large.classList.toggle('is-easing', !!easing)
    place(large, box.width * z, box.height * z, tx, ty)
    view.classList.add('is-active')
    classes.add('is-zoomed')
    badge?.classList.add('is-hidden')
  }

  // The slider under the picture holds still while it is pinched or magnified.
  const hold = () => {
    const found = root.closest('.webx-slider__viewport')?.swiper
    if (swiper || !found || found.destroyed) return
    swiper = found
    held = found.allowTouchMove
    found.allowTouchMove = false
  }

  const reset = (easing) => {
    const box = frame()
    z = 1
    tx = ty = 0
    large.classList.toggle('is-easing', !!easing)
    place(large, box.width, box.height, 0, 0)
    view.classList.remove('is-active')
    classes.remove('is-zoomed')
    badge?.classList.remove('is-hidden')
    if (swiper && !swiper.destroyed) {
      swiper.allowTouchMove = held
      swiper.slideTo(swiper.activeIndex, 0)
    }
    swiper = null
  }

  const local = (touch, box) => ({ x: touch.clientX - box.left, y: touch.clientY - box.top })

  /** Two fingers: how far apart, and the point of the picture between them, which stays between them. */
  const two = (touches, box) => {
    const [a, b] = [local(touches[0], box), local(touches[1], box)]
    return {
      mid: { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 },
      distance: Math.hypot(a.x - b.x, a.y - b.y) || 1,
    }
  }

  const pan = (touch, box, moved) => ({ start: local(touch, box), tx, ty, moved })

  const touchstart = (event) => {
    touchedAt = performance.now()
    const box = frame()

    if (event.touches[1]) {
      fetch()
      hold()
      const { mid, distance } = two(event.touches, box)
      gesture = { pinch: true, distance, z, point: { x: (mid.x - tx) / z, y: (mid.y - ty) / z } }
      lastTap = null
      clearTimeout(pending)
      if (event.cancelable) event.preventDefault()
    } else {
      gesture = pan(event.touches[0], box)
      if (z > 1) hold()
    }
  }

  const touchmove = (event) => {
    if (!gesture) return
    const box = frame()

    if (gesture.pinch) {
      if (!event.touches[1]) return
      const { mid, distance } = two(event.touches, box)
      z = clamp((gesture.z * distance) / gesture.distance, 1, most)
      tx = mid.x - gesture.point.x * z
      ty = mid.y - gesture.point.y * z
    } else {
      const at = local(event.touches[0], box)
      const dx = at.x - gesture.start.x
      const dy = at.y - gesture.start.y
      if (Math.hypot(dx, dy) > 10) gesture.moved = true
      // At its own size the finger is the page's and the slider's.
      if (z === 1) return
      tx = gesture.tx + dx
      ty = gesture.ty + dy
    }
    paint(box)
    if (event.cancelable) event.preventDefault()
  }

  const touchend = (event) => {
    touchedAt = performance.now()
    if (!gesture) return
    const box = frame()

    // One finger left of two goes on panning; two lifted together are often two events.
    if (gesture.pinch)
      gesture = event.touches[0] ? pan(event.touches[0], box, true) : { moved: true }
    if (event.touches[0]) return
    // The last finger up on a picture pinched back to its own size: it is as it was.
    if (z < 1.05 && classes.contains('is-zoomed')) {
      gesture = null
      return reset()
    }
    const tap = !gesture.moved && event.type === 'touchend'
    gesture = null
    if (!tap) return

    const at = local(event.changedTouches[0], box)
    const now = performance.now()

    if (
      lastTap &&
      now - lastTap.time < TAP &&
      Math.hypot(at.x - lastTap.x, at.y - lastTap.y) < 30
    ) {
      // A double tap: 2× around the point, or back to its own size.
      lastTap = null
      clearTimeout(pending)
      if (event.cancelable) event.preventDefault()
      if (z > 1) return reset(true)
      fetch()
      hold()
      z = 2
      tx = -at.x
      ty = -at.y
      return paint(box, true)
    }

    lastTap = { time: now, ...at }
  }

  // A tap is a click too. On a picture at its own size it is still the lightbox's, once no second
  // tap has come; on a magnified picture, nobody's.
  const click = (event) => {
    if (passing || performance.now() - touchedAt > 700) return
    event.preventDefault()
    clearTimeout(pending)
    if (z > 1) return
    const target = event.target
    pending = setTimeout(() => {
      passing = true
      target.click()
      passing = false
    }, TAP)
  }

  const listeners = [
    ['pointermove', over],
    ['pointerleave', out],
    ['pointerdown', press],
    ['touchstart', touchstart, { passive: false }],
    ['touchmove', touchmove, { passive: false }],
    ['touchend', touchend],
    ['touchcancel', touchend],
    ['click', click, true],
  ]
  for (const [type, listener, options] of listeners) root.addEventListener(type, listener, options)

  return () => {
    for (const [type, listener, options] of listeners)
      root.removeEventListener(type, listener, options)
    removeEventListener('scroll', scrolled, true)
    removeEventListener('pointerup', released)
    clearTimeout(pending)
    if (view) reset()
    view?.remove()
    lens?.remove()
    panel?.remove()
    badge?.classList.remove('is-hidden')
    classes.remove('is-active', 'is-side')
    if (own) classes.remove('webx-zoom')
  }
}

webx.widget?.('zoom', '[data-webx-zoom]', zoom)
