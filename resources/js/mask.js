/*
 * The phone mask (spec §17.5). Its own file, claimed by `<x-webx-field type="tel" mask>`.
 *
 * International: `+`, the country code, the rest in groups of digits — `+48 512 345 678`,
 * `+1 212 555 0123`. No library and no list of countries: the length of a country code is told by
 * its first digits (ITU-T E.164 — 1 and 7 alone, a short list of two, three for the rest).
 *
 * It never stands in the way: no key is refused, so a paste, an autofill and a phone's keyboard
 * all arrive as they are, and the value is put in order after them. A number typed without a `+`
 * into an empty field gets one; a pasted or filled-in one without it is someone's local number
 * and is left alone — the server's rules decide.
 */

const webx = (window.webx ??= {})

const TWO = new Set(
  '20 27 30 31 32 33 34 36 39 40 41 43 44 45 46 47 48 49 51 52 53 54 55 56 57 58 60 61 62 63 64 65 66 81 82 84 86 90 91 92 93 94 95 98'.split(
    ' ',
  ),
)

/** E.164: fifteen digits at most, the country code among them. */
const MOST = 15

/** `+485123456789` → `+48 512 345 678 9`… in groups of three, never a lone digit at the end. */
export function format(value) {
  const raw = String(value ?? '')
  const trimmed = raw.trimStart()
  if (!trimmed.startsWith('+') && !trimmed.startsWith('00')) return raw

  const digits = trimmed.replace(/^00/, '').replace(/\D/g, '').slice(0, MOST)
  if (!digits) return '+'

  const code = '17'.includes(digits[0]) ? 1 : TWO.has(digits.slice(0, 2)) ? 2 : 3
  const groups = digits.slice(code).match(/\d{1,3}/g) ?? []
  if (groups.length > 1 && groups[groups.length - 1].length === 1) {
    const last = groups.pop()
    groups[groups.length - 1] += last
  }
  return ['+' + digits.slice(0, code), ...groups].join(' ')
}

const digitsIn = (text) => text.replace(/\D/g, '').length

/** Where the caret goes after `count` digits of the formatted value. */
function caretAfter(text, count) {
  if (count <= 0) return text.startsWith('+') ? 1 : 0
  let seen = 0
  for (let i = 0; i < text.length; i++) {
    if (/\d/.test(text[i]) && ++seen === count) return i + 1
  }
  return text.length
}

export function mask(input) {
  if (!(input instanceof HTMLInputElement)) return undefined
  let before = input.value

  const apply = (event) => {
    let value = input.value
    let caret = input.selectionStart ?? value.length
    const kind = event?.inputType ?? ''

    // A digit typed into an empty field starts an international number.
    if (kind === 'insertText' && before === '' && /^\d/.test(value)) {
      value = '+' + value
      caret += 1
    }

    // Backspace over a space would only put it back: take the digit before it instead.
    if (kind.startsWith('delete') && digitsIn(value) === digitsIn(before) && value !== before) {
      const at = kind === 'deleteContentForward' ? caret : caret - 1
      const index =
        kind === 'deleteContentForward'
          ? value.slice(at).search(/\d/) + at
          : value.slice(0, at + 1).search(/\d(?=\D*$)/)
      if (index >= 0 && /\d/.test(value[index] ?? '')) {
        value = value.slice(0, index) + value.slice(index + 1)
        caret = index
      }
    }

    const count = digitsIn(value.slice(0, caret)) - (/^\s*00/.test(value) ? 2 : 0)
    const next = format(value)
    before = next
    if (next === input.value) return
    input.value = next
    if (document.activeElement === input) {
      const at = caretAfter(next, count)
      input.setSelectionRange(at, at)
    }
  }

  input.addEventListener('input', apply)
  // An autofill some browsers report as change only; a value the page came back with.
  input.addEventListener('change', apply)
  apply()

  return () => {
    input.removeEventListener('input', apply)
    input.removeEventListener('change', apply)
  }
}

webx.widget?.('mask', 'input[data-webx-mask="phone"]', mask)
