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
  show: 'Slide :index of :count',
  position: ':index / :count',
  pause: 'Pause',
  play: 'Play',
  thumbs: 'Choose a slide',
  thumb: 'Show slide :index',
}

// What `<x-webx-slider>` prints, with the settings it resolved.
const slider = (
  config = {},
  {
    slides = 4,
    perView = 1,
    pause = false,
    arrows = true,
    pagination = true,
    count = false,
    overlay = '',
  } = {},
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

  return `<section class="webx-slider${overlay ? ' webx-slider--overlaid' : ''}" data-webx-slider='${JSON.stringify(data)}'>
    <div class="webx-slider__viewport">
      <div class="webx-slider__track" style="--webx-slider-per-view: ${perView}">${items}</div>
    </div>
    ${overlay ? `<div class="webx-slider__overlay" hidden>${overlay}</div>` : ''}
    <div class="webx-slider__controls" hidden>
      ${pause ? '<button type="button" class="webx-slider__button webx-slider__pause" aria-label="Pause"></button>' : ''}
      ${arrows ? '<button type="button" class="webx-slider__button webx-slider__prev"></button>' : ''}
      ${pagination ? '<div class="webx-slider__pagination"></div>' : ''}
      ${count ? `<div class="webx-slider__count" aria-hidden="true"><span class="webx-slider__current">1</span><span class="webx-slider__count-divider">/</span><span class="webx-slider__total">${slides}</span></div>` : ''}
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

  it('counts "current/total" for `pagination="fraction"`, without and with a loop', async () => {
    const fraction = { pagination: false, count: true, slides: 3 }
    const { root } = await start(slider({}, fraction))
    const counter = root.querySelector('.webx-slider__count')
    const now = () => counter.querySelector('.webx-slider__current').textContent

    expect(now()).toBe('1')
    expect(counter.querySelector('.webx-slider__total').textContent).toBe('3')
    // No dots: Swiper's pagination is off, and the counter is not a second live region.
    expect(root.querySelector('.webx-slider__bullet')).toBeNull()
    expect(counter.getAttribute('aria-hidden')).toBe('true')
    expect(counter.hasAttribute('aria-live')).toBe(false)

    root.querySelector('.webx-slider__next').click()
    expect(now()).toBe('2')
    root.querySelector('.webx-slider__next').click()
    expect(now()).toBe('3')
    expect(counter.classList.contains('is-locked')).toBe(false)

    window.webx.unmount()
    document.body.innerHTML = ''

    // In a loop the number is the slide's own, round past the last back to one.
    const looped = await start(slider({ loop: true }, { ...fraction, slides: 4 }))
    const counter2 = looped.root.querySelector('.webx-slider__count')
    looped.swiper.slidePrev()
    expect(looped.swiper.realIndex).toBe(3)
    expect(counter2.querySelector('.webx-slider__current').textContent).toBe('4')
    looped.swiper.slideNext()
    expect(counter2.querySelector('.webx-slider__current').textContent).toBe('1')
  })

  it('goes round by rewinding when a loop has too few slides for Swiper to bring round', async () => {
    // A mosaic of two pages: a loop asked for, too few slides for one.
    const { root, swiper } = await start(slider({ loop: true }, { slides: 2 }))
    const prev = root.querySelector('.webx-slider__prev')
    const next = root.querySelector('.webx-slider__next')

    expect(swiper.params.loop).toBe(false)
    expect(swiper.params.rewind).toBe(true)
    // Both arrows on from the start: there is no edge to stop at.
    expect(prev.classList.contains('is-disabled')).toBe(false)
    expect(prev.hasAttribute('aria-disabled')).toBe(false)
    expect(next.classList.contains('is-disabled')).toBe(false)

    prev.click()
    expect(swiper.realIndex).toBe(1)
    expect(next.classList.contains('is-disabled')).toBe(false)
    expect(next.hasAttribute('aria-disabled')).toBe(false)
    next.click()
    expect(swiper.realIndex).toBe(0)

    // The keys go round too.
    const link = root.querySelector('a')
    link.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }))
    expect(swiper.realIndex).toBe(1)
    link.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))
    expect(swiper.realIndex).toBe(0)

    expect(root.querySelector('.webx-slider__notice').textContent).not.toMatch(/first|last/)
  })

  it('says where a rewinding arrow goes, never that this is the first or the last slide', async () => {
    // Swiper speaks only for arrows that are not buttons — a theme may draw them so; it reads the
    // old `keyCode`, which jsdom leaves at 0.
    const html = slider({ loop: true }, { slides: 2 }).replace(
      /<button type="button" class="webx-slider__button webx-slider__(prev|next)"><\/button>/g,
      '<div class="webx-slider__button webx-slider__$1"></div>',
    )
    const { root, swiper } = await start(html)
    const notice = root.querySelector('.webx-slider__notice')
    const enter = () => {
      const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true })
      Object.defineProperty(event, 'keyCode', { value: 13 })
      return event
    }

    root.querySelector('.webx-slider__prev').dispatchEvent(enter())
    expect(notice.textContent).toBe('Previous slide')
    swiper.slideTo(1, 0)
    root.querySelector('.webx-slider__next').dispatchEvent(enter())
    expect(notice.textContent).toBe('Next slide')
  })

  it('autoplays a rewinding slider from the last slide to the first, at once with reduced motion', async () => {
    const { swiper } = await start(slider({ loop: true, autoplay: 3000 }, { slides: 2 }))
    expect(swiper.autoplay.running).toBe(true)
    vi.useFakeTimers()
    try {
      swiper.autoplay.stop()
      swiper.slideTo(1, 0)
      swiper.autoplay.start()
      vi.advanceTimersByTime(3100)
      expect(swiper.realIndex).toBe(0)
      expect(swiper.autoplay.running).toBe(true)
    } finally {
      swiper.autoplay.stop()
      vi.useRealTimers()
    }

    reduced = true
    const still = await start(slider({ loop: true, speed: 600 }, { slides: 2 }))
    expect(still.swiper.params.speed).toBe(0)
    still.swiper.slideTo(1)
    still.swiper.slideNext()
    expect(still.swiper.realIndex).toBe(0)
    expect(still.swiper.animating).toBe(false)
  })

  it('keeps the loop with enough slides, the edges without a loop, and no arrows for one slide', async () => {
    const looped = await start(slider({ loop: true }, { slides: 4 }))
    expect(looped.swiper.params.loop).toBe(true)
    expect(looped.swiper.params.rewind).toBe(false)

    const edged = await start(slider({ loop: false }, { slides: 2 }))
    expect(edged.swiper.params.rewind).toBe(false)
    expect(edged.root.querySelector('.webx-slider__prev').classList.contains('is-disabled')).toBe(
      true,
    )
    edged.root.querySelector('.webx-slider__prev').click()
    expect(edged.swiper.realIndex).toBe(0)

    const single = await start(slider({ loop: true }, { slides: 1 }))
    expect(single.swiper.params.rewind).toBe(false)
    expect(single.swiper.isLocked).toBe(true)
  })

  it('counts only the slides of a running strip, not its copies', async () => {
    const { root, swiper } = await start(
      slider(
        { continuous: true, loop: true, speed: 4000 },
        { slides: 2, perView: 3, arrows: false, pagination: false, count: true },
      ),
    )
    const counter = root.querySelector('.webx-slider__count')

    expect(root.querySelectorAll('.webx-slider__slide').length).toBeGreaterThan(2)
    expect(counter.querySelector('.webx-slider__total').textContent).toBe('2')
    const real = new Set()
    for (let i = 0; i < 5; i++) {
      swiper.slideNext(0)
      real.add(swiper.realIndex)
      const shown = Number(counter.querySelector('.webx-slider__current').textContent)
      expect(shown).toBe((swiper.realIndex % 2) + 1)
      expect(shown).toBeLessThanOrEqual(2)
    }
    // It did come to a copy: Swiper numbers the copies past the two slides.
    expect(Math.max(...real)).toBeGreaterThanOrEqual(2)
  })

  it('follows the slide with the captions of the overlay, the dots in it, and tells every change', async () => {
    const captions = Array.from(
      { length: 3 },
      (_, i) =>
        `<div class="webx-slider__caption${i === 0 ? ' is-current' : ''}"${i > 0 ? ' inert aria-hidden="true"' : ''}><a href="#c${i}">Card ${i + 1}</a></div>`,
    ).join('')
    const overlay = `<div class="plate"><div class="webx-slider__captions" aria-live="polite">${captions}</div><div class="bars"></div></div>`
    const { root, swiper } = await start(
      slider(
        { loop: true, paginationIn: '.bars', pause: false, autoplay: 3000 },
        { slides: 3, arrows: false, pagination: false, overlay },
      ),
    )
    const items = root.querySelectorAll('.webx-slider__caption')
    const told = []
    root.addEventListener('webx:slide', (event) => told.push(event.detail))

    expect(root.querySelector('.webx-slider__overlay').hidden).toBe(false)
    // The dots are in the overlay, saying which slide of how many.
    const bullets = root.querySelectorAll('.bars .webx-slider__bullet')
    expect(bullets).toHaveLength(3)
    expect(root.querySelector('.bars').classList.contains('webx-slider__pagination')).toBe(true)
    expect(bullets[1].getAttribute('aria-label')).toBe('Slide 2 of 3')

    // Autoplay moves it: told as such, and not read out.
    swiper.slideNext()
    expect(told.at(-1)).toEqual({ index: 1, count: 3, auto: true })
    expect([...items].map((item) => item.classList.contains('is-current'))).toEqual([
      false,
      true,
      false,
    ])
    expect([...items].map((item) => item.inert)).toEqual([true, false, true])
    expect(items[1].hasAttribute('aria-hidden')).toBe(false)
    expect(items[0].getAttribute('aria-hidden')).toBe('true')
    expect(root.querySelector('.webx-slider__captions').getAttribute('aria-live')).toBe('off')

    // A dot in the overlay, under the pointer: by hand, read out; the hold outlives Swiper's resume.
    root.dispatchEvent(new Event('pointerenter'))
    bullets[2].click()
    expect(told.at(-1)).toEqual({ index: 2, count: 3, auto: false })
    expect(items[2].classList.contains('is-current')).toBe(true)
    expect(root.querySelector('.webx-slider__captions').getAttribute('aria-live')).toBe('polite')
    swiper.autoplay.resume()
    expect(swiper.autoplay.paused).toBe(true)

    // A loop counts the slides, not Swiper's places in it.
    swiper.slideNext()
    expect(told.at(-1).index).toBe(0)

    window.webx.unmount()
    expect(root.querySelector('.webx-slider__overlay').hidden).toBe(true)
    expect(root.querySelector('.bars').classList.contains('webx-slider__pagination')).toBe(false)
    expect([...items].map((item) => item.inert)).toEqual([false, true, true])
  })

  it('pages past 15% of a slide, and gives way at the edge by the package resistance', async () => {
    const { swiper } = await start(slider())
    expect(swiper.params.longSwipesRatio).toBe(0.15)
    expect(swiper.params.resistanceRatio).toBe(0.3)
    expect(swiper.params.shortSwipes).toBe(true)

    // The site's own, from the package's config.
    const own = await start(slider({ swipe: { threshold: 0.25, resistance: 0.5 } }))
    expect(own.swiper.params.longSwipesRatio).toBe(0.25)
    expect(own.swiper.params.resistanceRatio).toBe(0.5)
  })

  it('pages by a flick: a drag faster than 0.4 px/ms is a short swipe, a slower one goes by its length', async () => {
    const { swiper } = await start(slider())
    const release = (diff, ms) => {
      swiper.touchEventsData.touchStartTime = Date.now() - ms
      swiper.touches.diff = diff
      swiper.emit('touchEnd', new Event('pointerup'))
      return swiper.params.longSwipesMs
    }

    // 40 px in 50 ms: a flick — Swiper's short swipe, which pages whatever the length.
    expect(release(-40, 50)).toBe(Number.POSITIVE_INFINITY)
    // 40 px in a second: a long swipe, paged only past 15% of the slide.
    expect(release(-40, 1000)).toBe(0)
    expect(release(30, 20)).toBe(Number.POSITIVE_INFINITY)
  })

  it('pages by a touchpad: a sideways wheel of 60px is one step, and its momentum no more', async () => {
    let time = 1000
    vi.spyOn(performance, 'now').mockImplementation(() => time)
    const { root, swiper } = await start(slider())
    const viewport = root.querySelector('.webx-slider__viewport')
    const wheel = (deltaX, deltaY = 0) => {
      time += 16
      const event = new WheelEvent('wheel', { deltaX, deltaY, bubbles: true, cancelable: true })
      viewport.dispatchEvent(event)
      return event
    }

    wheel(30)
    expect(swiper.activeIndex).toBe(0)
    expect(wheel(35).defaultPrevented).toBe(true)
    expect(swiper.activeIndex).toBe(1)
    for (let at = 0; at < 10; at++) wheel(40)
    expect(swiper.activeIndex).toBe(1)

    time += 500
    wheel(-70)
    expect(swiper.activeIndex).toBe(0)

    // Down the page: the page scrolls, the slider stands.
    const down = wheel(10, 80)
    expect(down.defaultPrevented).toBe(false)
    expect(swiper.activeIndex).toBe(0)
    vi.restoreAllMocks()
  })

  it('leaves a touchpad gesture over the photos of a card to them, and past their last to itself', async () => {
    let time = 1000
    vi.spyOn(performance, 'now').mockImplementation(() => time)
    // A slider of cards, the first card with three photos of its own.
    document.body.innerHTML = slider()
    const photos = Array.from(
      { length: 3 },
      (_, at) =>
        `<img class="webx-photos__photo${at === 0 ? ' is-active' : ''}" src="/c${at + 1}.jpg" alt="">`,
    ).join('')
    document.querySelector('.webx-slider__slide').insertAdjacentHTML(
      'afterbegin',
      `<div class="webx-photos" data-webx-photos='${JSON.stringify({ autoplay: 0, words: { pause: 'Pause', play: 'Play' } })}'>
        <div class="webx-photos__frame">${photos}</div>
      </div>`,
    )
    delete window.webx
    vi.resetModules()
    await import('./runtime.js')
    await import('./slider.js')
    await import('./photos.js')
    const root = document.querySelector('.webx-slider')
    const swiper = root.querySelector('.webx-slider__viewport').swiper
    const card = root.querySelector('.webx-photos')
    const frame = card.querySelector('.webx-photos__frame')
    frame.getBoundingClientRect = () => ({
      top: 0,
      left: 0,
      width: 300,
      height: 200,
      bottom: 200,
      right: 300,
    })
    const shown = () =>
      Array.from(card.querySelectorAll('.webx-photos__photo')).findIndex((photo) =>
        photo.classList.contains('is-active'),
      )
    const wheel = (deltaX) => {
      time += 16
      const event = new WheelEvent('wheel', {
        deltaX,
        clientX: 100,
        clientY: 100,
        bubbles: true,
        cancelable: true,
      })
      frame.dispatchEvent(event)
      return event
    }

    // One gesture of 61px over the photos: the second photo, the slider where it was.
    wheel(61)
    expect(shown()).toBe(1)
    expect(swiper.activeIndex).toBe(0)
    // Its momentum, onto the last photo and past it: still the photos', the slider stands.
    time += 500
    wheel(61)
    for (let at = 0; at < 10; at++) wheel(40)
    expect(shown()).toBe(2)
    expect(swiper.activeIndex).toBe(0)

    // A gesture of its own past the last photo is not theirs: the slider of cards pages, once.
    time += 500
    wheel(61)
    for (let at = 0; at < 10; at++) wheel(40)
    expect(shown()).toBe(2)
    expect(swiper.activeIndex).toBe(1)
    vi.restoreAllMocks()
  })

  it('leaves the wheel of a running strip alone', async () => {
    const { root, swiper } = await start(
      slider(
        { variant: 'logos', continuous: true, loop: true },
        { arrows: false, pagination: false },
      ),
    )
    const event = new WheelEvent('wheel', { deltaX: 200, bubbles: true, cancelable: true })
    root.querySelector('.webx-slider__viewport').dispatchEvent(event)
    expect(event.defaultPrevented).toBe(false)
    expect(swiper.destroyed).toBeFalsy()
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

  it('gives a held slide its pictures as it nears the view, and not before (§14.2)', async () => {
    // What the server prints with `pictures.defer`: the slides past the first in view hold their addresses.
    const html = slider({}, { slides: 6 }).replace(
      /<img src="\/p([2-6])\.jpg" alt="">/g,
      '<picture class="webx-picture"><source type="image/avif" data-webx-srcset="/p$1-480.avif 480w"><img data-webx-src="/p$1.jpg" alt=""></picture>',
    )
    const { root } = await start(html)
    const image = (n) => root.querySelectorAll('.webx-slider__slide img')[n - 1]
    const source = (n) => root.querySelectorAll('.webx-slider__slide source')[n - 2]

    // The slide in view, the next one and the last (the one an arrow back goes to) — not the rest.
    expect(image(2).getAttribute('src')).toBe('/p2.jpg')
    expect(source(2).getAttribute('srcset')).toBe('/p2-480.avif 480w')
    expect(image(6).getAttribute('src')).toBe('/p6.jpg')
    expect(image(3).hasAttribute('src')).toBe(false)
    expect(source(3).hasAttribute('srcset')).toBe(false)
    expect(image(4).hasAttribute('src')).toBe(false)

    root.querySelector('.webx-slider__next').click()
    expect(image(3).getAttribute('src')).toBe('/p3.jpg')
    expect(source(3).getAttribute('srcset')).toBe('/p3-480.avif 480w')
    expect(image(3).hasAttribute('data-webx-src')).toBe(false)
    expect(image(4).hasAttribute('src')).toBe(false)
  })
})
