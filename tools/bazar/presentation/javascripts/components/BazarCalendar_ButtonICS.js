export default {
  props: ['bazarcalendar'],
  data() {
    return { href: '' }
  },
  methods: {
    updateHref(e) {
      const { params } = this.bazarcalendar
      const baseUrlPath = 'api/entries/ical'
      const formId = params.id
      const formIdParams = params.id.indexOf(',') > -1 ? {} : { id: formId }

      const dateFilterParams = params.datefilter != undefined
        ? { datefilter: params.datefilter }
        : {}

      let entriesParams = {}
      const filters = this.bazarcalendar.$root.computedFilters
      const { search } = this.bazarcalendar.$root
      if (search.length > 0 || Object.keys(filters).filter((filterKey) => filters[filterKey].length > 0).length > 0 || (params.id.indexOf(',') > -1)) {
        // filter on entries
        const { entries } = this.bazarcalendar
        entriesParams = { query: `id_fiche=${entries.map((entry) => entry.id_fiche).join(',')}` }
      }
      const urlParams = {
        ...formIdParams,
        ...dateFilterParams,
        ...entriesParams
      }
      this.href = wiki.url(baseUrlPath, urlParams)
      $(this.$el).attr('href', this.href)
    }
  },
  computed: {
    title() {
      return _t('BAZ_CALENDAR_EXPORT_BUTTON_TITLE')
    }
  },
  template: `
    <a class="btn btn-primary btn-xs ical-export-button" target="blank" :href="this.href" :title="title" @mouseover="updateHref">
      <i class="fa fa-plus"></i>&nbsp;<i class="fa fa-calendar"></i>
    </a>`
}
