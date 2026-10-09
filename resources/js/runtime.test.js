import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// jsdom has <dialog> but not its modal half; enough of it for the behaviour to be observable.
if (!HTMLDialogElement.prototype.showModal) {
  HTMLDialogElement.prototype.showModal = function () {
    this.setAttribute('open', '')
  }
  HTMLDialogElement.prototype.close = function () {
    if (!this.hasAttribute('open')) return
    this.removeAttribute('open')
    this.dispatchEvent(new Event('close'))
  }
}

async function start(html, before = {}) {
  document.body.innerHTML = html
  window.webx = Object.keys(before).length ? before : undefined
  vi.resetModules()
  await import('./runtime.js')
  return window.webx
}

const key = (el, name) =>
  el.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true }))

beforeEach(() => {
  document.documentElement.className = ''
  history.replaceState(null, '', '/')
})

afterEach(() => {
  document.body.innerHTML = ''
  delete window.webx
})

describe('the runtime', () => {
  it('joins a webx that is already there and starts both on webx.mount', async () => {
    const blocks = vi.fn()
    const webx = await start('<p></p>', { block() {}, mount: blocks })

    document.body.insertAdjacentHTML(
      'beforeend',
      '<div data-webx-tabs><section data-webx-tab="A"></section></div>',
    )
    webx.mount(document.body)

    expect(blocks).toHaveBeenCalledWith(document.body)
    expect(document.querySelectorAll('[role="tablist"]')).toHaveLength(1)
    expect(typeof webx.block).toBe('function')
    expect(document.documentElement.classList.contains('webx-js')).toBe(true)
  })

  it('mounts once however often it is asked, and lets go on unmount', async () => {
    const webx = await start(
      '<div id="t" data-webx-tabs><section data-webx-tab="A"></section></div>',
    )

    webx.mount()
    webx.mount(document.getElementById('t'))
    expect(document.querySelectorAll('[role="tablist"]')).toHaveLength(1)

    webx.unmount(document.body)
    expect(document.querySelectorAll('[role="tablist"]')).toHaveLength(0)

    webx.mount()
    expect(document.querySelectorAll('[role="tablist"]')).toHaveLength(1)
  })

  it('lets a widget registered later start on what is already on the page', async () => {
    const webx = await start('<div class="later"></div>')
    const setup = vi.fn()

    webx.widget('later', '.later', setup)

    expect(setup).toHaveBeenCalledWith(document.querySelector('.later'))
  })
})

describe('tabs', () => {
  const markup = `
    <div data-webx-tabs data-webx-tabs-label="Product">
      <section data-webx-tab><h3>Delivery</h3>Three days.</section>
      <section data-webx-tab="Returns">Thirty days.</section>
      <section data-webx-tab><h3>Payment</h3><span id="cards">Cards</span></section>
    </div>`

  it('turns the headings into a tab list with WAI-ARIA wiring', async () => {
    await start(markup)
    const list = document.querySelector('[role="tablist"]')
    const tabs = [...list.querySelectorAll('[role="tab"]')]
    const panels = [...document.querySelectorAll('[data-webx-tab]')]

    expect(list.getAttribute('aria-label')).toBe('Product')
    expect(tabs.map((tab) => tab.textContent)).toEqual(['Delivery', 'Returns', 'Payment'])
    expect(document.querySelector('h3').hidden).toBe(true)
    expect(tabs[0].getAttribute('aria-selected')).toBe('true')
    expect(tabs[1].tabIndex).toBe(-1)
    expect(panels[0].getAttribute('aria-labelledby')).toBe(tabs[0].id)
    expect(tabs[0].getAttribute('aria-controls')).toBe(panels[0].id)
    expect(panels.map((panel) => panel.hidden)).toEqual([false, true, true])
  })

  it('moves with the arrows, wraps around, and jumps with Home and End', async () => {
    await start(markup)
    const tabs = [...document.querySelectorAll('[role="tab"]')]

    tabs[0].focus()
    key(tabs[0], 'ArrowLeft')
    expect(document.activeElement).toBe(tabs[2])
    expect(tabs[2].getAttribute('aria-selected')).toBe('true')

    key(tabs[2], 'ArrowRight')
    expect(document.activeElement).toBe(tabs[0])

    key(tabs[0], 'End')
    expect(document.activeElement).toBe(tabs[2])
    key(tabs[2], 'Home')
    expect(document.activeElement).toBe(tabs[0])

    tabs[1].click()
    expect(document.querySelectorAll('[data-webx-tab]')[1].hidden).toBe(false)
  })

  it('opens on the panel the address points into', async () => {
    history.replaceState(null, '', '/#cards')
    await start(markup)

    expect(document.querySelectorAll('[role="tab"]')[2].getAttribute('aria-selected')).toBe('true')
  })

  it('gives the page back as it was on unmount', async () => {
    const webx = await start(markup)

    webx.unmount()

    expect(document.querySelector('[role="tablist"]')).toBeNull()
    expect(document.querySelector('h3').hidden).toBe(false)
    expect([...document.querySelectorAll('[data-webx-tab]')].every((panel) => !panel.hidden)).toBe(
      true,
    )
  })
})

describe('disclosure', () => {
  const markup = `
    <button data-webx-disclosure="menu">Menu</button>
    <ul id="menu"><li><a href="#">One</a></li></ul>
    <p id="outside">Outside</p>`

  it('hides the panel it controls and toggles it', async () => {
    await start(markup)
    const button = document.querySelector('button')
    const panel = document.getElementById('menu')

    expect(panel.hidden).toBe(true)
    expect(button.getAttribute('aria-expanded')).toBe('false')
    expect(button.getAttribute('aria-controls')).toBe('menu')

    button.click()
    expect(panel.hidden).toBe(false)
    expect(button.getAttribute('aria-expanded')).toBe('true')
  })

  it('closes on Esc, giving focus back, and on a click outside', async () => {
    await start(markup)
    const button = document.querySelector('button')
    const panel = document.getElementById('menu')

    button.click()
    panel.querySelector('a').focus()
    key(document.activeElement, 'Escape')
    expect(panel.hidden).toBe(true)
    expect(document.activeElement).toBe(button)

    button.click()
    document.getElementById('outside').click()
    expect(panel.hidden).toBe(true)
  })
})

describe('dialog', () => {
  const markup = `
    <a href="#callback" data-webx-dialog="callback">Call me back</a>
    <dialog id="callback" class="webx-dialog"><a href="#" data-webx-dialog-close>Close</a></dialog>`

  it('opens as a modal, locks the page and gives focus back to its opener', async () => {
    await start(markup)
    const opener = document.querySelector('[data-webx-dialog]')
    const dialog = document.getElementById('callback')

    opener.focus()
    opener.click()
    expect(dialog.open).toBe(true)
    expect(document.documentElement.classList.contains('webx-scroll-locked')).toBe(true)

    dialog.querySelector('[data-webx-dialog-close]').click()
    await Promise.resolve() // the attribute is observed, and observers report in a microtask
    expect(dialog.open).toBe(false)
    expect(document.documentElement.classList.contains('webx-scroll-locked')).toBe(false)
    expect(document.activeElement).toBe(opener)
  })

  it('opens from a link to it in the address', async () => {
    history.replaceState(null, '', '/#callback')
    await start(markup)

    expect(document.getElementById('callback').open).toBe(true)
  })
})

describe('accordion', () => {
  it('keeps one open when asked to', async () => {
    await start(`
      <div data-webx-accordion="single">
        <details open><summary>A</summary>a</details>
        <details><summary>B</summary>b</details>
      </div>`)
    const [first, second] = document.querySelectorAll('details')

    second.open = true
    second.dispatchEvent(new Event('toggle'))

    expect(first.open).toBe(false)
    expect(second.open).toBe(true)
  })
})
