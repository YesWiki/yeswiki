export default {
  props : ['bazarcalendar'],
  data () {
    return {
      href: ""
    };
  },
  methods: {
    updateHref: function(e){
      let params = this.bazarcalendar.params;
      let baseUrlPath = `api/entries/ical`;
      let formId = params.id;
      let formIdParams = params.id.indexOf(",") > -1 ? {} : {id:formId};

      let dateFilterParams = params.datefilter != undefined 
        ? {
          datefilter: params.datefilter
        }
        : {};

      let entriesParams = {};
      let filters = this.bazarcalendar.$root.computedFilters;
      let search = this.bazarcalendar.$root.search;
      if (search.length > 0 || Object.keys(filters).filter(filterKey => filters[filterKey].length > 0).length > 0 || (params.id.indexOf(",") > -1)){
        // filter on entries
        let entries = this.bazarcalendar.entries;
        entriesParams = {
          query: "id_fiche="+entries.map(entry => entry['id_fiche']).join(',')
        };
      }
      let urlParams = {
        ...formIdParams,
        ...dateFilterParams,
        ...entriesParams
      };
      this.href = wiki.url(baseUrlPath,urlParams);
      $(this.$el).attr('href',this.href);
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