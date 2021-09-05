import Panel from './components/Panel.js'

document.querySelectorAll(".bazar-list-dynamic-container").forEach(domElement =>{
  new Vue({
    el: domElement,
    components: { Panel },
    data: {
      entries: [],
      params: {},
      filters: [],
      currentPage: 0,
      perPage: 10,
      search: '',
      searchFormId: null // wether to search for a particular form ID (only used when no form id is defined for the bazar list action)
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
      searchedEntries() {
        let result = this.entries
        if (this.search || this.searchFormId) {
          result = result.filter(entry => {
            if (this.searchFormId && entry.id_typeannonce != this.searchFormId) return false
            if (this.search) {
              // TODO BazarListDynamic improve search : search each word separatly, search dans list.. ou utiliser l'API?
              return this.removeDiatrics(entry.bf_titre).includes(this.removeDiatrics(this.search))
            }
            return true
          })
        }
        return result
      },
      filteredEntries() {
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
        return result
      },
      paginatedEntries() {
        let result = this.filteredEntries
        if (this.perPage) {
          let start = this.perPage * this.currentPage
          result = result.slice(start, start + this.perPage)
        }
        return result
      },
      entriesToDisplay() {
        // calculate color and icon
        this.paginatedEntries.forEach(entry => {
          entry.color = this.valueFrom(entry, this.params.colorfield, this.params.color)
          entry.icon  = this.valueFrom(entry, this.params.iconfield, this.params.icon)
          return entry
        })
        return this.paginatedEntries
      },
      pages() {
        if (!this.perPage) return []
        let pagesCount = Math.floor(this.filteredEntries.length / parseInt(this.perPage)) + 1
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
      perPages() {
        this.currentPage = 0
      }
    },
    methods: {
      filterDomId(key) {
        return `accordion_filter_${key}_${this._uid}`
      },
      entryDomId(entry) {
        return `accordion_entry_${entry.id_fiche}_${this._uid}`
      },
      resetFilters() {
        for(let filterId in this.filters) {
          this.filters[filterId].list.forEach(option => option.checked = false)
        }
      },
      getEntryRender(entry) {
        if (entry.html_render) return
        $.getJSON(`?api/entries/html/${entry.id_fiche}`, function(data) {
          Vue.set(entry, 'html_render', data[entry.id_fiche].html_output)
        })
      },
      valueFrom(entry, field, mapping) {
        if (!entry[field]) return null
        let values = entry[field].split(',')
        // If some filters are checked, and the entry have multiple values, we will shall display 
        // the value associated with the checked filter
        // TODO BazarListDynamic check with users if this is expected behaviour
        // also check if we should display icon inside the filter itself
        if (this.computedFilters[field]) values = values.filter(val => this.computedFilters[field].includes(val))
        return mapping[values[0]]
      },
      removeDiatrics(str) {
        return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase()
      }
    },
    mounted() {
      this.entries = JSON.parse(this.$el.dataset.entries).map(entry => {
        // initialize some fields so they get reactive
        entry.color = null
        entry.icon = null
        return entry
      })
      this.params = JSON.parse(this.$el.dataset.params)
      this.filters = JSON.parse(this.$el.dataset.filters)
      this.perPage = this.params.pagination
    }
  })
})
