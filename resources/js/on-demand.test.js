import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { onDemand } from './on-demand.js'

// jsdom has no IntersectionObserver: a fake one the test drives by hand.
class FakeObserver {
  static all = []
  constructor(callback, options) {
    this.callback = callback
    this.options = options
    this.watched = new Set()
    FakeObserver.all.push(this)
  }
  observe(el) {
    this.watched.add(el)
  }
  unobserve(el) {
    this.watched.delete(el)
  }
  disconnect() {
    this.watched.clear()
  }
  /** The element comes near the window. */
  static near(el) {
    for (const observer of FakeObserver.all) {
      if (observer.watched.has(el)) observer.callback([{ isIntersecting: true, target: el }])
    }
  }
}

const list = (entries) =>
  `<script type="application/json" data-webx-on-demand>${JSON.stringify(entries)}</script>`

const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

let stopping = []

function start(html, load = vi.fn(() => Promise.resolve())) {
  document.body.innerHTML = html
  const demand = onDemand(load)
  stopping.push(demand)
  demand.scan(document)
  return { demand, load }
}

beforeEach(() => {
  FakeObserver.all = []
  globalThis.IntersectionObserver = FakeObserver
})

afterEach(() => {
  document.body.innerHTML = ''
  document.head.innerHTML = ''
  delete globalThis.IntersectionObserver
  stopping.forEach((demand) => demand.stop())
  stopping = []
})

describe('the scripts of widgets that wait until wanted', () => {
  it('fetches nothing until an element of the widget comes near, then once', async () => {
    const { load } = start(
      list({ slider: { src: ['/w/slider.js'], on: '[data-webx-slider]' } }) +
        '<div data-webx-slider id="a"></div><div data-webx-slider id="b"></div>',
    )

    expect(load).not.toHaveBeenCalled()
    expect(FakeObserver.all[0].options.rootMargin).toBe('200px 0px')

    FakeObserver.near(document.getElementById('b'))
    FakeObserver.near(document.getElementById('a'))
    await settle()

    expect(load).toHaveBeenCalledTimes(1)
    expect(load).toHaveBeenCalledWith('/w/slider.js')
  })

  it('never fetches a widget whose element is not on the page', async () => {
    const { load } = start(
      list({ lightbox: { src: ['/w/lightbox.js'], on: 'a[data-webx-lightbox]' } }),
    )
    await settle()

    expect(load).not.toHaveBeenCalled()
  })

  it('runs the files of a group one after another, fetched together', async () => {
    const order = []
    let releaseForm
    const load = vi.fn((src) => {
      order.push(`start ${src}`)
      if (src === '/w/form.js')
        return new Promise((resolve) => (releaseForm = resolve)).then(() => order.push('ran form'))
      order.push(`ran ${src}`)
      return Promise.resolve()
    })
    start(
      list({
        form: {
          src: ['/w/form.js', '/w/steps.js'],
          on: 'form[data-webx-send], form.webx-form--steps',
        },
      }) + '<form data-webx-send class="webx-form--steps"></form>',
      load,
    )

    FakeObserver.near(document.querySelector('form'))
    await settle()

    // Both over the network at once; the steps wait for the form to have run.
    expect(
      Array.from(document.head.querySelectorAll('link[rel="modulepreload"]'), (l) =>
        l.getAttribute('href'),
      ),
    ).toEqual(['/w/form.js', '/w/steps.js'])
    expect(order).toEqual(['start /w/form.js'])

    releaseForm()
    await settle()
    await settle()

    expect(order).toEqual(['start /w/form.js', 'ran form', 'start /w/steps.js', 'ran /w/steps.js'])
  })

  it('fetches on a touch before the element is near', async () => {
    const { load } = start(
      list({ slider: { src: ['/w/slider.js'], on: '[data-webx-slider]' } }) +
        '<div data-webx-slider><button type="button">Next</button></div>',
    )

    document.querySelector('button').dispatchEvent(new Event('pointerdown', { bubbles: true }))
    await settle()

    expect(load).toHaveBeenCalledWith('/w/slider.js')
  })

  it('holds a click that comes before the script and gives it again once the widget started', async () => {
    let started = false
    const opened = vi.fn()
    let release
    const load = vi.fn(() =>
      new Promise((resolve) => (release = resolve)).then(() => {
        // What the lightbox does when it starts: the link opens it.
        document.querySelector('a').addEventListener('click', (event) => {
          event.preventDefault()
          opened()
        })
        started = true
      }),
    )
    start(
      list({ lightbox: { src: ['/w/lightbox.js'], on: 'a[data-webx-lightbox]' } }) +
        '<a href="/photo.jpg" data-webx-lightbox><img alt=""></a>',
      load,
    )
    const followed = vi.fn()
    document.addEventListener('click', followed)

    const click = new MouseEvent('click', { bubbles: true, cancelable: true })
    document.querySelector('img').dispatchEvent(click)

    // Not followed to the picture's file, and nobody else saw it either.
    expect(click.defaultPrevented).toBe(true)
    expect(followed).not.toHaveBeenCalled()
    expect(opened).not.toHaveBeenCalled()

    await settle()
    release()
    await settle()
    await settle()

    expect(started).toBe(true)
    expect(opened).toHaveBeenCalledTimes(1)
    document.removeEventListener('click', followed)
  })

  it('lets a click on a field through: given again it would not open a picker', async () => {
    const { load } = start(
      list({ form: { src: ['/w/form.js'], on: 'form[data-webx-send]' } }) +
        '<form data-webx-send><input type="file"></form>',
    )

    const click = new MouseEvent('click', { bubbles: true, cancelable: true })
    document.querySelector('input').dispatchEvent(click)
    await settle()

    expect(click.defaultPrevented).toBe(false)
    expect(load).not.toHaveBeenCalled()
  })

  it('reads a list a piece of the page brought later, on mount of that piece', async () => {
    const { demand, load } = start('<main></main>')

    const piece = document.createElement('section')
    piece.innerHTML =
      list({ map: { src: ['/w/map.js'], on: '[data-webx-map]' } }) + '<div data-webx-map></div>'
    document.querySelector('main').append(piece)
    demand.scan(piece)

    FakeObserver.near(piece.querySelector('[data-webx-map]'))
    await settle()

    expect(load).toHaveBeenCalledWith('/w/map.js')
  })

  it('fetches everything at once where there is no IntersectionObserver', async () => {
    delete globalThis.IntersectionObserver
    const { load } = start(
      list({ slider: { src: ['/w/slider.js'], on: '[data-webx-slider]' } }) +
        '<div data-webx-slider></div>',
    )
    await settle()

    expect(load).toHaveBeenCalledWith('/w/slider.js')
  })

  it('keeps going when a file fails', async () => {
    const error = vi.spyOn(console, 'error').mockImplementation(() => {})
    const load = vi.fn((src) =>
      src === '/w/a.js' ? Promise.reject(new Error('gone')) : Promise.resolve(),
    )
    start(list({ form: { src: ['/w/a.js', '/w/b.js'], on: 'form' } }) + '<form></form>', load)

    FakeObserver.near(document.querySelector('form'))
    await settle()
    await settle()

    expect(load).toHaveBeenCalledWith('/w/b.js')
    expect(error).toHaveBeenCalled()
    error.mockRestore()
  })
})
