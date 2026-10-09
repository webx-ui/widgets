import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// jsdom has <dialog> but not its modal half; enough of it for the behaviours to be observable.
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

async function start(html) {
  document.body.innerHTML = html
  window.webx = undefined
  vi.resetModules()
  await import('./runtime.js')
  return window.webx
}

// The MutationObserver the behaviours watch `open` with reports after a microtask.
const settle = () => new Promise((resolve) => setTimeout(resolve, 0))
const frame = () => new Promise((resolve) => requestAnimationFrame(() => resolve()))

const key = (el, name) =>
  el.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true }))

function pointer(el, type, props = {}) {
  const event = new MouseEvent(type, {
    bubbles: type !== 'pointerenter' && type !== 'pointerleave',
    cancelable: true,
    clientX: props.x ?? 0,
    clientY: props.y ?? 0,
  })
  Object.defineProperty(event, 'pointerType', { value: props.pointerType ?? 'mouse' })
  el.dispatchEvent(event)
  return event
}

const click = (el, detail = 1) => {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true, detail })
  el.dispatchEvent(event)
  return event
}

const rect = (el, box) => {
  el.getBoundingClientRect = () => ({
    top: 0,
    bottom: 44,
    width: box.right - box.left,
    height: 44,
    x: box.left,
    y: 0,
    ...box,
  })
}

beforeEach(() => {
  document.documentElement.className = ''
  document.documentElement.removeAttribute('style')
  history.replaceState(null, '', '/')
})

afterEach(() => {
  window.webx?.unmount(document.body)
  document.body.innerHTML = ''
  delete window.webx
  vi.restoreAllMocks()
  vi.useRealTimers()
})

const menu = (attrs = '') => `
  <div class="webx-mobile-menu" data-webx-mobile-menu="m" data-side="right" ${attrs}>
    <a id="m-trigger" class="webx-mobile-menu__trigger" href="#m" data-webx-dialog="m">Menu</a>
    <dialog id="m" class="webx-mobile-menu__panel">
      <div class="webx-mobile-menu__top"><a href="#" data-webx-dialog-close id="close">×</a></div>
      <div class="webx-mobile-menu__body">
        <a id="anchor" href="#prices">Prices</a>
        <a id="away" href="/about">About</a>
        <a id="blank" href="/elsewhere" target="_blank">Elsewhere</a>
      </div>
    </dialog>
  </div>
  <h2 id="prices">Prices</h2>`

describe('mobile menu', () => {
  it('opens from its trigger as a button that says it is expanded, and adds a history entry', async () => {
    await start(menu())
    const trigger = document.getElementById('m-trigger')

    expect(trigger.getAttribute('role')).toBe('button')
    expect(trigger.getAttribute('aria-expanded')).toBe('false')

    const length = history.length
    click(trigger)
    await settle()

    expect(document.getElementById('m').open).toBe(true)
    expect(trigger.getAttribute('aria-expanded')).toBe('true')
    expect(history.length).toBe(length + 1)
    expect(history.state.webxMenu).toBe('m')
  })

  it('takes its history entry off when closed, and closes on Back', async () => {
    await start(menu())
    const back = vi.spyOn(history, 'back').mockImplementation(() => {})
    const panel = document.getElementById('m')

    click(document.getElementById('m-trigger'))
    await settle()
    click(document.getElementById('close'))
    await settle()

    expect(panel.open).toBe(false)
    expect(back).toHaveBeenCalledTimes(1)
    expect(document.getElementById('m-trigger').getAttribute('aria-expanded')).toBe('false')

    // Back while open: the browser took the entry off already, so the menu does not go back again.
    click(document.getElementById('m-trigger'))
    await settle()
    window.dispatchEvent(new PopStateEvent('popstate'))
    await settle()

    expect(panel.open).toBe(false)
    expect(back).toHaveBeenCalledTimes(1)
  })

  it('closes on a link inside, and follows a same-page anchor once its entry is gone', async () => {
    await start(menu())
    vi.spyOn(history, 'back').mockImplementation(() =>
      setTimeout(() => window.dispatchEvent(new PopStateEvent('popstate'))),
    )

    click(document.getElementById('m-trigger'))
    await settle()
    const event = click(document.getElementById('anchor'))
    await settle()
    await settle()

    expect(event.defaultPrevented).toBe(true)
    expect(document.getElementById('m').open).toBe(false)
    expect(location.hash).toBe('#prices')
  })

  it('leaves a link for a new tab alone, and history alone when told to', async () => {
    await start(menu('data-history="false"'))
    const panel = document.getElementById('m')
    const length = history.length

    click(document.getElementById('m-trigger'))
    await settle()
    expect(history.length).toBe(length)

    expect(click(document.getElementById('blank')).defaultPrevented).toBe(false)
    expect(panel.open).toBe(true)

    // No entry to take off: the link closes the menu and goes on as a link.
    // (jsdom cannot go to another document: the page stops the link after the menu saw it.)
    const stop = (e) => e.preventDefault()
    document.addEventListener('click', stop)
    click(document.getElementById('away'))
    expect(panel.open).toBe(false)
    document.removeEventListener('click', stop)
  })

  it('closes on a swipe toward its side, not on a short one or across', async () => {
    await start(menu())
    const panel = document.getElementById('m')
    click(document.getElementById('m-trigger'))
    await settle()

    pointer(panel, 'pointerdown', { x: 100, y: 300, pointerType: 'touch' })
    pointer(panel, 'pointermove', { x: 130, y: 302, pointerType: 'touch' })
    expect(panel.style.transform).toBe('translateX(30px)')
    pointer(panel, 'pointerup', { x: 130, y: 302, pointerType: 'touch' })
    expect(panel.open).toBe(true)
    expect(panel.style.transform).toBe('')

    pointer(panel, 'pointerdown', { x: 100, y: 300, pointerType: 'touch' })
    pointer(panel, 'pointermove', { x: 110, y: 400, pointerType: 'touch' })
    pointer(panel, 'pointerup', { x: 200, y: 400, pointerType: 'touch' })
    expect(panel.open).toBe(true)

    pointer(panel, 'pointerdown', { x: 100, y: 300, pointerType: 'touch' })
    pointer(panel, 'pointerup', { x: 200, y: 310, pointerType: 'touch' })
    expect(panel.open).toBe(false)
  })
})

const drill = `
  <nav class="webx-mobile-nav" data-webx-mobile-nav="drill">
    <ul class="webx-mobile-nav__list" id="top">
      <li><details class="webx-mobile-nav__group" id="services">
        <summary class="webx-mobile-nav__toggle">Services</summary>
        <div class="webx-mobile-nav__level webx-mobile-nav__level--drill">
          <button type="button" class="webx-mobile-nav__back" data-webx-mobile-nav-back>Back</button>
          <ul class="webx-mobile-nav__list" id="second"><li><a href="/services/a">A</a></li></ul>
        </div>
      </details></li>
      <li><a href="/about">About</a></li>
    </ul>
  </nav>`

describe('mobile navigation, drill', () => {
  it('lays an open branch over its parent and goes back with focus on the branch', async () => {
    await start(drill)
    const details = document.getElementById('services')
    const top = document.getElementById('top')

    details.open = true
    details.dispatchEvent(new Event('toggle'))

    expect(top.classList.contains('is-drilled')).toBe(true)
    expect(document.activeElement).toBe(details.querySelector('.webx-mobile-nav__back'))

    click(details.querySelector('.webx-mobile-nav__back'))
    details.dispatchEvent(new Event('toggle'))

    expect(details.open).toBe(false)
    expect(top.classList.contains('is-drilled')).toBe(false)
    expect(document.activeElement).toBe(details.querySelector('summary'))
  })

  it('starts drilled into a branch that is open in the markup', async () => {
    await start(drill.replace('id="services"', 'id="services" open'))
    expect(document.getElementById('top').classList.contains('is-drilled')).toBe(true)
  })
})

const nav = `
  <ul class="webx-header-nav" data-webx-header-nav>
    <li class="webx-header-nav__item" id="one">
      <a class="webx-header-nav__link" href="/one" id="one-link">One</a>
      <button type="button" class="webx-header-nav__toggle" aria-expanded="false" aria-controls="p1" data-webx-header-nav-toggle id="one-toggle"></button>
      <ul class="webx-header-nav__dropdown" id="p1">
        <li><a class="webx-header-nav__sublink" href="/one/a" id="one-a">A</a></li>
        <li><a class="webx-header-nav__sublink" href="/one/b" id="one-b">B</a></li>
      </ul>
    </li>
    <li class="webx-header-nav__item" id="two">
      <a class="webx-header-nav__link" href="/two" id="two-link">Two</a>
      <button type="button" class="webx-header-nav__toggle" aria-expanded="false" aria-controls="p2" data-webx-header-nav-toggle></button>
      <ul class="webx-header-nav__dropdown" id="p2"><li><a href="/two/a" id="two-a">A</a></li></ul>
    </li>
    <li class="webx-header-nav__item"><a class="webx-header-nav__link" href="/three" id="three-link">Three</a></li>
  </ul>
  <p id="outside">Outside</p>`

describe('header navigation', () => {
  it('opens from the keyboard into the list and closes on Esc with focus back on the toggle', async () => {
    await start(nav)
    const toggle = document.getElementById('one-toggle')

    toggle.focus()
    click(toggle, 0)

    expect(toggle.getAttribute('aria-expanded')).toBe('true')
    expect(document.getElementById('p1').classList.contains('is-open')).toBe(true)
    expect(document.activeElement.id).toBe('one-a')

    key(document.activeElement, 'ArrowDown')
    expect(document.activeElement.id).toBe('one-b')
    key(document.activeElement, 'ArrowDown')
    expect(document.activeElement.id).toBe('one-a')

    key(document.activeElement, 'Escape')
    expect(toggle.getAttribute('aria-expanded')).toBe('false')
    expect(document.activeElement).toBe(toggle)
  })

  it('opens on ↓ from the link and goes along the top level with ← →', async () => {
    await start(nav)
    const link = document.getElementById('one-link')

    link.focus()
    key(link, 'ArrowRight')
    expect(document.activeElement.id).toBe('two-link')
    key(document.activeElement, 'ArrowRight')
    expect(document.activeElement.id).toBe('three-link')
    key(document.activeElement, 'ArrowRight')
    expect(document.activeElement.id).toBe('one-link')

    key(link, 'ArrowDown')
    expect(document.getElementById('one').classList.contains('is-open')).toBe(true)
    expect(document.activeElement.id).toBe('one-a')
  })

  it('opens on the first tap and follows the link on the second', async () => {
    await start(nav)
    const link = document.getElementById('one-link')

    pointer(link, 'pointerdown', { pointerType: 'touch' })
    expect(click(link).defaultPrevented).toBe(true)
    expect(document.getElementById('one').classList.contains('is-open')).toBe(true)

    pointer(link, 'pointerdown', { pointerType: 'touch' })
    const stop = (e) => e.preventDefault()
    let followed = null
    document.addEventListener(
      'click',
      (e) => {
        followed = !e.defaultPrevented
        stop(e)
      },
      { once: true },
    )
    click(link)
    expect(followed).toBe(true)
  })

  it('opens on hover after a delay, keeps one open and closes a while after the pointer left', async () => {
    vi.useFakeTimers()
    await start(nav)
    const one = document.getElementById('one')
    const two = document.getElementById('two')

    pointer(one, 'pointerenter')
    expect(one.classList.contains('is-open')).toBe(false)
    vi.advanceTimersByTime(150)
    expect(one.classList.contains('is-open')).toBe(true)

    // Straight on to a neighbour (not toward the open panel): it switches at once.
    pointer(one, 'pointerleave')
    pointer(two, 'pointerenter')
    vi.advanceTimersByTime(0)
    expect(one.classList.contains('is-open')).toBe(false)
    expect(two.classList.contains('is-open')).toBe(true)

    pointer(two, 'pointerleave')
    vi.advanceTimersByTime(200)
    expect(two.classList.contains('is-open')).toBe(true)
    vi.advanceTimersByTime(200)
    expect(two.classList.contains('is-open')).toBe(false)
  })

  it('does not switch while the pointer heads for the open panel across a neighbour', async () => {
    vi.useFakeTimers()
    await start(nav)
    const one = document.getElementById('one')
    const two = document.getElementById('two')
    rect(document.getElementById('p1'), { left: 0, right: 300, top: 44, bottom: 200 })

    pointer(one, 'pointerenter')
    vi.advanceTimersByTime(150)
    pointer(document, 'pointermove', { x: 40, y: 10 })
    pointer(document, 'pointermove', { x: 120, y: 40 })
    pointer(one, 'pointerleave')
    pointer(two, 'pointerenter')
    vi.advanceTimersByTime(100)

    expect(one.classList.contains('is-open')).toBe(true)
    expect(two.classList.contains('is-open')).toBe(false)
  })

  it('holds a dropdown against the right edge, and closes on a click outside', async () => {
    await start(nav)
    rect(document.getElementById('p1'), { left: 900, right: 5000 })

    click(document.getElementById('one-toggle'))
    expect(document.getElementById('p1').classList.contains('is-flipped')).toBe(true)

    click(document.getElementById('outside'))
    expect(document.getElementById('one').classList.contains('is-open')).toBe(false)
    expect(document.getElementById('p1').classList.contains('is-flipped')).toBe(false)
  })
})

const header = (attrs) => `
  <header class="webx-header" data-webx-header ${attrs} id="h">
    <div class="webx-header__topbar">Phone</div>
    <div class="webx-header__bar">
      <div class="webx-header__brand">Brand</div>
      <nav class="webx-header__nav"><ul class="webx-header-nav"><li id="i1">One</li><li id="i2">Two</li></ul></nav>
      <div class="webx-header__actions">Call</div>
      <a class="webx-mobile-menu__trigger webx-header__trigger" id="t">Menu</a>
      <dialog id="d"></dialog>
    </div>
  </header>`

const folded = () =>
  ['h', 't'].map((id) => document.getElementById(id).classList.contains('is-collapsed'))

describe('header', () => {
  it('folds at a width in pixels and never with "never"', async () => {
    vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(800)
    await start(header('data-collapse="960"'))
    expect(folded()).toEqual([true, true])
    expect(document.querySelector('.webx-header__nav').classList.contains('is-collapsed')).toBe(
      true,
    )
    expect(document.querySelector('.webx-header__actions').classList.contains('is-collapsed')).toBe(
      true,
    )

    window.webx.unmount(document.body)
    document.body.innerHTML = header('data-collapse="never"')
    window.webx.mount(document.body)
    expect(folded()).toEqual([false, false])
  })

  it('folds "auto" exactly when an item no longer fits in the navigation', async () => {
    document.body.innerHTML = header('data-collapse="auto"')
    rect(document.querySelector('.webx-header__nav'), { left: 100, right: 500 })
    rect(document.getElementById('i1'), { left: 300, right: 400 })
    rect(document.getElementById('i2'), { left: 400, right: 500 })
    vi.resetModules()
    window.webx = undefined
    await import('./runtime.js')
    expect(folded()).toEqual([false, false])

    rect(document.getElementById('i1'), { left: 50, right: 400 })
    window.dispatchEvent(new Event('resize'))
    await frame()
    expect(folded()).toEqual([true, true])
  })

  it('keeps its heights on :root and the page scroll padded while it sticks', async () => {
    vi.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockImplementation(function () {
      return this.id === 'h' ? 100 : this.classList.contains('webx-header__topbar') ? 30 : 0
    })
    await start(header('data-collapse="never" data-sticky="sticky"'))
    const root = document.documentElement

    expect(root.classList.contains('webx-header-sticky')).toBe(true)
    expect(root.style.getPropertyValue('--webx-header-height')).toBe('70px')
    expect(root.style.getPropertyValue('--webx-header-topbar-height')).toBe('30px')

    window.webx.unmount(document.body)
    expect(root.classList.contains('webx-header-sticky')).toBe(false)
    expect(root.style.getPropertyValue('--webx-header-height')).toBe('')
  })

  it('hides scrolling down and shows scrolling up, but not while its menu is open', async () => {
    vi.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockReturnValue(60)
    let y = 0
    vi.spyOn(window, 'scrollY', 'get').mockImplementation(() => y)
    await start(header('data-collapse="never" data-sticky="hide-on-scroll"'))
    const el = document.getElementById('h')
    const scroll = async (to) => {
      y = to
      window.dispatchEvent(new Event('scroll'))
      await frame()
    }

    await scroll(400)
    expect(el.classList.contains('is-hidden')).toBe(true)
    expect(el.classList.contains('is-scrolled')).toBe(true)
    await scroll(300)
    expect(el.classList.contains('is-hidden')).toBe(false)

    document.getElementById('d').setAttribute('open', '')
    await scroll(800)
    expect(el.classList.contains('is-hidden')).toBe(false)

    await scroll(10)
    expect(el.classList.contains('is-scrolled')).toBe(false)
  })
})
