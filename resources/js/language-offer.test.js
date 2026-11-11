import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { languageOffer } from './language-offer.js'

const HTML = `
  <nav class="webx-language-switcher webx-language-switcher--offer">
    <details class="webx-dropdown"><summary class="webx-dropdown__trigger">RU</summary></details>
    <div class="webx-language-offer" role="dialog" aria-modal="false" lang="uk"
      data-webx-language-offer="webx_lang_offer" data-webx-language-offer-lifetime="600" hidden>
      <p class="webx-language-offer__question">Перейти на українську?</p>
      <a class="webx-language-offer__accept" href="/uk/cameras">Так</a>
      <button type="button" class="webx-language-offer__stay">Залишитись</button>
      <button type="button" class="webx-language-offer__close" aria-label="Закрити"></button>
    </div>
  </nav>`

let stop

function clearCookies() {
  for (const pair of document.cookie.split(';')) {
    const name = pair.split('=')[0].trim()
    if (name) document.cookie = `${name}=; Max-Age=0; Path=/`
  }
}

// jsdom lays nothing out: a switcher is "on screen" unless it is hidden.
const realRects = Element.prototype.getClientRects
beforeEach(() => {
  Element.prototype.getClientRects = function () {
    return this.closest('[hidden], .is-hidden') ? [] : [{}]
  }
  clearCookies()
  document.body.innerHTML = HTML
  stop = languageOffer(document.querySelector('[data-webx-language-offer]'))
})

afterEach(() => {
  Element.prototype.getClientRects = realRects
  stop?.()
  document.body.innerHTML = ''
  clearCookies()
})

const offer = () => document.querySelector('.webx-language-offer')
const press = (key) =>
  document.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))

describe('the offer of the first visit language', () => {
  it('shows itself: the server prints it hidden for a page without the script', () => {
    expect(offer().hidden).toBe(false)
    expect(document.cookie).not.toContain('webx_lang_offer')
  })

  it('remembers "stay" in its cookie and goes away', () => {
    document.querySelector('.webx-language-offer__stay').click()

    expect(offer().hidden).toBe(true)
    expect(document.cookie).toContain('webx_lang_offer=1')
  })

  it('declines with the cross too, and hands the keyboard back to the switcher', () => {
    const close = document.querySelector('.webx-language-offer__close')
    close.focus()
    close.click()

    expect(offer().hidden).toBe(true)
    expect(document.cookie).toContain('webx_lang_offer=1')
    expect(document.activeElement).toBe(document.querySelector('.webx-dropdown__trigger'))
  })

  it('closes on Esc', () => {
    press('Escape')

    expect(offer().hidden).toBe(true)
    expect(document.cookie).toContain('webx_lang_offer=1')
  })

  it('leaves Esc to an open dropdown', () => {
    document.body.insertAdjacentHTML('beforeend', '<div class="webx-dropdown is-open"></div>')
    press('Escape')

    expect(offer().hidden).toBe(false)
    expect(document.cookie).not.toContain('webx_lang_offer')
  })

  it('takes "yes" as a plain link and remembers nothing', () => {
    const accept = document.querySelector('.webx-language-offer__accept')
    accept.addEventListener('click', (event) => event.preventDefault())
    accept.click()

    expect(document.cookie).not.toContain('webx_lang_offer')
  })

  it('keeps one offer, beside the switcher on screen, when the markup is printed twice', () => {
    stop()
    document.body.innerHTML = HTML.replace('<nav class="', '<nav class="is-hidden ') + HTML
    const [hidden, shown] = document.querySelectorAll('[data-webx-language-offer]')
    languageOffer(hidden)
    stop = languageOffer(shown)

    expect(document.querySelectorAll('[data-webx-language-offer]')).toHaveLength(1)
    expect(document.querySelector('[data-webx-language-offer]')).toBe(shown)
    expect(shown.hidden).toBe(false)
  })

  it('waits for a folded switcher to come on screen', () => {
    stop()
    document.body.innerHTML = HTML.replace('<nav class="', '<nav class="is-hidden ')
    const root = document.querySelector('[data-webx-language-offer]')
    stop = languageOffer(root)
    expect(root.hidden).toBe(true)

    // The menu opens: the observer of its size calls again; jsdom has none, so the test does.
    root.parentElement.classList.remove('is-hidden')
    stop()
    stop = languageOffer(root)
    expect(root.hidden).toBe(false)
  })

  it('ignores a cookie name it was not given', () => {
    stop()
    document.body.innerHTML = HTML.replace('webx_lang_offer"', 'a;b"')
    stop = languageOffer(document.querySelector('[data-webx-language-offer]'))

    expect(offer().hidden).toBe(true)
  })
})
