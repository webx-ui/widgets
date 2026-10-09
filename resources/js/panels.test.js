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

// The MutationObservers report after a microtask.
const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

const key = (el, name) =>
  el.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true }))

function pointer(el, type, pointerType = 'mouse') {
  const event = new MouseEvent(type, { bubbles: false, cancelable: true })
  Object.defineProperty(event, 'pointerType', { value: pointerType })
  el.dispatchEvent(event)
}

const click = (el) => {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true })
  el.dispatchEvent(event)
  return event
}

const rect = (el, box) => {
  el.getBoundingClientRect = () => ({
    width: box.right - box.left,
    height: box.bottom - box.top,
    x: box.left,
    y: box.top,
    ...box,
  })
}

/** A window `width` wide and `height` tall; a panel of the size given. */
function geometry({ width = 1280, height = 800, panel = [200, 120] } = {}) {
  Object.defineProperty(document.documentElement, 'clientWidth', {
    configurable: true,
    value: width,
  })
  vi.spyOn(window, 'innerHeight', 'get').mockReturnValue(height)
  for (const el of document.querySelectorAll('.webx-dropdown__panel')) {
    Object.defineProperty(el, 'offsetWidth', { configurable: true, value: panel[0] })
    Object.defineProperty(el, 'offsetHeight', { configurable: true, value: panel[1] })
    el.style.setProperty('--webx-dropdown-offset', '8px')
  }
  for (const el of document.querySelectorAll('.webx-dropdown')) {
    rect(el, { left: 0, top: 0, right: 0, bottom: 0 })
  }
}

const dropdown = (placement = 'bottom-start', openOn = 'click', id = 'a') => `
  <details class="webx-dropdown webx-dropdown--${placement}" data-webx-dropdown="${openOn}" id="${id}">
    <summary class="webx-dropdown__trigger">Phones</summary>
    <div class="webx-dropdown__panel"><a href="/one">One</a><a href="/two">Two</a></div>
  </details>`

beforeEach(() => {
  document.documentElement.className = ''
  history.replaceState(null, '', '/')
})

afterEach(() => {
  window.webx?.unmount(document.body)
  document.body.innerHTML = ''
  delete window.webx
  vi.restoreAllMocks()
  vi.useRealTimers()
})

describe('dropdown panel', () => {
  it('opens and closes on its trigger, with ARIA', async () => {
    await start(dropdown())
    geometry()
    const root = document.getElementById('a')
    const trigger = root.querySelector('summary')
    const panel = root.querySelector('.webx-dropdown__panel')

    expect(trigger.getAttribute('aria-controls')).toBe(panel.id)
    expect(trigger.getAttribute('aria-expanded')).toBe('false')

    expect(click(trigger).defaultPrevented).toBe(true)
    expect(root.open).toBe(true)
    expect(root.classList.contains('is-open')).toBe(true)
    expect(trigger.getAttribute('aria-expanded')).toBe('true')

    click(trigger)
    expect(root.open).toBe(false)
    expect(trigger.getAttribute('aria-expanded')).toBe('false')
  })

  it('keeps one open on the page', async () => {
    await start(dropdown('bottom-start', 'click', 'a') + dropdown('bottom-end', 'click', 'b'))
    geometry()
    const [a, b] = ['a', 'b'].map((id) => document.getElementById(id))

    click(a.querySelector('summary'))
    click(b.querySelector('summary'))
    expect(a.open).toBe(false)
    expect(b.open).toBe(true)
  })

  it('closes on Esc and gives the focus back to the trigger', async () => {
    await start(dropdown())
    geometry()
    const root = document.getElementById('a')
    const trigger = root.querySelector('summary')
    click(trigger)
    root.querySelector('a').focus()

    key(document.activeElement, 'Escape')
    expect(root.open).toBe(false)
    expect(document.activeElement).toBe(trigger)
  })

  it('closes on a click elsewhere and on Tab out of it', async () => {
    await start(dropdown() + '<button id="out">Out</button>')
    geometry()
    const root = document.getElementById('a')
    const out = document.getElementById('out')

    click(root.querySelector('summary'))
    click(root.querySelector('.webx-dropdown__panel'))
    expect(root.open).toBe(true)
    click(out)
    expect(root.open).toBe(false)

    click(root.querySelector('summary'))
    root.dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: out }))
    expect(root.open).toBe(false)
  })

  it('turns over at the right edge, and is held inside a window narrower than it', async () => {
    await start(dropdown())
    geometry({ width: 360, panel: [200, 120] })
    const root = document.getElementById('a')
    const trigger = root.querySelector('summary')
    const panel = root.querySelector('.webx-dropdown__panel')

    rect(trigger, { left: 20, right: 120, top: 10, bottom: 54 })
    click(trigger)
    expect(panel.style.inset).toBe('62px auto auto 20px')
    expect(panel.classList.contains('is-flipped')).toBe(false)
    click(trigger)

    // Starting at the trigger it would cross the edge: it ends at the trigger's right instead.
    rect(trigger, { left: 300, right: 340, top: 10, bottom: 54 })
    click(trigger)
    expect(panel.style.inset).toBe('62px auto auto 140px')
    expect(panel.classList.contains('is-flipped')).toBe(true)
    click(trigger)
    expect(panel.classList.contains('is-flipped')).toBe(false)
    expect(panel.style.inset).toBe('')

    // Wider than either side: inside the window.
    geometry({ width: 360, panel: [340, 120] })
    rect(trigger, { left: 150, right: 190, top: 10, bottom: 54 })
    click(trigger)
    expect(panel.style.inset).toBe('62px auto auto 0px')
  })

  it('opens upwards when there is no room below, and down from a top placement without room above', async () => {
    await start(dropdown('bottom-start', 'click', 'a') + dropdown('top-end', 'click', 'b'))
    geometry({ width: 1280, height: 600, panel: [200, 120] })
    const a = document.getElementById('a').querySelector('summary')
    const b = document.getElementById('b').querySelector('summary')

    rect(a, { left: 20, right: 120, top: 540, bottom: 584 })
    click(a)
    expect(document.getElementById('a').querySelector('.webx-dropdown__panel').style.inset).toBe(
      '412px auto auto 20px',
    )

    rect(b, { left: 400, right: 500, top: 10, bottom: 54 })
    click(b)
    expect(document.getElementById('b').querySelector('.webx-dropdown__panel').style.inset).toBe(
      '62px auto auto 300px',
    )
  })

  it('opens on hover after a delay and closes a while after the pointer left; a touch clicks', async () => {
    vi.useFakeTimers()
    await start(dropdown('bottom-start', 'hover'))
    geometry()
    const root = document.getElementById('a')

    pointer(root, 'pointerenter')
    expect(root.open).toBe(false)
    vi.advanceTimersByTime(150)
    expect(root.open).toBe(true)

    // The click of the same mouse a moment later keeps it open.
    click(root.querySelector('summary'))
    expect(root.open).toBe(true)

    pointer(root, 'pointerleave')
    vi.advanceTimersByTime(100)
    expect(root.open).toBe(true)
    vi.advanceTimersByTime(300)
    expect(root.open).toBe(false)

    pointer(root, 'pointerenter', 'touch')
    vi.advanceTimersByTime(500)
    expect(root.open).toBe(false)
    vi.advanceTimersByTime(1000)
    click(root.querySelector('summary'))
    expect(root.open).toBe(true)
  })

  it('opens what the server printed open', async () => {
    await start(dropdown().replace('id="a"', 'id="a" open'))
    const root = document.getElementById('a')
    expect(root.open).toBe(true)
    expect(root.classList.contains('is-open')).toBe(true)
    expect(root.querySelector('summary').getAttribute('aria-expanded')).toBe('true')
  })

  it('gives everything back when unmounted', async () => {
    const webx = await start(dropdown())
    geometry()
    const root = document.getElementById('a')
    click(root.querySelector('summary'))
    webx.unmount(document.body)
    expect(root.open).toBe(false)
    expect(root.querySelector('summary').hasAttribute('aria-expanded')).toBe(false)
  })
})

const formDialog = (open = false) => `
  <a id="call" href="#webx-form-callback" data-webx-form="callback">Request a call</a>
  <a id="book" href="#webx-form-callback" data-webx-form="callback" data-webx-form-value-service="12">Book</a>
  <a id="plain" href="#webx-form-callback">Link</a>
  <a id="none" href="/contacts" data-webx-form="nothing">No such form</a>
  <dialog id="webx-form-callback" class="webx-dialog webx-form-dialog" data-webx-form-dialog="callback"${open ? ' open' : ''}>
    <a class="webx-dialog__close" href="#" data-webx-dialog-close>×</a>
    <form method="post" data-webx-form="callback" class="wx-form wx-form--modal">
      <input type="hidden" name="webx_form" value="callback">
      <input type="hidden" name="fields[service]" value="">
      <div class="wx-form__fields"><input name="fields[name]"></div>
      <div class="wx-form__message" data-webx-message hidden><p data-webx-message-heading></p></div>
      <div class="wx-form__actions"><button type="submit">Send</button></div>
    </form>
    <button type="button" class="webx-form-dialog__done" data-webx-dialog-close hidden>Close</button>
  </dialog>`

describe('form in a dialog', () => {
  it('opens from any opener of its slug, and from a link to it', async () => {
    await start(formDialog())
    const dialog = document.getElementById('webx-form-callback')

    expect(click(document.getElementById('call')).defaultPrevented).toBe(true)
    expect(dialog.open).toBe(true)
    dialog.close()
    await settle()
    expect(document.activeElement).toBe(document.getElementById('call'))

    click(document.getElementById('plain'))
    expect(dialog.open).toBe(true)
    dialog.close()
    await settle()

    // The form carries the same attribute for the module's script: it opens nothing.
    click(dialog.querySelector('form'))
    expect(dialog.open).toBe(false)
  })

  it('leaves a link to a form the page has no dialog for alone', async () => {
    await start(formDialog())
    expect(click(document.getElementById('none')).defaultPrevented).toBe(false)
  })

  it('fills a field from the opener, and the next opener gets it back as printed', async () => {
    await start(formDialog())
    const dialog = document.getElementById('webx-form-callback')
    const service = dialog.querySelector('[name="fields[service]"]')

    click(document.getElementById('book'))
    expect(service.value).toBe('12')
    dialog.close()
    await settle()

    click(document.getElementById('call'))
    expect(service.value).toBe('')
  })

  it('shows the thank-you with a button that closes it, and is a fresh form again after', async () => {
    await start(formDialog())
    const dialog = document.getElementById('webx-form-callback')
    const fields = dialog.querySelector('.wx-form__fields')
    const done = dialog.querySelector('.webx-form-dialog__done')
    const message = dialog.querySelector('[data-webx-message]')

    click(document.getElementById('call'))
    // What the module's script does on a 200.
    message.hidden = false
    await settle()

    expect(dialog.open).toBe(true)
    expect(dialog.classList.contains('is-sent')).toBe(true)
    expect(fields.hidden).toBe(true)
    expect(dialog.querySelector('.wx-form__actions').hidden).toBe(true)
    expect(done.hidden).toBe(false)
    expect(document.activeElement).toBe(done)

    click(done)
    await settle()
    expect(dialog.open).toBe(false)
    expect(dialog.classList.contains('is-sent')).toBe(false)
    expect(fields.hidden).toBe(false)
    expect(message.hidden).toBe(true)
    expect(done.hidden).toBe(true)
  })

  it('opens again as a modal when the page came back from it', async () => {
    const showModal = vi.spyOn(HTMLDialogElement.prototype, 'showModal')
    await start(formDialog(true))
    const dialog = document.getElementById('webx-form-callback')
    expect(dialog.open).toBe(true)
    expect(showModal).toHaveBeenCalled()
    expect(document.documentElement.classList.contains('webx-scroll-locked')).toBe(true)
  })
})
