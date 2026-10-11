import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// jsdom has no layout, no observers and no media: each test says where things stand, fires the
// observers itself and plays a <video> by a flag.
let watched = []
let reduced = false
let frames = []
let now = 0
const paused = Object.getOwnPropertyDescriptor(HTMLMediaElement.prototype, 'paused')

beforeEach(() => {
  watched = []
  reduced = false
  frames = []
  now = 0
  vi.stubGlobal(
    'IntersectionObserver',
    class {
      constructor(callback) {
        this.callback = callback
        this.els = new Set()
      }
      observe(el) {
        this.els.add(el)
        watched.push(this)
      }
      disconnect() {
        this.els.clear()
      }
      show(visible = true) {
        this.callback([...this.els].map((target) => ({ target, isIntersecting: visible })))
      }
    },
  )
  vi.stubGlobal('matchMedia', (query) => ({
    matches: query.includes('reduce') && reduced,
    media: query,
    addEventListener() {},
    removeEventListener() {},
  }))
  vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))
  vi.stubGlobal('cancelAnimationFrame', () => {})
  vi.spyOn(performance, 'now').mockImplementation(() => now)
  Object.defineProperty(HTMLMediaElement.prototype, 'paused', {
    configurable: true,
    get() {
      return this.playing !== true
    },
  })
  vi.spyOn(HTMLMediaElement.prototype, 'play').mockImplementation(function () {
    this.playing = true
    return Promise.resolve()
  })
  vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(function () {
    this.playing = false
  })
  localStorage.clear()
})

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
  vi.useRealTimers()
  Object.defineProperty(HTMLMediaElement.prototype, 'paused', paused)
  delete navigator.connection
})

/** The page, then a hook to set where things stand, then the scripts — they mount as they load. */
async function start(html, file, before = () => {}) {
  document.body.innerHTML = html
  before()
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import(`./${file}.js`)
}

const at = (el, top, height = 100) => {
  el.getBoundingClientRect = () => ({
    top,
    bottom: top + height,
    height,
    left: 0,
    right: 0,
    width: 48,
  })
}

/** Runs the animation frames queued so far, at the given time. */
const frame = (time) => {
  now = time
  const queued = frames.splice(0)
  queued.forEach((callback) => callback(time))
}

describe('background video', () => {
  const page = `
    <div class="webx-video webx-video--background" data-webx-video-background>
      <div class="webx-video__backdrop" aria-hidden="true">
        <img class="webx-video__poster" src="/still.webp" alt="">
        <video class="webx-video__background" muted loop playsinline preload="none"><source src="/clip.mp4"></video>
      </div>
      <div class="webx-video__over"><h1>Hello</h1></div>
      <button type="button" class="webx-video__pause is-paused" aria-label="Play" data-pause="Pause" data-play="Play"></button>
    </div>`

  const parts = () => ({
    video: document.querySelector('video'),
    button: document.querySelector('.webx-video__pause'),
  })

  it('plays muted once on screen, and the poster stays until the first frame', async () => {
    await start(page, 'video')
    const { video, button } = parts()

    expect(video.playing).toBeUndefined()
    watched.at(-1).show()
    expect(video.playing).toBe(true)
    expect(video.muted).toBe(true)
    expect(button.classList.contains('is-paused')).toBe(false)
    expect(button.getAttribute('aria-label')).toBe('Pause')
    expect(video.classList.contains('is-playing')).toBe(false)

    video.dispatchEvent(new Event('playing'))
    expect(video.classList.contains('is-playing')).toBe(true)

    // Off screen it rests, and goes on when it comes back.
    watched.at(-1).show(false)
    expect(video.playing).toBe(false)
    watched.at(-1).show()
    expect(video.playing).toBe(true)
  })

  it('stays on its poster under reduced motion and on a connection saving data, until the visitor plays it', async () => {
    reduced = true
    await start(page, 'video')
    const { video, button } = parts()
    watched.at(-1).show()
    expect(video.playing).toBeUndefined()
    expect(button.classList.contains('is-paused')).toBe(true)
    expect(button.getAttribute('aria-label')).toBe('Play')

    button.click()
    expect(video.playing).toBe(true)
    expect(button.getAttribute('aria-label')).toBe('Pause')
    expect(localStorage.getItem('webx-video-background')).toBeNull()

    window.webx.unmount()
    reduced = false
    navigator.connection = { saveData: true }
    await start(page, 'video')
    watched.at(-1).show()
    expect(parts().video.playing).toBeUndefined()
  })

  it('pauses on the button, remembers it for the next page, and plays again on it', async () => {
    await start(page, 'video')
    const { video, button } = parts()
    watched.at(-1).show()

    button.click()
    expect(video.playing).toBe(false)
    expect(button.classList.contains('is-paused')).toBe(true)
    expect(localStorage.getItem('webx-video-background')).toBe('paused')

    window.webx.unmount()
    await start(page, 'video')
    watched.at(-1).show()
    expect(parts().video.playing).toBeUndefined()

    parts().button.click()
    expect(parts().video.playing).toBe(true)
    expect(localStorage.getItem('webx-video-background')).toBeNull()
  })

  it('shows itself paused when the browser refuses even a muted play, and stops when unmounted', async () => {
    HTMLMediaElement.prototype.play.mockImplementationOnce(() =>
      Promise.reject(new Error('NotAllowed')),
    )
    await start(page, 'video')
    const { video, button } = parts()
    watched.at(-1).show()
    await Promise.resolve()
    await Promise.resolve()
    expect(button.classList.contains('is-paused')).toBe(true)

    button.click()
    expect(video.playing).toBe(true)
    window.webx.unmount()
    expect(video.playing).toBe(false)
  })
})

describe('counter', () => {
  const page = `<p lang="en"><span class="webx-counter" data-webx-counter='{"value":3000,"decimals":0,"duration":2000}'><span class="webx-counter__number">3,000</span><span class="webx-counter__suffix">+</span></span></p>`

  const below = () => at(document.querySelector('.webx-counter'), window.innerHeight + 200)

  it('drops to zero below the screen and counts up in view, the final number said meanwhile', async () => {
    await start(page, 'counter', below)
    const root = document.querySelector('.webx-counter')
    const number = root.querySelector('.webx-counter__number')

    expect(number.textContent).toBe('0')
    expect(number.getAttribute('aria-hidden')).toBe('true')
    expect(root.querySelector('.webx-counter__final').textContent).toBe('3,000')
    expect(root.classList.contains('is-counting')).toBe(true)

    watched.at(-1).show()
    frame(0)
    frame(1000)
    // Half the time, eased out: well past half the number.
    expect(Number(number.textContent.replace(/\D/g, ''))).toBe(2625)

    frame(2000)
    expect(number.textContent).toBe('3,000')
    expect(number.hasAttribute('aria-hidden')).toBe(false)
    expect(root.querySelector('.webx-counter__final')).toBeNull()
    expect(root.classList.contains('is-counting')).toBe(false)
  })

  it('keeps its number in view, scrolled past and under reduced motion', async () => {
    await start(page, 'counter', () => at(document.querySelector('.webx-counter'), 100))
    expect(document.querySelector('.webx-counter__number').textContent).toBe('3,000')
    expect(watched).toHaveLength(0)

    window.webx.unmount()
    await start(page, 'counter', () => at(document.querySelector('.webx-counter'), -400))
    expect(document.querySelector('.webx-counter__number').textContent).toBe('3,000')

    window.webx.unmount()
    reduced = true
    await start(page, 'counter', below)
    expect(document.querySelector('.webx-counter__number').textContent).toBe('3,000')
    expect(document.querySelector('.webx-counter').classList.contains('is-counting')).toBe(false)
  })

  it('counts in its decimals, and puts the number back when printed or unmounted', async () => {
    await start(
      `<span class="webx-counter" data-webx-counter='{"value":4.9,"decimals":1,"duration":1000}'><span class="webx-counter__number">4.9</span></span>`,
      'counter',
      below,
    )
    const number = document.querySelector('.webx-counter__number')
    expect(number.textContent).toBe('0.0')

    watched.at(-1).show()
    frame(0)
    frame(500)
    expect(number.textContent).toBe('4.3')

    window.dispatchEvent(new Event('beforeprint'))
    expect(number.textContent).toBe('4.9')

    window.webx.unmount()
    await start(page, 'counter', below)
    expect(document.querySelector('.webx-counter__number').textContent).toBe('0')
    window.webx.unmount()
    expect(document.querySelector('.webx-counter__number').textContent).toBe('3,000')
  })
})

describe('countdown', () => {
  const START = Date.UTC(2026, 11, 30, 12, 0, 0)
  const END = START + 86400000 + 3600000 + 60000 + 5000

  const page = (
    ended = '<p class="webx-countdown__ended" hidden>The sale is over</p>',
    over = '',
  ) => `
    <section data-webx-countdown-scope>
      <div class="webx-countdown ${over}" data-webx-countdown='{"end":${END}}'>
        <p class="webx-countdown__date"><time>Ends on …</time></p>
        <div class="webx-countdown__units" aria-hidden="true">
          <span class="webx-countdown__value" data-unit="days">9</span>
          <span class="webx-countdown__value" data-unit="hours">09</span>
          <span class="webx-countdown__value" data-unit="minutes">09</span>
          <span class="webx-countdown__value" data-unit="seconds">09</span>
        </div>
        ${ended}
      </div>
    </section>`

  const digits = () =>
    Array.from(document.querySelectorAll('.webx-countdown__value'), (el) => el.textContent).join(
      ' ',
    )

  it('works the time out again at once — the page may have waited in a cache — and on every second', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] })
    vi.setSystemTime(START)
    await start(page(), 'countdown')

    expect(digits()).toBe('1 01 01 05')
    vi.advanceTimersByTime(1010)
    expect(digits()).toBe('1 01 01 04')
    vi.advanceTimersByTime(5000)
    expect(digits()).toBe('1 01 00 59')
  })

  it('says its text at the end, the units and the date gone', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] })
    vi.setSystemTime(END - 2000)
    await start(page(), 'countdown')
    expect(digits()).toBe('0 00 00 02')

    vi.advanceTimersByTime(2100)
    const root = document.querySelector('.webx-countdown')
    expect(root.classList.contains('is-over')).toBe(true)
    expect(root.querySelector('.webx-countdown__units')).toBeNull()
    expect(root.querySelector('.webx-countdown__date')).toBeNull()
    expect(root.querySelector('.webx-countdown__ended').hidden).toBe(false)
    expect(document.querySelector('section').hidden).toBe(false)
  })

  it('takes its block away at the end when it has nothing to say', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] })
    vi.setSystemTime(END + 1000)
    await start(page(''), 'countdown')

    expect(document.querySelector('section').hidden).toBe(true)
  })

  it('leaves alone one the server said is over, and stops ticking when unmounted', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] })
    vi.setSystemTime(START)
    await start(page(undefined, 'is-over'), 'countdown')
    expect(digits()).toBe('9 09 09 09')

    window.webx.unmount()
    await start(page(), 'countdown')
    expect(digits()).toBe('1 01 01 05')
    window.webx.unmount()
    vi.advanceTimersByTime(3000)
    expect(digits()).toBe('1 01 01 05')
  })
})
