/*
 * Where the visitor came from, into the submission (FORM_STEPS §8). Its own file, claimed with
 * form.js by a form that asks for it — `<x-webx-form data-webx-attribution>`, which inbox's form is.
 *
 * On `webx:form:submit` of such a form it adds to `detail.data`, unless the form has the name
 * already: the `utm_*` of the last touch, `webx_referrer` (the outside page the visitor came
 * from), `webx_landing` (the page they came in on), and `webx_utm_first[…]` — the campaign of the
 * first touch. No `gclid` / `fbclid`: those are `marketing`, another conversation.
 *
 * Without consent to `statistics` nothing is kept: the last touch is this page — its `utm_*`, its
 * referrer when it is from outside — which is the landing page of an advert, the usual case. With
 * it, `localStorage` `webx_attribution` keeps `{ first, last }` for 90 days: the first touch is
 * never written over, the last is every arrival that brought a campaign or an outside referrer.
 * Taking consent back (`webx:consent`) removes the key: kept without consent, it would be the kind
 * of storage for statistics ePrivacy does not allow.
 */

const KEY = 'webx_attribution'
const DAYS = 90
const webx = (window.webx ??= {})

const agreed = () => webx.consent?.has?.('statistics') === true

function outside(referrer) {
  try {
    return referrer && new URL(referrer).origin !== location.origin ? referrer : ''
  } catch {
    return ''
  }
}

/**
 * What this page says of the visit. An arrival — from outside, typed in, or by a campaign's link —
 * also names itself the landing page; a page reached from another of the site's does not.
 */
export function touch(url = location.href, referrer = document.referrer) {
  const address = new URL(url)
  const utm = {}
  for (const [key, value] of address.searchParams) {
    if (key.startsWith('utm_') && value) utm[key] = value
  }
  const from = outside(referrer)
  address.hash = ''
  const arrived = !referrer || from !== '' || Object.keys(utm).length > 0
  return { utm, referrer: from, landing: arrived ? address.href : '', at: Date.now() }
}

const fresh = (point) => point && Date.now() - point.at < DAYS * 86400000

function stored() {
  try {
    const kept = JSON.parse(localStorage.getItem(KEY) ?? 'null')
    return {
      first: fresh(kept?.first) ? kept.first : null,
      last: fresh(kept?.last) ? kept.last : null,
    }
  } catch {
    return { first: null, last: null }
  }
}

function forget() {
  try {
    localStorage.removeItem(KEY)
  } catch {
    // Storage switched off: there is nothing kept to take back.
  }
}

/** This page into the store, with consent; without it, whatever was kept goes. */
export function remember() {
  if (!agreed()) return forget()
  const now = touch()
  if (!now.landing) return
  const kept = stored()
  // A campaign or an outside page is a touch; an address typed in or a reload is one only for the first.
  const counts = Object.keys(now.utm).length > 0 || now.referrer !== ''
  const next = { first: kept.first ?? now, last: counts || !kept.last ? now : kept.last }
  try {
    localStorage.setItem(KEY, JSON.stringify(next))
  } catch {
    // Full or switched off: the submission still gets this page.
  }
}

function add(data, name, value) {
  if (value && !data.has(name)) data.append(name, value)
}

document.addEventListener('webx:form:submit', (event) => {
  const form = event.target
  const data = event.detail?.data
  if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-webx-attribution') || !data)
    return
  const kept = agreed() ? stored() : { first: null, last: null }
  const last = kept.last ?? touch()
  for (const [key, value] of Object.entries(last.utm)) add(data, key, value)
  add(data, 'webx_referrer', last.referrer)
  add(data, 'webx_landing', last.landing)
  for (const [key, value] of Object.entries(kept.first?.utm ?? {}))
    add(data, `webx_utm_first[${key}]`, value)
})

document.addEventListener('webx:consent', remember)

// On `load`, not now: a module runs while the page is already `interactive`, before consent.js —
// claimed later — has said what was agreed to. A submit before then still sends this page.
if (document.readyState === 'complete') remember()
else window.addEventListener('load', remember, { once: true })
