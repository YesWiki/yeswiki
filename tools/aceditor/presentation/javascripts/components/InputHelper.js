export default {
  methods: {
    componentIdFrom(config) {
      return `input-${['text', 'number', 'range', 'url'].includes(config.type) ? 'text' : (config.type || 'hidden')}`
    },
    showIfFrom(config) {
      const hideIf = (config.showif && !this.values[config.showif])
                  || (config.showOnlyFor && !config.showOnlyFor.includes(this.selectedActionId))
                  || (config.showExceptFor && config.showExceptFor.includes(this.selectedActionId))
                  || (config.advanced && !this.displayAdvancedParams)
    return !hideIf
    },
    refFrom(config) {
      return config.specialInput || config.type == "geo" ? 'specialInput' : ''
    }
  }
}
