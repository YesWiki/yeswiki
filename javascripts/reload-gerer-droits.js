// This is the file's public surface: a global other scripts call, declared in
// eslint.config.mjs. ESLint sees each file alone, so the definition reads as unused.
// eslint-disable-next-line no-unused-vars
function reloadGererDroits(elem) {
  const value = elem.value
  const urlwindow = window.location.toString()
  const urlSplitted = urlwindow.split('?')
  const baseUrl = urlSplitted[0]
  const paramsSplitted = urlSplitted.length > 1 ? urlSplitted[1].split('&') : []
  let i
  let params = ''
  for (i = 0; i < paramsSplitted.length; i++) {
    if (paramsSplitted[i].slice(0, 7) !== 'filter=') {
      if (i > 0) {
        params = `${params}&`
      }
      params += paramsSplitted[i]
    }
  }
  if (value !== '') {
    if (params.length > 0) {
      params = `${params}&`
    }
    params = `${params}filter=${value}`
  }
  window.location = baseUrl + (params.length > 0 ? `?${params}` : '')
}
