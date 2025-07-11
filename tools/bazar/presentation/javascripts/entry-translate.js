new Vue({
  el: '.translate-entry',
  data: {
    selected_language: 'default',
    extra_langs_list: [],
    entry: {},
    jsonEntry: '',
    JsonExtraLang: '',
    extra_lang_values: {},
    fields_names: {}
  },
  mounted() {
    this.entry = JSON.parse(this.$el.dataset.entry)
    this.extra_langs_list = JSON.parse(this.$el.dataset.extra_lang)
    this.fields_names = JSON.parse(this.$el.dataset.fields_names)

    this.extra_lang_values = this.entry.__extra_lang
    this.jsonEntry = JSON.stringify(this.entry)
  },
  watch: {
    extra_lang_values: {
      handler() {
        this.JsonExtraLang = JSON.stringify(this.filterData(this.extra_lang_values)) ?? ''
      },
      deep: true
    }
  },
  methods: {
    filterData(value) {
      if (typeof value === 'string' || value instanceof String) {
        if (value !== '') {
          return value
        }
        return null
      }
      const data = {}
      for (const [key, child] of Object.entries(value)) {
        const childData = this.filterData(child)
        if (childData) {
          data[key] = childData
        }
      }
      if (Object.keys(data).length === 0) {
        return null
      }
      return data
    }
  }
})
