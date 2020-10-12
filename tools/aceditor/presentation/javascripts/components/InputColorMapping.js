import InputMultiInput from './InputMultiInput.js'

export default {
  mixins: [InputMultiInput],
  mounted() {
    this.addElement()
  },
  methods: {
    parseNewValues(newValues) {
      if (newValues.color) {
        this.elements = []
        newValues.color.split(',').forEach(el => {
          this.elements.push({color: el.split('=')[0], id: el.split('=')[1]})
        })
      }
    },
    getValues() {
      return {
        color: this.elements.filter(m => m.id && m.color).map(m => `${m.color}=${m.id}`).join(',')
      }
    }
  }
};
