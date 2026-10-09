import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'

// jsdom lays nothing out: Swiper needs a width to place slides by, and a ResizeObserver.
const observers = []
globalThis.ResizeObserver = class {
  constructor(callback) {
    this.callback = callback
    observers.push(this)
  }
  observe() {}
  disconnect() {}
}

let reduced = false

beforeAll(() => {
  Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
    configurable: true,
    get: () => 600,
  })
  Object.defineProperty(HTMLElement.prototype, 'clientHeight', {
    configurable: true,
    get: () => 300,
  })
  window.matchMedia = (query) => ({
    matches: query.includes('reduce') && reduced,
    addEventListener() {},
    removeEventListener() {},
  })
})

afterEach(() => {
  reduced = false
  observers.length = 0
  window.webx?.unmount?.()
  document.body.innerHTML = ''
})

const words = {
  slide: 'slide',
  prev: 'Previous slide',
  next: 'Next slide',
  first: 'This is the first slide',
  last: 'This is the last slide',
  go_to: 'Go to slide :index',
  position: ':index / :count',
  pause: 'Pause',
  play: 'Play',
  thumbs: 'Choose a slide',
  thumb: 'Show slide :index',
}

// What `<x-webx-slider>` prints, with the settings it resolved.
const slider = (
  config = {},
  { slides = 4, perView = 1, pause = false, arrows = true, pagination = true } = {},
) => {
  const data = {
    variant: 'cards',
    effect: 'slide',
    loop: false,
    autoplay: 0,
    continuous: false,
    thumbs: false,
    speed: 0,
    options: {},
    words,
    ...config,
  }
  const items = Array.from(
    { length: slides },
    (_, i) =>
      `<div class="webx-slider__slide" role="group"><img src="/p${i + 1}.jpg" alt=""><a href="#s${i + 1}">Slide ${i + 1}</a></div>`,
  ).join('')

  return `<section class="webx-slider" data-webx-slider='${JSON.stringify(data)}'>
    <div class="webx-slider__viewport">
      <div class="webx-slider__track" style="--webx-slider-per-view: ${perView}">${items}</div>
    </div>
    <div class="webx-slider__controls" hidden>
      ${pause ? '<button type="button" class="webx-slider__button webx-slider__pause" aria-label="Pause"></button>' : ''}
      ${arrows ? '<button type="button" class="webx-slider__button webx-slider__prev"></button>' : ''}
      ${pagination ? '<div class="webx-slider__pagination"></div>' : ''}
      ${arrows ? '<button type="button" class="webx-slider__button webx-slider__next"></button>' : ''}
    </div>
  </section>`
}

const frame = () => new Promise((resolve) => requestAnimationFrame(resolve))

async function start(html) {
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./slider.js')
  const root = document.querySelector('.webx-slider')
  return { root, swiper: root?.querySelector('.webx-slider__viewport').swiper }
}

describe('slider', () => {
  it('takes over the strip: ready, controls shown, slides named for a screen reader', async () => {
    const { root, swiper } = await start(slider())
    const track = root.querySelector('.webx-slider__track')

    expect(swiper).toBeDefined()
    expect(root.classList.contains('is-ready')).toBe(true)
    expect(track.classList.contains('is-ready')).toBe(true)
    expect(root.querySelector('.webx-slider__controls').hidden).toBe(false)

    const slides = root.querySelectorAll('.webx-slider__slide')
    expect(slides[1].getAttribute('aria-label')).toBe('2 / 4')
    expect(slides[0].classList.contains('is-active')).toBe(true)

    const bullets = root.querySelectorAll('.webx-slider__bullet')
    expect(bullets).toHaveLength(4)
    expect(bullets[0].tagName).toBe('BUTTON')
    expect(bullets[2].getAttribute('aria-label')).toBe('Go to slide 3')

    // Swiper's live region is the package's class, hidden by its stylesheet — not a stray line of text.
    expect(root.querySelector('.webx-slider__notice')).not.toBeNull()
    expect(root.querySelector('[class*="swiper-"]')).toBeNull()
  })

  it('moves by the arrows, the dots and the keyboard — only with the focus inside', async () => {
    const { root, swiper } = await start(slider())

    root.querySelector('.webx-slider__next').click()
    expect(swiper.realIndex).toBe(1)
    expect(root.querySelector('.webx-slider__prev').getAttribute('aria-label')).toBe(
      'Previous slide',
    )

    root.querySelectorAll('.webx-slider__bullet')[3].click()
    expect(swiper.realIndex).toBe(3)

    const link = root.querySelector('a')
    link.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true }))
    // Swiper goes to a slide by its number in the loop on the next frame.
    await frame()
    expect(swiper.realIndex).toBe(0)
    link.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))
    expect(swiper.realIndex).toBe(1)

    document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))
    expect(swiper.realIndex).toBe(1)
  })

  it('shows as many slides as the container query chose, and follows it when the width changes', async () => {
    const { root, swiper } = await start(slider({}, { slides: 6, perView: 2.5 }))
    const track = root.querySelector('.webx-slider__track')

    expect(swiper.params.slidesPerView).toBe(2.5)
    expect(swiper.params.slidesPerGroup).toBe(2)

    track.style.setProperty('--webx-slider-per-view', '1.2')
    observers.forEach((observer) => observer.callback([]))

    expect(swiper.params.slidesPerView).toBe(1.2)
    expect(swiper.params.slidesPerGroup).toBe(1)
  })

  it('fades one slide at a time', async () => {
    const { root, swiper } = await start(slider({ effect: 'fade' }, { perView: 3 }))

    expect(swiper.params.effect).toBe('fade')
    expect(swiper.params.slidesPerView).toBe(1)
    expect(
      root.querySelector('.webx-slider__slide').classList.contains('webx-slider__slide--fade'),
    ).toBe(true)
  })

  it('autoplays with a pause button, and stops for the pointer and the focus', async () => {
    const { root, swiper } = await start(slider({ autoplay: 3000 }, { pause: true }))
    const pause = root.querySelector('.webx-slider__pause')

    expect(swiper.autoplay.running).toBe(true)
    expect(pause.getAttribute('aria-label')).toBe('Pause')

    root.dispatchEvent(new Event('pointerenter'))
    expect(swiper.autoplay.paused).toBe(true)
    root.dispatchEvent(new Event('pointerleave'))
    expect(swiper.autoplay.paused).toBe(false)

    root.querySelector('a').dispatchEvent(new FocusEvent('focusin', { bubbles: true }))
    expect(swiper.autoplay.paused).toBe(true)
    root
      .querySelector('a')
      .dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: document.body }))
    expect(swiper.autoplay.paused).toBe(false)

    pause.click()
    expect(swiper.autoplay.running).toBe(false)
    expect(pause.getAttribute('aria-label')).toBe('Play')
    expect(pause.classList.contains('is-paused')).toBe(true)

    // Stopped by the button, it stays stopped when the pointer leaves.
    root.dispatchEvent(new Event('pointerenter'))
    root.dispatchEvent(new Event('pointerleave'))
    expect(swiper.autoplay.running).toBe(false)

    pause.click()
    expect(swiper.autoplay.running).toBe(true)
    expect(pause.getAttribute('aria-label')).toBe('Pause')
  })

  it('does not start on its own with reduced motion — the button offers to', async () => {
    reduced = true
    const { root, swiper } = await start(slider({ autoplay: 3000, speed: 600 }, { pause: true }))
    const pause = root.querySelector('.webx-slider__pause')

    expect(swiper.autoplay.running).toBe(false)
    expect(swiper.params.speed).toBe(0)
    expect(pause.getAttribute('aria-label')).toBe('Play')

    pause.click()
    expect(swiper.autoplay.running).toBe(true)
  })

  it('gives `gallery` a thumbnail per slide that picks it and says which is current', async () => {
    const html = slider({ thumbs: true }, { slides: 3, pagination: false }).replace(
      '<div class="webx-slider__slide" role="group">',
      '<div class="webx-slider__slide" role="group" data-thumb="/small-1.jpg">',
    )
    const { root, swiper } = await start(html)
    const thumbs = root.querySelectorAll('.webx-slider__thumb')

    expect(thumbs).toHaveLength(3)
    expect(thumbs[0].querySelector('img').getAttribute('src')).toBe('/small-1.jpg')
    expect(thumbs[1].querySelector('img').getAttribute('src')).toBe('/p2.jpg')
    expect(thumbs[2].getAttribute('aria-label')).toBe('Show slide 3')
    expect(thumbs[0].hasAttribute('aria-current')).toBe(true)

    root.querySelector('.webx-slider__next').click()
    expect(swiper.realIndex).toBe(1)
    expect(thumbs[1].hasAttribute('aria-current')).toBe(true)
    expect(thumbs[0].hasAttribute('aria-current')).toBe(false)
  })

  it('fills a running strip with hidden copies, and takes them away again', async () => {
    const { root } = await start(
      slider(
        { continuous: true, loop: true, speed: 4000 },
        { slides: 2, perView: 3, arrows: false, pagination: false },
      ),
    )
    const copies = root.querySelectorAll('.webx-slider__slide[aria-hidden="true"]')

    expect(copies.length).toBeGreaterThan(0)
    expect(Array.from(copies).every((copy) => copy.inert)).toBe(true)

    window.webx.unmount()
    expect(root.querySelectorAll('.webx-slider__slide')).toHaveLength(2)
  })

  it('lets go of Swiper and gives the strip back on unmount', async () => {
    const { root, swiper } = await start(slider({ thumbs: true }))

    window.webx.unmount()

    expect(swiper.destroyed).toBe(true)
    expect(root.classList.contains('is-ready')).toBe(false)
    expect(root.querySelector('.webx-slider__track').classList.contains('is-ready')).toBe(false)
    expect(root.querySelector('.webx-slider__controls').hidden).toBe(true)
    expect(root.querySelector('.webx-slider__thumbs')).toBeNull()
    expect(root.querySelector('.webx-slider__slide').getAttribute('style')).toBeNull()

    // And mounts again, the same as the first time.
    window.webx.mount()
    const again = root.querySelector('.webx-slider__viewport').swiper
    expect(again).not.toBe(swiper)
    expect(again.destroyed).toBeFalsy()
    expect(root.classList.contains('is-ready')).toBe(true)
  })
})
