new Vue({
  el: '.translate-form',
  data: {
    selected_language: 'default',
    extra_langs_list: ['en'],
    form: {},
    jsonForm: '',
    JsonExtraLang: '',
    extra_lang_values: {},
    fieldTypes: ['label', 'helper', 'default']
  },
  mounted() {
    this.form = JSON.parse(this.$el.dataset.form)
    this.extra_langs_list = JSON.parse(this.$el.dataset.extra_lang)
    this.form.fields ??= {}
    const langsValues = this.form.__extra_lang ?? {}

    for (const lang of this.extra_langs_list) {
      langsValues[lang] ??= {}
      langsValues[lang].title ??= ''
      langsValues[lang].fields ??= {}
      for (const entry in this.form.fields) {
        const value = this.form.fields[entry]
        langsValues[lang].fields[entry] ??= {}
        if (value.label) {
          langsValues[lang].fields[entry].label ??= ''
        }
        if (value.helper) {
          langsValues[lang].fields[entry].helper ??= ''
        }
        if (value.default) {
          langsValues[lang].fields[entry].default ??= ''
        }
      }
    }
    this.extra_lang_values = langsValues
    this.jsonForm = JSON.stringify(this.form)
  },
  watch: {
    extra_lang_values: {
      handler() {
        this.JsonExtraLang = JSON.stringify(this.filterData(this.extra_lang_values))
      },
      deep: true
    }
  },
  methods: {
    onSubmit(data) {

    },
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
    removeEmptyValues(values) {
      const extraLangValues = values
      for (const lang in extraLangValues) {
        if (extraLangValues[lang].title === '') {
          delete extraLangValues[lang].title
        }
        for (const field in extraLangValues[lang].fields) {
          for (const fieldType in extraLangValues[lang].fields[field]) {
            if (!extraLangValues[lang].fields[field][fieldType]) {
              delete extraLangValues[lang].fields[field][fieldType]
            }
          }
          if (Object.keys(extraLangValues[lang].fields[field]).length === 0) {
            delete extraLangValues[lang].fields[field]
          }
        }
        if (!Object.keys(extraLangValues[lang].fields).length === 0) {
          delete extraLangValues[lang]
        }
      }
      return extraLangValues
    }
  }
})
