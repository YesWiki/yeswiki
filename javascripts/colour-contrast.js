// WCAG contrast between two CSS colours, and which ink reads best on a fill, as PresetService::inkOn() does in PHP (end-users 09).
let probe = null

/** The red, green and blue of any CSS colour, as the browser resolves it, or null. */
export function rgbOf(value) {
  if (!probe) {
    probe = document.createElement('span')
    probe.setAttribute('aria-hidden', 'true')
    probe.style.display = 'none'
    document.body.appendChild(probe)
  }
  probe.style.color = ''
  probe.style.color = String(value ?? '').trim()
  const parts = getComputedStyle(probe).color.match(/[\d.]+/g)
  if (!parts || parts.length < 3) return null
  return parts.slice(0, 3).map(Number)
}

/** WCAG 2.1 relative luminance: sRGB linearised, then weighted. */
function luminance([r, g, b]) {
  const channel = (value) => {
    const v = value / 255
    return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
  }
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
}

/** The WCAG ratio between two colours: 1 (identical) to 21 (black on white), null when one does not resolve. */
export function contrastRatio(a, b) {
  const first = rgbOf(a)
  const second = rgbOf(b)
  if (!first || !second) return null
  const light = Math.max(luminance(first), luminance(second))
  const dark = Math.min(luminance(first), luminance(second))
  return (light + 0.05) / (dark + 0.05)
}

/** Whichever of the theme's two inks contrasts more with that fill, null when there is no fill. */
export function inkOn(fill) {
  if (!fill) return null
  const root = getComputedStyle(document.documentElement)
  const onLight = root.getPropertyValue('--yw-ink-on-light').trim() || '#000000'
  const onDark = root.getPropertyValue('--yw-ink-on-dark').trim() || '#ffffff'
  return (contrastRatio(fill, onLight) ?? 0) >=
    (contrastRatio(fill, onDark) ?? 0)
    ? onLight
    : onDark
}
