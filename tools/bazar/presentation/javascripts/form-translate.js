const { createApp } = Vue

const { jsonform, __translation, defaultlanguage } =
  document.getElementById('translate-form').dataset
const { captions } = document.querySelector(
  '#translate-form .nodes-container',
).dataset

const app = createApp({
  data() {
    const extraLangsList = JSON.parse(__translation)
    const form = JSON.parse(jsonform)
    const extraLangsValues = form.__translation ?? {}

    for (const lang of Object.values(extraLangsList)) {
      extraLangsValues[lang] ??= {}
      extraLangsValues[lang].title ??= ''
      extraLangsValues[lang].description ??= ''
      extraLangsValues[lang].fields ??= {}
      for (const entry in form.fields) {
        extraLangsValues[lang].fields[entry] ??= {}
        for (const attribute in form.fields[entry].attributes) {
          extraLangsValues[lang].fields[entry][attribute] ??= ''
        }
      }
    }
    return {
      selectedLanguage: defaultlanguage,
      extraLangsList,
      form,
      jsonForm: jsonform,
      jsonExtraLangs: '',
      captions: JSON.parse(captions),
      extraLangsValues,
    }
  },
  watch: {
    extraLangsValues: {
      handler() {
        this.jsonExtraLangs = JSON.stringify(
          this.filterData(this.extraLangsValues),
        )
      },
      deep: true,
    },
  },
  methods: {
    onSubmit(data) {
      return data
    },
    multiline(attribute) {
      return !['label', 'default'].includes(attribute)
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
  },
})
app.mount('#translate-form')
