/*
 * The lightbox (spec §8): PhotoSwipe's core — zoom, a group to page through, swipes, pinch — built
 * in here, and only here. Its own `photoswipe/lightbox` module is not used: it loads the core as a
 * second file, one more request, and this script is already the one a page loads for it.
 *
 * A link is the whole contract: `<a href="<picture>" data-webx-lightbox="<group>" data-width
 * data-height>`. Without this script it opens the picture; with it the picture opens over the
 * page. Links with the same group page through together, in the order of the page — a slider's
 * slides in their own order, which is not the page's once Swiper's loop has moved them round. An
 * empty group is a picture on its own.
 *
 * PhotoSwipe draws its own DOM under its own `.pswp*` classes, and unlike Swiper it has no option
 * to rename them: its stylesheet comes as it is, and its look is repainted from the site's tokens
 * through its own custom properties on `.webx-lightbox`, the class this script gives its root.
 * The words, and the icons — the chain's, so a theme's arrow is the lightbox's arrow too — come
 * from the server in `<script type="application/json" id="webx-lightbox">`.
 */

import 'photoswipe/style.css'
import '../css/lightbox.css'
import PhotoSwipe from 'photoswipe'

const webx = (window.webx ??= {})

const LINK = 'a[data-webx-lightbox]'

/** The one open: its PhotoSwipe and the links of its group. */
let open = null

function settings() {
  try {
    return JSON.parse(document.getElementById('webx-lightbox')?.textContent || '{}')
  } catch {
    return {}
  }
}

/** Where a link stands: a slide of a looping slider by the slide's own number, anything else by itself. */
function place(link) {
  const slide = link.closest('[data-swiper-slide-index]')
  return slide?.parentElement
    ? { at: slide.parentElement, index: Number(slide.dataset.swiperSlideIndex) }
    : { at: link, index: 0 }
}

/** The links that page through with this one, in the order a visitor reads them. */
export function members(link) {
  const group = link.dataset.webxLightbox
  if (!group) return [link]

  return Array.from(document.querySelectorAll(LINK))
    .filter(
      (el) => el.dataset.webxLightbox === group && (el === link || !el.closest('[data-webx-copy]')),
    )
    .map((el) => ({ el, ...place(el) }))
    .sort((a, b) => {
      if (a.at === b.at) return a.index - b.index
      return a.at.compareDocumentPosition(b.at) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1
    })
    .map(({ el }) => el)
}

function item(link) {
  const picture = link.querySelector('img')
  return {
    src: link.href,
    width: Number(link.dataset.width) || 0,
    height: Number(link.dataset.height) || 0,
    alt: picture?.alt ?? link.getAttribute('aria-label') ?? '',
    msrc: picture?.currentSrc || picture?.getAttribute('src') || undefined,
    element: link,
  }
}

/**
 * A picture without its sizes is measured by loading it: PhotoSwipe places the picture before it
 * arrives, and a guess would jump. The library's pictures always have them (§8); the audit finds
 * the links that do not (§15.2).
 */
function measure(data) {
  if (data.width > 0 && data.height > 0) return Promise.resolve(false)

  return new Promise((resolve) => {
    const image = new Image()
    image.onload = () => {
      data.width = image.naturalWidth
      data.height = image.naturalHeight
      resolve(true)
    }
    image.onerror = () => resolve(false)
    image.src = data.src
  })
}

/** Brings the slide a link stands in into view, so the picture closes into its thumbnail. */
function reveal(link) {
  const viewport = link.closest('.webx-slider__viewport')
  const slide = link.closest('.webx-slider__slide')
  const swiper = viewport?.swiper
  if (!swiper || !slide || swiper.destroyed) return

  if (swiper.params.loop) swiper.slideToLoop(Number(slide.dataset.swiperSlideIndex) || 0, 0)
  else swiper.slideTo(Math.max(0, swiper.slides.indexOf(slide)), 0)
}

export function close() {
  open?.pswp.destroy()
  open = null
}

export async function show(link) {
  close()

  const links = members(link)
  const items = links.map(item)
  const index = Math.max(0, links.indexOf(link))
  const { words = {}, icons = {} } = settings()

  await measure(items[index])

  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
  const pswp = new PhotoSwipe({
    dataSource: items,
    index,
    mainClass: 'webx-lightbox',
    showHideAnimationType: reduced ? 'none' : 'zoom',
    // Opaque: a page showing through competes with the counter and the buttons on a phone.
    bgOpacity: 1,
    // Focus goes back to the link of the picture last shown, not always to the opener: after
    // paging to the fifth, Tab goes on from the fifth.
    returnFocus: false,
    closeTitle: words.close,
    zoomTitle: words.zoom,
    arrowPrevTitle: words.prev,
    arrowNextTitle: words.next,
    errorMsg: words.error,
    arrowPrevSVG: icons.prev,
    arrowNextSVG: icons.next,
    closeSVG: icons.close,
    zoomSVG: icons.zoom,
  })

  // A picture of the group without sizes: measured when it comes up, then laid out again.
  pswp.on('change', () => {
    const at = pswp.currIndex
    measure(items[at]).then((changed) => {
      if (changed && !pswp.isDestroying) pswp.refreshSlideContent(at)
    })
  })

  pswp.on('close', () => reveal(links[pswp.currIndex] ?? link))
  pswp.on('destroy', () => {
    const last = links[pswp.currIndex] ?? link
    if (open?.pswp === pswp) open = null
    ;(last.isConnected ? last : link).focus()
  })

  open = { pswp, links }
  pswp.init()

  // PhotoSwipe's English roles and an unnamed dialog, in the page's language.
  pswp.element?.setAttribute('aria-modal', 'true')
  if (words.dialog) pswp.element?.setAttribute('aria-label', words.dialog)
  if (words.carousel) pswp.scrollWrap?.setAttribute('aria-roledescription', words.carousel)
  if (words.slide) {
    pswp.mainScroll?.itemHolders?.forEach(({ el }) =>
      el.setAttribute('aria-roledescription', words.slide),
    )
  }

  return pswp
}

export function lightbox(link) {
  const click = (event) => {
    // A new tab, a download, a drag Swiper turned into a swipe: the browser's, or nobody's.
    if (event.defaultPrevented || event.button !== 0) return
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
    event.preventDefault()
    show(link)
  }

  link.addEventListener('click', click)
  link.setAttribute('aria-haspopup', 'dialog')
  link.classList.add('is-ready')

  return () => {
    link.removeEventListener('click', click)
    link.removeAttribute('aria-haspopup')
    link.classList.remove('is-ready')
    if (open?.links.includes(link)) close()
  }
}

webx.widget?.('lightbox', LINK, lightbox)
