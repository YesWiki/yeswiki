import { mergeSearchParams } from './url.js'

/* update all export buttons in a page with the given search parameters */

export function updateExportLinks(pSearchParams) {
  document
    .querySelectorAll('.export-links > a')
    .forEach((pLink) => {
      const vOldHREF = pLink.getAttribute('data-href') // Get the original href

      let vNewHREF

      if (vOldHREF.trim() === '') {
        console.error('Invalid URL provided.')
      } else {
        const vNewURL = new URL(vOldHREF)

        const vAllowedProtocols = ['http:', 'https:']
        if (!vAllowedProtocols.includes(vNewURL.protocol)) {
          console.error('Blocked unsafe URL protocol in data-href:', vNewURL.protocol)
          return
        }

        const vHandler = vNewURL.searchParams.keys().next()
        let vHandlerValue = vHandler.value

        if (vHandler) vNewURL.searchParams.delete(vHandlerValue)
        else vHandlerValue = ''

        const vParams = mergeSearchParams(vNewURL.searchParams.toString(), pSearchParams, { returnMode: 'string', overrideKeywords: false, overrideQuery: false })

        const finalURL = new URL(vNewURL.href)
        const searchParams = new URLSearchParams(vParams || '')
        if (vHandlerValue) {
          const [key, value] = vHandlerValue.split('=')
          if (value !== undefined) {
            searchParams.set(key, value)
          } else {
            finalURL.search = vHandlerValue + (vParams ? `&${vParams}` : '')
          }
        }
        finalURL.search = searchParams.toString()
        pLink.href = finalURL.toString()
      }
    })
}
