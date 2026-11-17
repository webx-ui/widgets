/**
 * Whether the page is drawn in a preview of the panel: `data-webx-preview` on the `<iframe>` it
 * stands in (there from the first script, before the panel can reach the page) or on its own
 * `<html>` (written by the panel once it has). Such a frame is as tall as its page and never
 * scrolls, so what moves with the scroll has nothing to move with — and a glide or a parallax left
 * running there only fights the frame.
 */
export function inPreview() {
  if (document.documentElement.hasAttribute('data-webx-preview')) return true
  try {
    return window.frameElement?.hasAttribute('data-webx-preview') ?? false
  } catch {
    // A frame of another origin: not the panel's.
    return false
  }
}
