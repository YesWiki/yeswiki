const { createApp } = Vue

const { entry, extralang, fieldsnames, baselang } =
  document.getElementById('translate-entry').dataset

const app = createApp({
  data() {
    return {
      baseLang: baselang,
      selectedLanguage: baselang,
      extraLangsList: JSON.parse(extralang),
      entry: JSON.parse(entry),
      JsonExtraLang: '',
      extraLangValues: JSON.parse(entry).extra_lang,
      jsonEntry: entry,
      fieldsNames: JSON.parse(fieldsnames),
    }
  },
  watch: {
    extraLangValues: {
      handler() {
        this.JsonExtraLang =
          JSON.stringify(this.filterData(this.extraLangValues)) ?? ''
      },
      deep: true,
      immediate: true,
    },
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
    },
  },
})
app.mount('#translate-entry')
