import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// Lenis itself needs layout and frames jsdom does not have: what the widget asks of it is enough.
const instances = []
vi.mock('lenis', () => ({
  default: class {
    constructor(options) {
      this.options = options
      this.stopped = false
      this.calls = []
      instances.push(this)
    }
    stop() {
      this.stopped = true
    }
    start() {
      this.stopped = false
    }
    reset() {
      this.calls.push(['reset'])
    }
    scrollTo(target, options) {
      this.calls.push(['scrollTo', target, options])
    }
    destroy() {
      this.destroyed = true
    }
  },
}))

let media = {}

beforeEach(() => {
  instances.length = 0
  media = { reduce: false, fine: true }
  vi.stubGlobal('matchMedia', (query) => ({
    matches: query.includes('reduce')
      ? media.reduce
      : query.includes('pointer: fine') && media.fine,
    media: query,
    addEventListener() {},
    removeEventListener() {},
  }))
})

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  for (const name of [...document.documentElement.attributes].map((a) => a.name)) {
    if (name.startsWith('data-')) document.documentElement.removeAttribute(name)
  }
  document.documentElement.className = ''
  document.documentElement.removeAttribute('style')
  history.replaceState(null, '', '/')
  vi.unstubAllGlobals()
})

async function start(html, attributes = {}) {
  for (const [name, value] of Object.entries(attributes)) {
    document.documentElement.setAttribute(name, value)
  }
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  return import('./smooth-scroll.js')
}

const click = (el, init = {}) => {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1, ...init })
  el.dispatchEvent(event)
  return event
}

describe('smooth scrolling', () => {
  it('glides with the settings of the designs when the switch is on', async () => {
    await start('<p>Text</p>', { 'data-webx-smooth-scroll': '' })

    expect(instances).toHaveLength(1)
    const { options } = instances[0]
    expect(options.duration).toBe(1.1)
    expect(options.wheelMultiplier).toBe(0.9)
    expect(options.easing(0.5)).toBeCloseTo(1 - 0.5 ** 4)
    expect(options.syncTouch).toBe(false)
    expect(options.allowNestedScroll).toBe(true)
    expect(document.documentElement.classList.contains('webx-smooth-scroll')).toBe(true)
    expect(typeof window.webx.scrollTo).toBe('function')
  })

  it('takes the attributes first, then the tokens of the theme', async () => {
    document.documentElement.style.setProperty('--webx-smooth-scroll-duration', '800ms')
    document.documentElement.style.setProperty('--webx-smooth-scroll-easing', 'expo')
    await start('', {
      'data-webx-smooth-scroll': '',
      'data-webx-smooth-scroll-wheel': '1.2',
      'data-webx-smooth-scroll-easing': 'cubic',
    })

    const { options } = instances[0]
    expect(options.duration).toBe(0.8)
    expect(options.wheelMultiplier).toBe(1.2)
    expect(options.easing(0.5)).toBeCloseTo(1 - 0.5 ** 3)
  })

  it('falls back to the defaults on a value it cannot read', async () => {
    const { settings, seconds } = await start('', {
      'data-webx-smooth-scroll': '',
      'data-webx-smooth-scroll-duration': 'slow',
      'data-webx-smooth-scroll-easing': 'bounce',
      'data-webx-smooth-scroll-wheel': '-1',
    })

    expect(settings(document.documentElement)).toEqual({
      duration: 1.1,
      easing: 'quart',
      wheel: 0.9,
      anchorDuration: 1.4,
    })
    expect(seconds('1.4s')).toBe(1.4)
    expect(seconds('1400ms')).toBe(1.4)
    expect(seconds('2')).toBe(2)
    expect(seconds('fast')).toBeNull()
  })

  it('stays native with reduced motion and on a touch screen', async () => {
    media.reduce = true
    await start('', { 'data-webx-smooth-scroll': '' })
    expect(instances).toHaveLength(0)

    media = { reduce: false, fine: false }
    await start('', { 'data-webx-smooth-scroll': '' })
    expect(instances).toHaveLength(0)
    expect(document.documentElement.classList.contains('webx-smooth-scroll')).toBe(false)
  })

  it('does nothing on a page without the switch', async () => {
    await start('<a href="#a">A</a><h2 id="a">A</h2>')
    expect(instances).toHaveLength(0)
  })

  it('starts once with the switch on both html and body', async () => {
    document.body.setAttribute('data-webx-smooth-scroll', '')
    await start('', { 'data-webx-smooth-scroll': '' })
    expect(instances).toHaveLength(1)
    document.body.removeAttribute('data-webx-smooth-scroll')
  })

  it('pauses under a modal of the runtime and resumes after it', async () => {
    await start(
      '<button data-webx-dialog="d">Open</button><dialog id="d"><button data-webx-dialog-close>x</button></dialog>',
      { 'data-webx-smooth-scroll': '' },
    )
    const dialog = document.getElementById('d')
    dialog.showModal = function () {
      this.setAttribute('open', '')
    }
    dialog.close = function () {
      this.removeAttribute('open')
    }

    click(document.querySelector('[data-webx-dialog]'))
    await Promise.resolve()
    expect(document.documentElement.classList.contains('webx-scroll-locked')).toBe(true)
    expect(instances[0].stopped).toBe(true)

    dialog.close()
    await new Promise((resolve) => setTimeout(resolve))
    expect(document.documentElement.classList.contains('webx-scroll-locked')).toBe(false)
    expect(instances[0].stopped).toBe(false)
  })

  it('leaves to themselves what scrolls inside the page', async () => {
    const { NATIVE } = await start(
      '<div data-webx-scroll-native></div><dialog></dialog><div class="webx-dropdown__panel"></div><p></p>',
      { 'data-webx-smooth-scroll': '' },
    )
    const prevent = instances[0].options.prevent
    const [own, dialog, panel, text] = document.body.children

    expect(prevent(own)).toBe(true)
    expect(prevent(dialog)).toBe(true)
    expect(prevent(panel)).toBe(true)
    expect(prevent(text)).toBe(false)
    expect(NATIVE).toContain('.pswp')
  })

  it('glides to an anchor of the page and puts it into the address', async () => {
    await start(
      '<a href="#prices">Prices</a><a href="/#team">Team</a><a href="#top">Up</a><h2 id="prices">Prices</h2><h2 id="team">Team</h2>',
      { 'data-webx-smooth-scroll': '' },
    )
    const [prices, team, up] = document.querySelectorAll('a')

    expect(click(prices).defaultPrevented).toBe(true)
    expect(location.hash).toBe('#prices')
    const [name, target, options] = instances[0].calls.at(-1)
    expect(name).toBe('scrollTo')
    expect(target).toBe(document.getElementById('prices'))
    expect(options.duration).toBe(1.4)
    expect(options.easing(0.25)).toBeCloseTo(4 * 0.25 ** 3)

    expect(click(team).defaultPrevented).toBe(true)
    expect(location.hash).toBe('#team')
    expect(instances[0].calls.at(-1)[1]).toBe(document.getElementById('team'))

    click(up)
    expect(instances[0].calls.at(-1)[1]).toBe(0)

    window.dispatchEvent(new PopStateEvent('popstate'))
    expect(instances[0].calls.at(-1)).toEqual(['reset'])
  })

  it('leaves the browser its own way with other links and with the keyboard', async () => {
    await start(
      '<a href="#a" id="key">Key</a><a href="#a" target="_blank">New</a><a href="/other#a">Other</a>' +
        '<a href="#d" data-webx-lightbox>Picture</a><a href="#d">Dialog</a><a href="#missing">Missing</a><h2 id="a">A</h2><dialog id="d"></dialog>',
      { 'data-webx-smooth-scroll': '' },
    )
    const links = [...document.querySelectorAll('a')]
    // Not followed in jsdom: only whether the widget took it is of interest.
    document.addEventListener('click', (event) => event.preventDefault())

    expect(click(links[0], { detail: 0 }).defaultPrevented).toBe(true)
    expect(instances[0].calls).toHaveLength(0)
    for (const link of links.slice(1)) click(link)
    expect(instances[0].calls).toHaveLength(0)
    expect(click(links[0], { ctrlKey: true }) && instances[0].calls).toHaveLength(0)
  })

  it('lets go when unmounted', async () => {
    await start('', { 'data-webx-smooth-scroll': '' })
    window.webx.unmount()
    expect(instances[0].destroyed).toBe(true)
    expect(document.documentElement.classList.contains('webx-smooth-scroll')).toBe(false)
    expect(window.webx.scrollTo).toBeUndefined()
  })
})
