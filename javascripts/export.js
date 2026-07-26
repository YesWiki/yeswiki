import { mergeSearchParams } from './url.js'

export function updateExportLinks(pSearchParams) {
  document
    .querySelectorAll('.export-links > a')
    .forEach((pLink) => {
      const vOldHREF = pLink.getAttribute('data-href')

      if (!vOldHREF || vOldHREF.trim() === '') {
        console.error('Invalid URL provided.')
        return
      }

      try {
        const vURL = new URL(vOldHREF)
        const vAllowedProtocols = ['http:', 'https:']
        if (!vAllowedProtocols.includes(vURL.protocol)) {
          console.error('Blocked unsafe URL protocol:', vURL.protocol)
          return
        }

        const vRawSearch = vURL.search.substring(1)
        const vSegments = vRawSearch.split('&')
        const vHandler = vSegments[0]
        const vExistingParamsStr = vSegments.slice(1).join('&')
        const vMergedParams = mergeSearchParams(
          vExistingParamsStr,
          pSearchParams,
          { returnMode: 'string', overrideKeywords: false, overrideQuery: false }
        )

        let vNewSearch = `?${vHandler}`
        if (vMergedParams) {
          vNewSearch += `&${vMergedParams}`
        }

        vURL.search = vNewSearch
        pLink.href = vURL.toString()

      } catch (e) {
        console.error('Error processing URL:', e)
      }
    })
}
