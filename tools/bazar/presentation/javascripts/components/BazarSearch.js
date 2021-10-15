// TODO better list and translatable
var wordsToExcludeFromSearch = ['le', 'la', 'les', 'du', 'en', 'un', 'une']

export default {
  methods: {
    searchEntries(entries, search) {
      switch (this.params.search) {
        case "dynamic":
          return this.localSearch(entries, search)
        case "true":
          return this.distantSearch(entries, search)
        default:
          return entries
      }
    },
    // Search throught API
    distantSearch(entries, search) {
      let params = { ...this.params, ...{ q: search } }
      $.getJSON(wiki.url('?api/entries/bazarlist'), params, (data) => {
        let searchedIds = data.entries.map(entry => entry[0])
        this.searchedEntries = entries.filter(entry => searchedIds.includes(entry.id_fiche))
        this.filterEntries()
      })
      return entries
    },
    // Search with existing data in javascript
    localSearch(entries, search) {
      let words = search.split(' ')
                        .map(word => this.removeDiatrics(word))
                        .filter(word => word.length > 1 && !wordsToExcludeFromSearch.includes(word))
      let result = entries.filter(entry => {
        entry.searchScore = 0
        words.forEach(word => {
          this.params.searchfields.forEach(field => {
            let fieldValue = entry[field] ? entry[field] : ""
            if (Array.isArray(fieldValue)) fieldValue = fieldValue.join(' ')
            fieldValue = this.removeDiatrics(fieldValue)
            if (fieldValue && fieldValue.includes(word)) {
              entry.searchScore += field == 'bf_titre' ? 2 * word.length : word.length
            }
          })
        })
        return entry.searchScore > 0
      })

      result = result.sort((a, b) => (a.searchScore > b.searchScore) ? -1 : 1)
      return result
    },
    removeDiatrics(str) {
      return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase()
    }
  }
}