/*
 * Cookie consent (spec §9): the banner, its dialog, and everything third-party that waits for
 * an answer. Its own file, claimed on every page, after the runtime: it registers through
 * `webx.widget` and imports nothing the runtime has, so the two never share a chunk.
 *
 * The answer is the first-party cookie `webx_consent` — `{"v", "d", "c"}`: the policy version it
 * was given to, the date, the optional categories agreed to — which the server reads as well
 * (`Consent::has()`). An answer to an older version is no answer: the banner asks again.
 *
 * What waits, until its category is agreed to:
 *   <script type="text/plain" data-webx-consent="statistics" src="…">  re-created with the real
 *       type (`data-webx-type`, or none: classic), so the browser runs it;
 *   <iframe data-webx-consent="media" data-src="…">                    gets its `src`;
 *   <template data-webx-consent="marketing">…</template>                its content goes on the page,
 *       scripts inside re-created (what `<x-webx-consent>` prints).
 * Taking an answer back reloads the page: a script that ran cannot be unloaded.
 *
 * For the site's own scripts: `webx.consent.has(category)`, and `webx:consent` on `document`
 * with `{ categories }` whenever the answer changes. Google Consent Mode v2: the default is in
 * the head (server-side, before any tag); the update goes out here.
 */

import '../css/consent.css'

const COOKIE = 'webx_consent'
const OPTIONAL = ['preferences', 'statistics', 'marketing', 'media']
const BLOCKED =
  'script[type="text/plain"][data-webx-consent],iframe[data-webx-consent][data-src],template[data-webx-consent]'

const webx = (window.webx ??= {})

function read(version) {
  const raw = document.cookie
    .split('; ')
    .find((pair) => pair.startsWith(`${COOKIE}=`))
    ?.slice(COOKIE.length + 1)
  if (!raw) return null
  try {
    const answer = JSON.parse(decodeURIComponent(raw))
    return String(answer?.v) === version && Array.isArray(answer.c) ? answer : null
  } catch {
    return null
  }
}

function write(version, categories, days) {
  const value = encodeURIComponent(
    JSON.stringify({ v: version, d: new Date().toISOString().slice(0, 10), c: categories }),
  )
  const secure = location.protocol === 'https:' ? '; Secure' : ''
  document.cookie = `${COOKIE}=${value}; Path=/; Max-Age=${days * 86400}; SameSite=Lax${secure}`
}

/** A script built by the parser stays inert wherever it is moved: only a new one runs. */
function revive(old) {
  const script = document.createElement('script')
  for (const { name, value } of Array.from(old.attributes)) {
    if (name !== 'type' && name !== 'data-webx-consent' && name !== 'data-webx-type') {
      script.setAttribute(name, value)
    }
  }
  const type = old.getAttribute('data-webx-type')
  if (type) script.type = type
  // Inserted scripts load async; several from one place keep their order as written.
  if (script.src) script.async = false
  script.textContent = old.textContent
  old.replaceWith(script)
}

function release(el) {
  if (el instanceof HTMLScriptElement) {
    revive(el)
  } else if (el instanceof HTMLIFrameElement) {
    el.src = el.getAttribute('data-src') ?? ''
    el.removeAttribute('data-src')
  } else if (el instanceof HTMLTemplateElement) {
    const content = el.content.cloneNode(true)
    const scripts = Array.from(content.querySelectorAll('script'))
    el.replaceWith(content)
    scripts.forEach(revive)
  }
}

function googleUpdate(has) {
  const state = (granted) => (granted ? 'granted' : 'denied')
  const gtag =
    typeof window.gtag === 'function'
      ? window.gtag
      : Array.isArray(window.dataLayer)
        ? function () {
            window.dataLayer.push(arguments)
          }
        : null
  gtag?.('consent', 'update', {
    ad_storage: state(has('marketing')),
    ad_user_data: state(has('marketing')),
    ad_personalization: state(has('marketing')),
    analytics_storage: state(has('statistics')),
    functionality_storage: state(has('preferences')),
    personalization_storage: state(has('preferences')),
  })
}

export function consent(root) {
  const version = root.getAttribute('data-version') ?? '1'
  const days = Number(root.getAttribute('data-lifetime')) || 365
  const off = root.hasAttribute('data-off')
  const gpc = root.hasAttribute('data-gpc') || navigator.globalPrivacyControl === true
  const banner = root.querySelector('[data-webx-consent-banner]')
  const dialog = root.querySelector('dialog')
  const waiting = new Set()

  let answer = off ? null : read(version)
  let granted = new Set(off ? OPTIONAL : (answer?.c ?? []))
  const has = (category) => category === 'necessary' || granted.has(category)

  const switches = () =>
    Array.from(root.querySelectorAll('[data-webx-consent-category]')).filter(
      (input) => input.name !== 'necessary',
    )

  // A category is offered when the server saw it on the site, when this page asks for it, or
  // when it was agreed to already — whatever is granted must be visible to be taken back.
  const offer = () => {
    for (const row of root.querySelectorAll('[data-webx-consent-row]')) {
      const category = row.getAttribute('data-webx-consent-row')
      if (granted.has(category) || document.querySelector(`[data-webx-consent="${category}"]`)) {
        row.hidden = false
      }
    }
    for (const input of switches()) input.checked = granted.has(input.name)
    const note = root.querySelector('[data-webx-consent-gpc]')
    if (note) note.hidden = !gpc
  }

  const decide = (categories) => {
    const next = new Set(categories.filter((category) => OPTIONAL.includes(category)))
    const withdrawn = answer !== null && [...granted].some((category) => !next.has(category))
    write(version, [...next], days)
    answer = read(version) ?? { v: version, c: [...next] }

    if (withdrawn) {
      location.reload()
      return
    }

    granted = next
    if (banner) banner.hidden = true
    if (dialog?.open) dialog.close()
    for (const el of [...waiting]) {
      if (has(el.getAttribute('data-webx-consent'))) {
        waiting.delete(el)
        release(el)
      }
    }
    googleUpdate(has)
    document.dispatchEvent(
      new CustomEvent('webx:consent', { detail: { categories: [...granted] } }),
    )
  }

  // Marketing is never part of "all" when the browser asks not to be tracked.
  const all = () => OPTIONAL.filter((category) => !(gpc && category === 'marketing'))

  webx.consent = {
    has,
    get categories() {
      return [...granted]
    },
    set: decide,
    open() {
      // Through the runtime's dialog behaviour, the way the banner's own button opens it.
      root.querySelector('[data-webx-dialog]')?.click()
    },
  }

  const onClick = (event) => {
    const button = event.target instanceof Element ? event.target.closest('button') : null
    if (!button || !root.contains(button)) return
    if (button.hasAttribute('data-webx-consent-accept')) decide(all())
    else if (button.hasAttribute('data-webx-consent-reject')) decide([])
    else if (button.hasAttribute('data-webx-consent-save'))
      decide(
        switches()
          .filter((input) => input.checked)
          .map((input) => input.name),
      )
  }
  root.addEventListener('click', onClick)

  // The dialog shows what is agreed to now, whatever was switched and left unsaved before.
  const observer = new MutationObserver(() => dialog?.open && offer())
  if (dialog) observer.observe(dialog, { attributes: true, attributeFilter: ['open'] })

  webx.widget?.('consent-blocked', BLOCKED, (el) => {
    const category = el.getAttribute('data-webx-consent')
    if (has(category)) {
      release(el)
      return
    }
    waiting.add(el)
    return () => waiting.delete(el)
  })

  offer()
  if (banner && !off && answer === null) banner.hidden = false

  return () => {
    root.removeEventListener('click', onClick)
    observer.disconnect()
  }
}

webx.widget?.('consent', '[data-webx-consent-root]', consent)
