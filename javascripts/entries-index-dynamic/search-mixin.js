// TODO better list and translatable
import { parseKeywords, removeDiacritics, extractRegExp } from '../search.js'
import { mergeSearchParams, serializeSearchParams } from '../url.js'

const wordsToExcludeFromSearch = ['le', 'la', 'les', 'du', 'en', 'un', 'une']

export default {
  data() {
    return {
      isLoading: false,
      pendingRequest: null,
    }
  },
  methods: {
    searchEntries(entries, search) {
      switch (this.params.search) {
        case 'dynamic':
          return this.localSearch(entries, search)
        case 'true':
          return this.distantSearch(entries, search)
        default:
          return entries
      }
    },
    // Search through API
    distantSearch(entries, search) {
      if (this.isLoading) {
        // Do not send multiple request in parrallel, wait for the first one to finish
        this.pendingRequest = search
        return
      }
      this.isLoading = true
      this.pendingRequest = null

      const vParams = mergeSearchParams(
        this.params,
        { keywords: search },
        { returnMode: 'object', overrideKeywords: false, overrideQuery: false },
      )

      fetch(
        `${wiki.url('?api/entries/bazarlist')}&${serializeSearchParams(vParams)}`,
      )
        .then((response) => response.json())
        .then((data) => {
          this.isLoading = false
          const searchedIds = data.entries.map((entry) => entry[0])
          this.searchedEntries = entries.filter((entry) =>
            searchedIds.includes(entry.tag),
          )
          this.filterEntries()
          if (this.pendingRequest) {
            this.distantSearch(entries, this.pendingRequest)
          }
        })
      return this.searchedEntries
    },
    // Search with existing data in javascript
    localSearch(entries, search) {
      const vThis = this

      // Parse search as a keywords search string

      const vParsedKeywords = parseKeywords(search)

      vParsedKeywords.CNF = vParsedKeywords.CNF.map((pAnd) =>
        pAnd
          .map((pOr) => removeDiacritics(pOr))
          .filter(
            (pOr) => pOr.length > 2 && !wordsToExcludeFromSearch.includes(pOr),
          ),
      ).filter((pAnd) => pAnd.length > 0)

      vParsedKeywords.excludeds = vParsedKeywords.excludeds
        .map((pExcluded) => removeDiacritics(pExcluded))
        .filter(
          (pExcluded) =>
            pExcluded.length > 2 &&
            !wordsToExcludeFromSearch.includes(pExcluded),
        )

      let vResult = entries.filter((pEntry) => {
        pEntry.searchScore = 1

        let vMatchedAnds = 0
        let vAndsCount = 0

        vParsedKeywords.CNF.every((pAnd) => {
          let vMatchedOrs = 0
          let vAndScore = 0

          vAndsCount++

          pAnd.forEach((pOr) => {
            let vMatchedFields = 0
            let vOrScore = 0

            vThis.params.searchfields.forEach((pField) => {
              let vFieldValue = pEntry[pField] ? pEntry[pField] : ''

              if (Array.isArray(vFieldValue))
                vFieldValue = vFieldValue.join(' ')

              vFieldValue = removeDiacritics(vFieldValue)

              vFieldValue = vFieldValue.trim()

              const vRegExp = extractRegExp(pOr)

              if (vFieldValue) {
                const vMatches = vFieldValue.match(new RegExp(vRegExp, 'gi'))

                if (vMatches) {
                  vMatches.forEach((pMatch) => {
                    vOrScore +=
                      pField === 'title' || pField === 'bf_titre'
                        ? 2 * (pMatch.length + 1)
                        : pMatch.length + 1
                    vMatchedFields++
                  })
                }
              }
            })

            vOrScore *= vMatchedFields + 1

            vAndScore += vOrScore

            if (vMatchedFields > 0) vMatchedOrs++
          })

          if (vAndScore === 0) {
            pEntry.searchScore = 0
            return false
          }

          vAndScore *= vMatchedOrs + 1

          pEntry.searchScore += vAndScore

          if (vMatchedOrs > 0) vMatchedAnds++

          return true
        })

        if (vAndsCount > 0) {
          pEntry.searchScore *= vMatchedAnds / vAndsCount
        }

        vParsedKeywords.excludeds.forEach((pExcluded) => {
          vThis.params.searchfields.forEach((pField) => {
            let vFieldValue = pEntry[pField] ? pEntry[pField] : ''

            if (Array.isArray(vFieldValue)) vFieldValue = vFieldValue.join(' ')

            vFieldValue = removeDiacritics(vFieldValue)

            vFieldValue = vFieldValue.trim()

            const vRegExp = extractRegExp(pExcluded)

            if (vFieldValue) {
              // `vRegExp`, not `pExcluded`: the field value has been de-accented just above,
              // so the raw keyword misses every accented match -- and without `i`, every
              // difference of case too. Matches the included-keyword path.
              const vMatches = vFieldValue.match(new RegExp(vRegExp, 'gi'))

              if (vMatches) {
                pEntry.searchScore = 0
              }
            }
          })
        })

        return pEntry.searchScore > 0
      })

      vResult = vResult.sort((a, b) => (a.searchScore > b.searchScore ? -1 : 1))
      return vResult
    },
  },
}
