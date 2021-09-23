import Panel from './components/Panel.js'
import ModalEntry from './components/ModalEntry.js'

var wordsToExcludeFromSearch = ['le', 'la', 'les', 'du', 'en', 'un', 'une']
document.querySelectorAll(".bazar-list-dynamic-container").forEach(domElement =>{
  new Vue({
    el: domElement,
    components: { Panel, ModalEntry },
    data: {
      mounted: false, // when vue get initialized
      ready: false, // when ajax data have been retrieved
      params: {},

      filters: [],
      entries: [],     
      searchedEntries: [],
      filteredEntries: [],
      paginatedEntries: [],
      entriesToDisplay: [],

      currentPage: 0,
      pagination: 10,
      search: '',
      searchFormId: null, // wether to search for a particular form ID (only used when no form id is defined for the bazar list action)
      searchTimer: null // use ot debounce user input
    },
    computed: {
      computedFilters() {
        let result = {}
        for(const filterId in this.filters) {
          let checkedValues = this.filters[filterId].list.filter(option => option.checked)
                                                         .map(option => option.value)
          if (checkedValues.length > 0) result[filterId] = checkedValues
        }
        return result
      },
      filteredEntriesCount() {
        return this.filteredEntries.length
      },
      pages() {
        if (this.pagination <= 0) return []
        let pagesCount = Math.floor(this.filteredEntries.length / parseInt(this.pagination)) + 1
        let start = 0, end = pagesCount - 1        
        let pages = [this.currentPage - 2, this.currentPage - 1, this.currentPage, this.currentPage + 1, this.currentPage + 2]
        pages = pages.filter(page => page >= start && page <= end)
        if (!pages.includes(start)) {
          if (!pages.includes(start + 1)) pages.unshift('divider')
          pages.unshift(0)
        }
        if (!pages.includes(end)) {
          if (!pages.includes(end - 1)) pages.push('divider')
          pages.push(end)
        }
        return pages
      }
    },
    watch: {
      filteredEntriesCount() { this.currentPage = 0 },
      search() { 
        clearTimeout(this.searchTimer)
        this.searchTimer = setTimeout(() => this.calculateBaseEntries(), 350)
      },
      searchFormId() { this.calculateBaseEntries() },
      computedFilters() { this.filterEntries() },
      currentPage() { this.paginateEntries() },
      searchedEntries() { this.calculateFiltersCount() },
    },
    methods: {
      calculateBaseEntries() {
        let result = this.entries
        let needSearch = this.search && this.search.length > 2
        if (needSearch || this.searchFormId) {
          result = result.filter(entry => {
            // filter based on formId, when no form id is specified
            if (this.searchFormId && entry.id_typeannonce != this.searchFormId) return false
            if (needSearch) {
              entry.searchScore = 0
              let words = this.search.split(' ')
                                     .map(word => this.removeDiatrics(word))
                                     .filter(word => word.length > 1 && !wordsToExcludeFromSearch.includes(word))
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
            }
            return true
          })
          if (needSearch) result = result.sort((a, b) => (a.searchScore > b.searchScore) ? -1 : 1)
        }
        this.searchedEntries = result
        this.filterEntries()
      },
      filterEntries() {
        // Handles filters
        let result = this.searchedEntries
        for(const filterId in this.computedFilters) {
          result = result.filter(entry => {
            if (!entry[filterId]) return false
            return entry[filterId].split(',').some(value => {
              return this.computedFilters[filterId].includes(value)
            })
          })
        }
        this.filteredEntries = result
        this.paginateEntries()
      },
      paginateEntries() {
        let result = this.filteredEntries
        if (this.pagination > 0) {
          let start = this.pagination * this.currentPage
          result = result.slice(start, start + this.pagination)
        }
        this.paginatedEntries = result
        this.formatEntries()
      },
      formatEntries() {
        this.paginatedEntries.forEach(entry => {
          entry.color = this.colorIconValueFor(entry, this.params.colorfield, this.params.color)
          entry.icon  = this.colorIconValueFor(entry, this.params.iconfield, this.params.icon)
        })
        this.entriesToDisplay = this.paginatedEntries
      },
      calculateFiltersCount() {
        for(let fieldName in this.filters) {
          for (let option of this.filters[fieldName].list) {
            option.nb = this.searchedEntries.filter(entry => {
              let entryValues = entry[fieldName]
              if (!entryValues) return
              entryValues = entryValues.split(',')
              return entryValues.some(value => value == option.value)
            }).length
          }
        }
      },
      resetFilters() {
        for(let filterId in this.filters) {
          this.filters[filterId].list.forEach(option => option.checked = false)
        }
        this.search = ''
      },
      field(entry, field, fallbackField) {
        let mappedField = this.params.displayfields[field]
        return mappedField ? entry[mappedField] : entry[fallbackField]
      },
      getEntryRender(entry) {
        if (entry.html_render) return
        let fieldsToExclude = this.params.displayfields ? Object.values(this.params.displayfields) : []
        $.getJSON(`?api/entry/${entry.id_fiche}/view&excludeFields=${fieldsToExclude}`, function(data) {
          Vue.set(entry, 'html_render', data.html)
        })
      },
      openEntryModal(entry) {
        this.$refs.modal.displayEntry(entry)
      },
      colorIconValueFor(entry, field, mapping) {
        if (!entry[field]) return null
        let values = entry[field].split(',')
        // If some filters are checked, and the entry have multiple values, we display 
        // the value associated with the checked filter
        // TODO BazarListDynamic check with users if this is expected behaviour
        if (this.computedFilters[field]) values = values.filter(val => this.computedFilters[field].includes(val))
        return mapping[values[0]]
      },
      removeDiatrics(str) {
        return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase()
      }
    },
    mounted() {
      this.params = JSON.parse(this.$el.dataset.params)
      this.pagination = parseInt(this.params.pagination)
      this.mounted = true
      // Retrieve data asynchronoulsy
      $.getJSON('?api/entries/bazarlist', this.params, (data) => {
        // First display filters cause entries can be a bit long to load
        this.filters = data.filters || []        
        
        // Auto adjust some params depending on entries count
        if (data.entries.length > 50 && !this.pagination) this.pagination = 20 // Auto paginate if large numbers
        if (data.entries.length > 1000) this.params.cluster = true // Activate cluster for map mode
        
        setTimeout(() => {
          this.entries = data.entries.map(array => {
            let entry = { color: null, icon: null }
            // Transform array data into object using the fieldMapping
            for(let key in data.fieldMapping) {
              entry[data.fieldMapping[key]] = array[key]
            }
            
            return entry
          })
          this.calculateBaseEntries()
          this.ready = true
        }, 0)  
      })
      
    }
  })
})
