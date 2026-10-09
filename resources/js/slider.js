/*
 * The slider (spec §7): Swiper, built in with only the modules the variants use — navigation,
 * pagination, a11y, autoplay, the fade effect and thumbnails. Its own file, claimed by
 * `<x-webx-slider>`, after the runtime: it registers through `webx.widget`.
 *
 * Without this script the track is a strip that scrolls sideways and snaps to a slide. Swiper gets
 * the package's own class names, not its `swiper-*` ones, and no stylesheet of its own: one flat
 * `.webx-*` class per rule (THEMES §8), which a theme repaints by writing the same class.
 *
 * Slides per view are the container's to decide, not the window's: the component writes
 * `--webx-slider-per-view` per container width into a `<style>` beside it, and the script reads
 * the value the browser chose — on start and whenever the slider's width changes. Without
 * JavaScript the same property sizes the slides of the strip. The gap comes the same way, from
 * the track's `row-gap` (a strip of one row never uses it, so it survives `is-ready`).
 *
 * Everything else is settled on the server: the variant's settings with the props over them come
 * in `data-webx-slider`, the words with them.
 */

import '../css/slider.css'
import Swiper from 'swiper'
import { A11y, Autoplay, EffectFade, Navigation, Pagination, Thumbs } from 'swiper/modules'

const webx = (window.webx ??= {})

/** What the browser chose for the track at the slider's present width. */
function measure(track) {
  const style = getComputedStyle(track)
  const perView = parseFloat(style.getPropertyValue('--webx-slider-per-view'))
  return {
    perView: perView > 0 ? perView : 1,
    gap: parseFloat(style.rowGap) || 0,
  }
}

const words = (text, swap) =>
  Object.entries(swap).reduce((line, [from, to]) => line.replace(from, to), text ?? '')

/** The thumbnails of `gallery`: a button per slide, its picture the slide's `data-thumb` or first image. */
function thumbnails(slides, text) {
  const strip = document.createElement('div')
  strip.className = 'webx-slider__thumbs'
  strip.setAttribute('role', 'group')
  if (text.thumbs) strip.setAttribute('aria-label', text.thumbs)

  const track = document.createElement('div')
  track.className = 'webx-slider__thumbs-track'
  strip.append(track)

  slides.forEach((slide, index) => {
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'webx-slider__thumb'
    button.setAttribute('aria-label', words(text.thumb, { ':index': index + 1 }))

    const picture = slide.querySelector('img')
    const src = slide.dataset.thumb || picture?.currentSrc || picture?.getAttribute('src')
    if (src) {
      const image = document.createElement('img')
      image.className = 'webx-slider__thumb-image'
      image.src = src
      image.alt = ''
      image.loading = 'lazy'
      button.append(image)
    } else {
      button.textContent = String(index + 1)
    }
    track.append(button)
  })

  return strip
}

export function slider(root) {
  const viewport = root.querySelector('.webx-slider__viewport')
  const track = viewport?.querySelector('.webx-slider__track')
  if (!track) return

  let config
  try {
    config = JSON.parse(root.dataset.webxSlider || '{}')
  } catch {
    return
  }

  const text = config.words ?? {}
  const slides = Array.from(track.children).filter((el) =>
    el.classList.contains('webx-slider__slide'),
  )
  const controls = root.querySelector('.webx-slider__controls')
  const prev = root.querySelector('.webx-slider__prev')
  const next = root.querySelector('.webx-slider__next')
  const bullets = root.querySelector('.webx-slider__pagination')
  const pause = root.querySelector('.webx-slider__pause')
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
  const fade = config.effect === 'fade'
  const autoplay = config.autoplay > 0 || config.continuous === true
  let { perView, gap } = measure(track)

  // A running strip of logos needs more slides than it shows, or the loop has nothing to bring
  // round: copies, hidden from the reader and out of the tab order.
  const copies = []
  if (config.continuous && slides.length > 0) {
    while (slides.length + copies.length < Math.ceil(perView) * 2 + 2) {
      const copy = slides[copies.length % slides.length].cloneNode(true)
      copy.setAttribute('aria-hidden', 'true')
      copy.inert = true
      // The lightbox leaves copies out of a group: a picture would come round twice.
      copy.dataset.webxCopy = ''
      copy.removeAttribute('id')
      copies.push(copy)
      track.append(copy)
    }
  }

  if (fade) slides.forEach((slide) => slide.classList.add('webx-slider__slide--fade'))

  let strip = null
  let thumbs = null
  if (config.thumbs && slides.length > 1) {
    strip = thumbnails(slides, text)
    viewport.after(strip)
    thumbs = new Swiper(strip, {
      wrapperClass: 'webx-slider__thumbs-track',
      slideClass: 'webx-slider__thumb',
      slideActiveClass: 'is-active',
      slideVisibleClass: 'is-visible',
      slideFullyVisibleClass: 'is-fully-visible',
      slideNextClass: 'is-next',
      slidePrevClass: 'is-prev',
      containerModifierClass: 'webx-slider__thumbs--',
      slidesPerView: 'auto',
      spaceBetween: measure(strip.firstElementChild).gap,
      watchSlidesProgress: true,
      speed: reduced ? 0 : config.speed,
    })
  }

  const count = slides.length + copies.length
  const loop = config.loop === true && count > Math.ceil(perView) + 1
  const group = () => (fade ? 1 : Math.max(1, Math.floor(perView)))

  const swiper = new Swiper(viewport, {
    modules: [A11y, Autoplay, EffectFade, Navigation, Pagination, Thumbs],
    wrapperClass: 'webx-slider__track',
    slideClass: 'webx-slider__slide',
    slideActiveClass: 'is-active',
    slideVisibleClass: 'is-visible',
    slideFullyVisibleClass: 'is-fully-visible',
    slideNextClass: 'is-next',
    slidePrevClass: 'is-prev',
    slideBlankClass: 'is-blank',
    containerModifierClass: 'webx-slider__viewport--',
    slidesPerView: fade ? 1 : perView,
    slidesPerGroup: group(),
    spaceBetween: fade ? 0 : gap,
    speed: reduced ? 0 : config.speed,
    loop,
    loopAddBlankSlides: false,
    watchSlidesProgress: true,
    effect: fade ? 'fade' : 'slide',
    fadeEffect: { crossFade: true },
    navigation: {
      enabled: Boolean(prev && next),
      prevEl: prev,
      nextEl: next,
      disabledClass: 'is-disabled',
      hiddenClass: 'is-hidden',
      lockClass: 'is-locked',
      navigationDisabledClass: 'is-off',
    },
    pagination: {
      enabled: Boolean(bullets),
      el: bullets,
      clickable: true,
      bulletElement: 'button',
      bulletClass: 'webx-slider__bullet',
      bulletActiveClass: 'is-active',
      modifierClass: 'webx-slider__pagination--',
      clickableClass: 'is-clickable',
      lockClass: 'is-locked',
      horizontalClass: 'is-horizontal',
      paginationDisabledClass: 'is-off',
    },
    autoplay: {
      enabled: autoplay && !reduced,
      // A running strip moves without a stop: no delay, one long linear transition per slide.
      delay: config.continuous ? 0 : config.autoplay || 5000,
      disableOnInteraction: false,
      pauseOnMouseEnter: false,
    },
    thumbs: thumbs
      ? { swiper: thumbs, slideThumbActiveClass: 'is-current', thumbsContainerClass: 'is-thumbs' }
      : undefined,
    a11y: {
      enabled: true,
      // Swiper's live region, which its own stylesheet hides: ours does here.
      notificationClass: 'webx-slider__notice',
      prevSlideMessage: text.prev,
      nextSlideMessage: text.next,
      firstSlideMessage: text.first,
      lastSlideMessage: text.last,
      paginationBulletMessage: words(text.go_to, { ':index': '{{index}}' }),
      slideLabelMessage: words(text.position, {
        ':index': '{{index}}',
        ':count': '{{slidesLength}}',
      }),
      // The section carries "carousel" already; the slides are groups named "2 / 5".
      containerRoleDescriptionMessage: null,
      itemRoleDescriptionMessage: text.slide,
      slideRole: 'group',
    },
    ...config.options,
  })

  root.classList.add('is-ready')
  track.classList.add('is-ready')
  if (controls) controls.hidden = false

  // The current thumbnail says so to a screen reader, not only by its frame.
  const current = () => {
    strip?.querySelectorAll('.webx-slider__thumb').forEach((button, index) => {
      if (index === swiper.realIndex) button.setAttribute('aria-current', 'true')
      else button.removeAttribute('aria-current')
    })
  }
  swiper.on('slideChange', current)
  current()

  // Autoplay (WCAG 2.2.2): a pause button always, and a stop while the pointer or the focus is
  // inside. With reduced motion it does not start — the button offers to.
  let stopped = !autoplay || reduced
  let hovered = false
  let focused = false

  const label = () => {
    if (!pause) return
    pause.classList.toggle('is-paused', stopped)
    pause.setAttribute('aria-label', stopped ? text.play : text.pause)
  }
  const hold = () => {
    if (!autoplay || stopped) return
    if (hovered || focused) swiper.autoplay.pause()
    else swiper.autoplay.resume()
  }

  const on = []
  const listen = (target, type, listener) => {
    target.addEventListener(type, listener)
    on.push(() => target.removeEventListener(type, listener))
  }

  if (pause) {
    listen(pause, 'click', () => {
      stopped = !stopped
      if (stopped) swiper.autoplay.stop()
      else {
        swiper.autoplay.start()
        hold()
      }
      label()
    })
    label()
  }

  listen(root, 'pointerenter', () => {
    hovered = true
    hold()
  })
  listen(root, 'pointerleave', () => {
    hovered = false
    hold()
  })
  listen(root, 'focusin', () => {
    focused = true
    hold()
  })
  listen(root, 'focusout', (event) => {
    if (root.contains(event.relatedTarget)) return
    focused = false
    hold()
  })

  // Arrows move this slider only while the focus is inside it — never every slider of the page.
  listen(root, 'keydown', (event) => {
    if (event.target.closest('input, textarea, select, [contenteditable]')) return
    const move = {
      ArrowLeft: () => swiper.slidePrev(),
      ArrowRight: () => swiper.slideNext(),
      Home: () => swiper.slideToLoop(0),
      End: () => swiper.slideToLoop(slides.length - 1),
    }[event.key]
    if (!move || event.target.closest('.webx-slider__thumbs')) return
    event.preventDefault()
    move()
  })

  // Per view by the slider's own width: crossing a container breakpoint changes the property.
  const sizes = new ResizeObserver(() => {
    const now = measure(track)
    if (now.perView === perView && now.gap === gap) return
    ;({ perView, gap } = now)
    if (fade) return
    swiper.params.slidesPerView = perView
    swiper.params.slidesPerGroup = group()
    swiper.params.spaceBetween = gap
    swiper.update()
  })
  sizes.observe(root)

  return () => {
    sizes.disconnect()
    on.splice(0).forEach((remove) => remove())
    swiper.destroy(true, true)
    thumbs?.destroy(true, true)
    strip?.remove()
    copies.forEach((copy) => copy.remove())
    slides.forEach((slide) => slide.classList.remove('webx-slider__slide--fade'))
    root.classList.remove('is-ready')
    track.classList.remove('is-ready')
    if (controls) controls.hidden = true
  }
}

webx.widget?.('slider', '[data-webx-slider]', slider)
