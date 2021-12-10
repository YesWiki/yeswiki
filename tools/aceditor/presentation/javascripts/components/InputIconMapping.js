import InputMultiInput from './InputMultiInput.js'

export default {
  mixins: [InputMultiInput],
  methods: {
    parseNewValues(newValues) {
      if (newValues.icon) {
        this.elements = []
        newValues.icon.split(',').forEach(el => {
          this.elements.push({icon: el.split('=')[0], id: el.split('=')[1]})
        })
      }
    },
    getValues() {
      return {
        icon: this.elements.filter(m => m.id && m.icon).map(m => `${m.icon}=${m.id}`).join(',')
      }
    }
  }
};
