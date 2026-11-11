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
 *
 * Two more attributes of a link, both optional: `data-webx-lightbox-ratio="4:3"` — a frame of that
 * shape the picture is fitted into whole (as is without it), and `data-webx-lightbox-opacity` —
 * the backdrop's, over `--webx-lightbox-backdrop-opacity` (1 unless a theme says otherwise).
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

/** `4:3`, `4/3` or `1.5` as a width over a height; anything else is no frame. */
export function ratio(value) {
  const [w, h = '1'] = String(value ?? '')
    .trim()
    .split(/\s*[:/]\s*/)
  const shape = Number(w) / Number(h)
  return Number.isFinite(shape) && shape > 0 ? shape : null
}

/**
 * The frame a picture is shown in: the smallest box of the asked shape that holds the whole
 * picture at its own size. PhotoSwipe lays the box out, zooms it and animates it like any
 * picture; `object-fit: contain` in the stylesheet keeps the picture inside it undistorted, on
 * the frame's own background. Contain, not cover: a lightbox is where the whole picture is seen.
 */
function frame(data) {
  if (!data.ratio || !(data.width > 0 && data.height > 0)) return
  if (data.width / data.height > data.ratio) data.height = Math.round(data.width / data.ratio)
  else data.width = Math.round(data.height * data.ratio)
}

/** The stand-in thumbnail of a framed picture, in the frame's shape. */
export function framePlaceholder(content) {
  const placeholder = content.placeholder?.element
  if (!content.data.ratio || !placeholder) return
  placeholder.classList.add('webx-lightbox__framed')
  placeholder.style.aspectRatio = String(content.data.ratio)
}

/** The backdrop's opacity: the link's own, else the token's as the link sees it, else opaque. */
function opacity(link) {
  const own = link.dataset.webxLightboxOpacity
  const token = own ?? getComputedStyle(link).getPropertyValue('--webx-lightbox-backdrop-opacity')
  const value = Number.parseFloat(token)
  return Number.isFinite(value) ? Math.min(1, Math.max(0, value)) : 1
}

/**
 * The thumbnail PhotoSwipe zooms up into the picture while it loads, if it has the picture's
 * shape. A cropped one — a square file a grid picks on a phone — would grow in its own shape and
 * jump to the picture's when the picture comes in; without it the zoom grows a plain box of the
 * picture's shape instead. A thumbnail not yet loaded has no shape to tell: it is taken.
 */
export function thumbnail(picture, width, height) {
  const src = picture?.currentSrc || picture?.getAttribute('src') || undefined
  const own = picture?.naturalWidth / picture?.naturalHeight
  if (!src || !(width > 0 && height > 0) || !(own > 0)) return src
  return Math.abs(own / (width / height) - 1) <= 0.02 ? src : undefined
}

function item(link) {
  const picture = link.querySelector('img')
  const width = Number(link.dataset.width) || 0
  const height = Number(link.dataset.height) || 0
  const data = {
    src: link.href,
    width,
    height,
    alt: picture?.alt ?? link.getAttribute('aria-label') ?? '',
    msrc: thumbnail(picture, width, height),
    element: link,
    ratio: ratio(link.dataset.webxLightboxRatio),
  }
  frame(data)
  return data
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
      frame(data)
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
  // Where the page stood: it stands there again after closing, whichever picture closed it.
  const scroll = { left: window.scrollX, top: window.scrollY }

  await measure(items[index])

  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
  const pswp = new PhotoSwipe({
    dataSource: items,
    index,
    mainClass: 'webx-lightbox',
    showHideAnimationType: reduced ? 'none' : 'zoom',
    // Opaque unless a theme says otherwise: a page showing through competes with the counter
    // and the buttons on a phone.
    bgOpacity: opacity(link),
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

  // A framed picture gets the frame's background; the rest stay as they are, a PNG's
  // transparency included.
  pswp.on('contentAppend', ({ content }) => {
    if (content.data.ratio) content.element?.classList.add('webx-lightbox__framed')
  })
  // The thumbnail standing in until the picture loads keeps its own shape (PhotoSwipe gives it
  // `height: auto`) and would stick out of the frame. It gets the frame's shape as soon as it is
  // made — `contentLoad` comes right after — not on `contentAppend`: that one comes after the
  // opening zoom, which would then land on the thumbnail's shape and jump to the frame's.
  pswp.on('contentLoad', ({ content }) => framePlaceholder(content))

  pswp.on('close', () => reveal(links[pswp.currIndex] ?? link))
  pswp.on('destroy', () => {
    const last = links[pswp.currIndex] ?? link
    if (open?.pswp === pswp) open = null
    // Without `preventScroll` the focus would scroll the page to the last picture's link.
    // A link the page keeps hidden — the pictures a still mosaic does not show — cannot take it:
    // the opener does.
    const target = last.isConnected && !last.closest('[hidden], [inert]') ? last : link
    target.focus({ preventScroll: true })
    if (window.scrollX !== scroll.left || window.scrollY !== scroll.top) {
      // `instant`: the page's `scroll-behavior: smooth` would glide back from somewhere else.
      window.scrollTo({ ...scroll, behavior: 'instant' })
    }
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
