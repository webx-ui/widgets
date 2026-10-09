/*
 * The pointer's corridor to an open panel: a pointer that crosses a neighbour on its way to the
 * panel — diagonally, the shortest way — must not open the neighbour. The header's dropdowns
 * and `<x-webx-dropdown>` both ask it.
 */

/** Feed it every mouse move while a panel is open; it keeps the last few points. */
export function trail() {
  let points = []
  return {
    push(event) {
      points.push({ x: event.clientX, y: event.clientY })
      if (points.length > 4) points = points.slice(-4)
    },
    clear() {
      points = []
    },
    /** Is the pointer inside the triangle from where it was a moment ago to the panel's top corners? */
    heading(panel) {
      if (!panel || points.length < 2) return false
      const [from, to] = [points[0], points[points.length - 1]]
      const box = panel.getBoundingClientRect()
      // A panel above its trigger is reached through its bottom edge.
      const y = box.bottom <= from.y ? box.bottom : box.top
      const a = { x: box.left, y }
      const b = { x: box.right, y }
      const side = (p, q, r) => (q.x - p.x) * (r.y - p.y) - (q.y - p.y) * (r.x - p.x)
      const d1 = side(from, a, to)
      const d2 = side(a, b, to)
      const d3 = side(b, from, to)
      const negative = d1 < 0 || d2 < 0 || d3 < 0
      const positive = d1 > 0 || d2 > 0 || d3 > 0
      return !(negative && positive)
    },
  }
}
