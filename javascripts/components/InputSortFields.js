import InputMultiInput from './InputMultiInput.js'
import InputPageList from './InputPageList.js'

export default {
  mixins: [InputMultiInput],
  components: { InputPageList },
  methods: {
    parseNewValues(newValues) {
      this.elements = []
      const fields = newValues.sortfields ? newValues.sortfields.split(',') : []
      const titles = newValues.sortfieldstitles ? newValues.sortfieldstitles.split(',') : []
      for (let i = 0; i < fields.length; i++) {
        this.elements.push({
          field: fields[i],
          title: titles[i]
        })
      }
    },
    getValues() {
      return {
        sortfields: this.elements.map((g) => g.field).filter((e) => e != '').join(','),
        sortfieldstitles: this.elements.map((g) => g.title).filter((e) => e != '').join(',')
      }
    }
  },
  watch: {
    elements: {
      handler(newElements) {
        newElements.forEach((element) => {
          if (element.field && !element.title) {
            // Use the field as default title
            element.title = element.field
              .replace(/^bf_/, '')
              .replace(/_/g, ' ')
              .replace(/\b\w/g, (c) => c.toUpperCase())
          }
        })
        this.$emit('input', newElements)
      },
      deep: true
    }
  }
}
