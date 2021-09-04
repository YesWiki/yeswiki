document.querySelectorAll(".bazar-list-dynamic-container").forEach(domElement =>{
  new Vue({
    el: domElement,
    data: {
      entries: [],
      params: {},
      filters: [],
      currentPage: 0,
      perPage: 10
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
      filteredEntries() {
        // Handles filters and search
        let result = this.entries
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
      }
    },
    mounted() {
      this.entries = JSON.parse(this.$el.dataset.entries)
      this.params = JSON.parse(this.$el.dataset.params)
      this.filters = JSON.parse(this.$el.dataset.filters)
      this.perPage = this.params.pagination
    }
  })
})
