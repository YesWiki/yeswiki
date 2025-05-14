new Vue({
  el: '.translate-entry',
  data: {
    selected_language: 'default',
    extra_langs_list: ['en'],
    entry: {},
    jsonEntry: '',
    JsonExtraLang: '',
    extra_lang_values: {},
    ignore_fields: [],
    display_fields: {}
  },
  mounted() {
    this.entry = JSON.parse(this.$el.dataset.entry)
    this.extra_langs_list = JSON.parse(this.$el.dataset.extra_lang)
    this.ignore_fields = JSON.parse(this.$el.dataset.ignore_fields)
    for (const el in this.entry) {
      if (!this.ignore_fields.includes(el)) {
        this.display_fields[el] = this.entry[el]
      }
    }
    const langsValues = this.entry.__extra_lang ?? {}

    for (const lang of this.extra_langs_list) {
      langsValues[lang] ??= {}
      for (const el in this.entry) {
        langsValues[lang][el] ??= ''
      }
    }
    this.extra_lang_values = langsValues
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
