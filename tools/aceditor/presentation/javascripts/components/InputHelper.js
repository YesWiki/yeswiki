// Some methods to be reused as mixins for component who want to build dynamically
// input components
export default {
  methods: {
    componentIdFrom(config) {
      return `input-${['text', 'number', 'range', 'url'].includes(config.type) ? 'text' : (config.type || 'hidden')}`
    },
    showIfFrom(config) {
      const hideIf = (config.showif && !this.values[config.showif])
                  || (config.showOnlyFor && !config.showOnlyFor.includes(this.selectedActionId))
                  || (config.showExceptFor && config.showExceptFor.includes(this.selectedActionId))
                  || (config.advanced && !this.$root.displayAdvancedParams)
      return !hideIf
    },
    refFrom(config) {
      return config.subproperties || config.type == "geo" ? 'specialInput' : ''
    }
  }
}
