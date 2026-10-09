/*
 * `data-webx-tabs` on a container of `[data-webx-tab]` panels. Each panel's label is the value of
 * its `data-webx-tab`, or else its first heading — which is what a visitor without JavaScript
 * reads above the panel. The runtime hides those headings, puts a tab list on top and wires it
 * the way WAI-ARIA describes: arrows move between tabs and select them, Home and End jump to the
 * ends, only the selected tab is in the Tab order.
 *
 * The first panel is selected, unless the address points into another one or one is marked
 * `data-webx-tab-selected`.
 */

import { idOf, listeners } from '../core.js'

const HEADING = ':scope > h1, :scope > h2, :scope > h3, :scope > h4, :scope > h5, :scope > h6'

export function tabs(root) {
  const panels = Array.from(root.children).filter((el) => el.hasAttribute('data-webx-tab'))
  if (panels.length === 0) return

  const on = listeners()
  const list = document.createElement('div')
  const hidden = []

  list.className = 'webx-tabs__list'
  list.setAttribute('role', 'tablist')
  const label = root.getAttribute('data-webx-tabs-label')
  if (label) list.setAttribute('aria-label', label)

  const buttons = panels.map((panel) => {
    const heading = panel.querySelector(HEADING)
    const text = panel.getAttribute('data-webx-tab') || heading?.textContent?.trim() || ''
    if (heading && !heading.hidden) {
      heading.hidden = true
      hidden.push(heading)
    }

    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'webx-tabs__tab'
    button.textContent = text
    button.setAttribute('role', 'tab')
    button.id = `${idOf(panel, 'webx-tab')}-tab`
    button.setAttribute('aria-controls', panel.id)
    panel.classList.add('webx-tabs__panel')
    panel.setAttribute('role', 'tabpanel')
    panel.setAttribute('aria-labelledby', button.id)
    panel.tabIndex = 0
    list.append(button)
    return button
  })

  const select = (index, focus = false) => {
    buttons.forEach((button, i) => {
      const selected = i === index
      button.setAttribute('aria-selected', String(selected))
      button.tabIndex = selected ? 0 : -1
      button.classList.toggle('is-selected', selected)
      panels[i].hidden = !selected
    })
    if (focus) buttons[index].focus()
  }

  const anchor = location.hash
    ? document.getElementById(decodeURIComponent(location.hash.slice(1)))
    : null
  const target = anchor ? panels.findIndex((panel) => panel.contains(anchor)) : -1
  const marked = panels.findIndex((panel) => panel.hasAttribute('data-webx-tab-selected'))
  select(target >= 0 ? target : Math.max(0, marked))

  on(list, 'click', (event) => {
    const index = buttons.indexOf(
      event.target instanceof Element ? event.target.closest('[role="tab"]') : null,
    )
    if (index >= 0) select(index)
  })
  on(list, 'keydown', (event) => {
    const current = buttons.indexOf(document.activeElement)
    if (current < 0) return
    const last = buttons.length - 1
    const next = {
      ArrowRight: current === last ? 0 : current + 1,
      ArrowLeft: current === 0 ? last : current - 1,
      Home: 0,
      End: last,
    }[event.key]
    if (next === undefined) return
    event.preventDefault()
    select(next, true)
  })

  root.classList.add('webx-tabs', 'is-enhanced')
  root.prepend(list)

  return () => {
    on.off()
    list.remove()
    root.classList.remove('is-enhanced')
    hidden.forEach((heading) => (heading.hidden = false))
    for (const panel of panels) {
      panel.hidden = false
      panel.removeAttribute('role')
      panel.removeAttribute('aria-labelledby')
      panel.removeAttribute('tabindex')
    }
  }
}
