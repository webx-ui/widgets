import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  document.cookie = 'webx_consent=; Max-Age=0; Path=/'
})

const EMBED = 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?autoplay=1'

// What `<x-webx-video>` prints: the facade, and before consent the notice of §9.4 over it.
const video = (n, blocked = true) => `
  <div class="webx-video webx-video--youtube${blocked ? ' is-blocked' : ''}" id="v${n}"
       data-webx-video='${JSON.stringify({ embed: `${EMBED}&n=${n}`, title: `Clip ${n}` })}' data-webx-consent="media">
    <a class="webx-video__facade" href="https://www.youtube.com/watch?v=aqz-KE-bpKQ" aria-label="Play: Clip ${n}">
      <img class="webx-video__poster" src="/poster.webp" alt=""><span class="webx-video__play"></span>
    </a>
    ${
      blocked
        ? `<div class="webx-video__consent"><p class="webx-video__notice">The video loads from YouTube</p>
      <button type="button" class="webx-video__button" data-webx-video-load>Load</button>
      <button type="button" class="webx-video__button webx-video__button--always" data-webx-video-always>Always load videos</button></div>`
        : ''
    }
  </div>`

// The banner's root, as the server prints it when the visitor has not answered yet.
const banner = `<div data-webx-consent-root data-version="1"><div data-webx-consent-banner hidden></div></div>`

async function start(html, { consent = false } = {}) {
  document.body.innerHTML = html + (consent ? banner : '')
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  const module = await import('./video.js')
  if (consent) await import('./consent.js')
  return module
}

const frame = (n) => document.querySelector(`#v${n} iframe.webx-video__frame`)

describe('video', () => {
  it('makes the link a play button, and puts the player in only on a click, with the focus in it', async () => {
    await start(video(1, false))
    const root = document.getElementById('v1')

    const facade = root.querySelector('.webx-video__facade')
    expect(facade.tagName).toBe('BUTTON')
    expect(facade.type).toBe('button')
    expect(facade.getAttribute('aria-label')).toBe('Play: Clip 1')
    expect(facade.querySelector('img.webx-video__poster')).not.toBeNull()
    expect(root.classList.contains('is-ready')).toBe(true)
    expect(frame(1)).toBeNull()

    facade.click()

    const iframe = frame(1)
    expect(iframe.src).toBe(`${EMBED}&n=1`)
    expect(iframe.title).toBe('Clip 1')
    expect(iframe.allow).toContain('autoplay')
    expect(iframe.allow).toContain('fullscreen')
    expect(root.querySelector('.webx-video__facade')).toBeNull()
    expect(root.classList.contains('is-playing')).toBe(true)
    expect(document.activeElement).toBe(iframe)
  })

  it('before consent the play button is out of reach, and nothing loads until asked', async () => {
    await start(video(1))
    const root = document.getElementById('v1')
    const facade = root.querySelector('.webx-video__facade')

    expect(facade.inert).toBe(true)
    facade.click()
    expect(frame(1)).toBeNull()
    expect(document.querySelector('iframe')).toBeNull()
  })

  it('"Load" plays this one only, and gives no consent', async () => {
    await start(video(1) + video(2), { consent: true })

    document.querySelector('#v1 [data-webx-video-load]').click()

    expect(frame(1)).not.toBeNull()
    expect(document.querySelector('#v1 .webx-video__consent')).toBeNull()
    expect(document.getElementById('v1').classList.contains('is-blocked')).toBe(false)
    expect(frame(2)).toBeNull()
    expect(document.getElementById('v2').classList.contains('is-blocked')).toBe(true)
    expect(window.webx.consent.has('media')).toBe(false)
    expect(document.cookie).not.toContain('webx_consent')
  })

  it('"Always load videos" agrees to media: this one plays, every other placeholder becomes a facade', async () => {
    await start(video(1) + video(2) + video(3), { consent: true })
    const heard = vi.fn()
    document.addEventListener('webx:consent', heard)

    document.querySelector('#v2 [data-webx-video-always]').click()

    expect(window.webx.consent.has('media')).toBe(true)
    expect(decodeURIComponent(document.cookie)).toContain('"c":["media"]')
    expect(heard).toHaveBeenCalledTimes(1)
    expect(frame(2)).not.toBeNull()
    for (const n of [1, 3]) {
      const root = document.getElementById(`v${n}`)
      expect(root.classList.contains('is-blocked')).toBe(false)
      expect(root.querySelector('.webx-video__consent')).toBeNull()
      expect(root.querySelector('.webx-video__facade').inert).toBe(false)
      expect(frame(n), 'a facade until its own click').toBeNull()
    }

    document.querySelector('#v3 .webx-video__facade').click()
    expect(frame(3)).not.toBeNull()
    document.removeEventListener('webx:consent', heard)
  })

  it('a piece of the page mounted after the answer follows the answer the browser has', async () => {
    document.cookie = `webx_consent=${encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['media'] }))}; Path=/`
    await start('', { consent: true })
    // Printed blocked — a block the panel swapped in, a piece fetched later — and mounted then.
    document.body.insertAdjacentHTML('beforeend', video(1))
    window.webx.mount()

    expect(document.getElementById('v1').classList.contains('is-blocked')).toBe(false)
  })

  it('without the banner\'s script "Always load videos" still plays the one clicked', async () => {
    await start(video(1))
    document.querySelector('#v1 [data-webx-video-always]').click()
    expect(frame(1)).not.toBeNull()
  })

  it('mounts once, lets go on unmount and comes back on a second mount', async () => {
    await start(video(1, false))
    const root = document.getElementById('v1')
    window.webx.mount()
    window.webx.mount()

    window.webx.unmount()
    expect(root.classList.contains('is-ready')).toBe(false)
    root.querySelector('.webx-video__facade').click()
    expect(frame(1), 'no listener left').toBeNull()

    window.webx.mount()
    root.querySelector('.webx-video__facade').click()
    expect(document.querySelectorAll('#v1 iframe')).toHaveLength(1)
  })

  it('a video of the site needs nothing from the script', async () => {
    await start(
      '<div class="webx-video webx-video--file"><video class="webx-video__player" controls preload="none"></video></div>',
    )
    const player = document.querySelector('video')
    expect(player.preload).toBe('none')
    expect(document.querySelector('.webx-video').classList.contains('is-ready')).toBe(false)
  })
})
