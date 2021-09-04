document.querySelectorAll(".bazar-list-dynamic-container").forEach(domElement =>{
  new Vue({
    el: domElement,
    delimiters: ['[[', ']]'],
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
            return this.computedFilters[filterId].includes(entry[filterId])
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
        return Array.from(Array(pagesCount).keys());
      }
    },
    watch: {
      perPages() {
        this.currentPage = 0
      }
    },
    methods: {
      filterDomId(key) {
        return `accordion_${key}_${this._uid}`
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
