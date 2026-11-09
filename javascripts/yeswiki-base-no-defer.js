function _t(message, replacements = {}) {
  let translation =
    typeof wiki !== 'undefined' && wiki.lang !== undefined
      ? (wiki.lang[message] ?? null)
      : null
  if (!translation) {
    translation = message
    if (typeof wiki !== 'undefined' && wiki.isDebugEnabled) {
      console.warn(
        `Translation was not found in wiki.lang for "${message}", (wiki.locale = ${wiki.locale})`,
      )
    }
  }
  for (const key in replacements) {
    while (translation.includes(`{${key}}`)) {
      translation = translation.replace(`{${key}}`, replacements[key])
    }
  }
  return translation
}

var wiki = {
  ...(typeof wiki !== 'undefined' ? wiki : null),
  ...{
    /** Moves handler segments such as /iframe before the query parameters, e.g. ?Tag&lang=en/iframe becomes ?Tag/iframe&lang=en. */
    reorderUrl(url) {
      const cut = url.search(/[&#]/)
      if (cut === -1) return url
      const parts = url.slice(cut).split(/([&#/][^&#/]*)/).filter(Boolean)
      return (
        url.slice(0, cut) +
        parts.filter((part) => part.startsWith('/')).join('') +
        parts.filter((part) => part.startsWith('&')).join('') +
        parts.filter((part) => part.startsWith('#')).join('')
      )
    },
    url(url, params = {}) {
      let result = wiki.baseUrl + url
      result = result.replace('??', '?')
      const stringParams = []
      for (const key in params) {
        stringParams.push(`${key}=${encodeURIComponent(params[key])}`)
      }
      if (stringParams.length) {
        result += result.includes('?') ? '&' : '?'
        result += stringParams.join('&')
      }
      return result
    },
    cssVar(varName) {
      return getComputedStyle(document.documentElement).getPropertyValue(
        varName,
      )
    },
  },
}

window._t = _t
