document.querySelectorAll(".bazar-list.dynamic").forEach(domElement =>{
  new Vue({
    el: domElement,
    delimiters: ['${', '}'],
    data: {
      entries: [],
      params: {},
      currentPage: 0,
      perPage: 10
    },
    computed: {
      filteredEntries() {
        // Handles filters and search
        return this.entries
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
    mounted() {
      this.entries = JSON.parse(this.$el.dataset.entries)
      this.params = JSON.parse(this.$el.dataset.params)
      this.perPage = this.params.pagination
    }
  })
})
