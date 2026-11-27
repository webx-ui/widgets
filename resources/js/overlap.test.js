import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { overlap } from './behaviours/overlap.js'

// jsdom has no layout: each test says how tall the screen, the pinned section, this section and
// its edge are. The browser has scroll-driven animations here, so nothing is written per scroll.
const height = (el, value) =>
  Object.defineProperty(el, 'offsetHeight', { value, configurable: true })

function page({ hero, own, edge }) {
  document.body.innerHTML = `
    <main>
      <section class="hero"><div data-webx-overlap-fade>Words</div></section>
      <section data-webx-overlap><picture class="webx-overlap__edge"><img class="webx-overlap__image" alt=""></picture></section>
    </main>`
  const [pinned, section] = document.querySelectorAll('section')
  height(pinned, hero)
  height(section, own)
  height(section.querySelector('.webx-overlap__edge'), edge)
  return { pinned, section, picture: section.querySelector('.webx-overlap__edge') }
}

beforeEach(() => {
  vi.stubGlobal('CSS', { supports: () => true })
  vi.stubGlobal('innerHeight', 823)
})

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('overlap edge', () => {
  it('shows the whole edge only when its section comes up: it waits below by as much', () => {
    const { section, picture } = page({ hero: 380, own: 900, edge: 490 })
    const off = overlap(section)

    expect(picture.classList.contains('webx-overlap__edge--whole')).toBe(true)
    expect(picture.classList.contains('webx-overlap__edge--ahead')).toBe(true)
    // The edge whole and this section that much lower: the same part of it shows over the one above.
    expect(parseFloat(section.style.getPropertyValue('--webx-overlap-sink'))).toBeGreaterThan(0)

    off()
    expect(picture.classList.contains('webx-overlap__edge--whole')).toBe(false)
  })

  it('keeps the edge at rest when its section has no way to come up', () => {
    // A short section under a short hero: its bottom is on the screen before the pin starts.
    const { section, picture } = page({ hero: 380, own: 420, edge: 490 })
    overlap(section)

    // Whole, the fog would lie over the hero's words from the first frame — and be the largest paint.
    expect(picture.classList.contains('webx-overlap__edge--whole')).toBe(false)
    expect(picture.classList.contains('webx-overlap__edge--ahead')).toBe(false)
    expect(section.style.getPropertyValue('--webx-overlap-sink')).toBe('0px')
    expect(section.classList.contains('webx-overlap--rising')).toBe(true)
  })
})
