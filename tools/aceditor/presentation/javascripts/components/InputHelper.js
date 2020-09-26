export default {
  methods: {
    componentIdFrom(config) {
      return `input-${['text', 'number', 'range', 'url'].includes(config.type) ? 'text' : (config.type || 'hidden')}`
    },
    showIfFrom(config) {
      return !config.showif || this.values[config.showif] && this.values[config.showif].length
    },
    refFrom(config) {
      return config.specialInput ? 'specialInput' : ''
    }
  }
}
