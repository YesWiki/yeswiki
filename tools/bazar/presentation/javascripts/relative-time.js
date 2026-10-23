const UNITS = [
  ['year', 31536000],
  ['month', 2592000],
  ['week', 604800],
  ['day', 86400],
  ['hour', 3600],
  ['minute', 60],
]
const MAX_RELATIVE_AGE = 30 * 86400

/** Phrases an elapsed time the way people say it, in the page's language. */
function relativeLabel(date, now, lang) {
  const seconds = Math.round((date.getTime() - now) / 1000)
  if (Math.abs(seconds) > MAX_RELATIVE_AGE) {
    return date.toLocaleDateString(lang, {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
  }
  const format = new Intl.RelativeTimeFormat(lang, { numeric: 'always' })
  const [unit, size] = UNITS.find(
    ([, length]) => Math.abs(seconds) >= length,
  ) || ['minute', 60]
  const amount = Math.trunc(seconds / size) || -1
  const label = format.format(amount, unit)
  return label.charAt(0).toLocaleUpperCase(lang) + label.slice(1)
}

/** Rewrites every relative-time element under a node. */
function renderRelativeTimes(root = document) {
  const lang = document.documentElement.lang || navigator.language
  const now = Date.now()
  root
    .querySelectorAll('time.yw-relative-time[datetime]')
    .forEach((element) => {
      const date = new Date(element.getAttribute('datetime'))
      if (Number.isNaN(date.getTime())) return
      if (!element.title) element.title = element.textContent.trim()
      element.textContent = relativeLabel(date, now, lang)
    })
}

renderRelativeTimes()
document.addEventListener('htmx:load', (event) =>
  renderRelativeTimes(event.target),
)
