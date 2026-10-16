import InputMultiInput from './InputMultiInput.js'

export default {
  mixins: [InputMultiInput],
  methods: {
    parseNewValues(newValues) {
      if (newValues.labels) {
        this.elements = []
        const labels = newValues.labels.split(',')
        const images = newValues.images ? newValues.images.split(',') : []
        for (let i = 0; i < labels.length; i++) {
          this.elements.push({
            label: labels[i],
            image: images[i] ?? '',
          })
        }
      }
    },
    getValues() {
      const kept = this.elements.filter((element) => element.label !== '')
      return {
        labels: kept.map((element) => element.label).join(','),
        images: kept.some((element) => element.image)
          ? kept.map((element) => element.image).join(',')
          : '',
      }
    },
  },
}
