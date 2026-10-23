const { createApp } = Vue

const { jsonform, extralang, defaultlanguage } = document.getElementById('translate-form').dataset

const app = createApp({
  data() {
    const fieldTypes = ['label', 'helper', 'default']
    const extraLangsList = JSON.parse(extralang)
    const form = JSON.parse(jsonform)
    const extraLangsValues = form.extralang ?? {}

    for (const lang of Object.values(extraLangsList)) {
      extraLangsValues[lang] ??= {}
      extraLangsValues[lang].title ??= ''
      extraLangsValues[lang].description ??= ''
      extraLangsValues[lang].fields ??= {}
      for (const entry in form.fields) {
        extraLangsValues[lang].fields[entry] ??= {}
        for (const prop of fieldTypes) {
          if (form.fields[entry][prop]) {
            extraLangsValues[lang].fields[entry][prop] ??= ''
          }
        }
      }
    }
    return {
      selectedLanguage: defaultlanguage,
      extraLangsList,
      form,
      jsonForm: jsonform,
      jsonExtraLangs: '',
      fieldTypes,
      extraLangsValues
    }
  },
  watch: {
    extraLangsValues: {
      handler() {
        this.jsonExtraLangs = JSON.stringify(this.filterData(this.extraLangsValues))
      },
      deep: true
    }
  },
  methods: {
    onSubmit(data) {
      return data
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
    }
  }
})
app.mount('#translate-form')
