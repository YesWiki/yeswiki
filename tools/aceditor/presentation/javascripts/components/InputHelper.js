// Some methods to be reused as mixins for component who want to build dynamically
// input components
export default {
  methods: {
    componentIdFrom(config) {
      return `input-${['text', 'number', 'range', 'url', 'email'].includes(config.type) ? 'text' : (config.type || 'hidden')}`
    },
    // Whether or not display this field (and include it's key/value in the action params)
    checkConfigDisplay(config) {
      if (!config) return false
      let showIfResult = true
      // condition with showif attribute
      if (config.showif) {
        let showIfConf = config.showif
        if (typeof showIfConf === 'string') { // allow shortcut conf like showif: myfield
          showIfConf = {}
          showIfConf[config.showif] = 'notNull'
        }
        // Check every condition is respected
        for(const field in showIfConf) {
          const value = this.values[field]
          const expectedValue = showIfConf[field]
          if (expectedValue == 'notNull') showIfResult = showIfResult && !!value
          else if (Array.isArray(expectedValue)) showIfResult = showIfResult && expectedValue.includes(value)
          else if (value) showIfResult = showIfResult && new RegExp(expectedValue, 'i').exec(value) != null
        }
      }
      // Other conditions
      const hideIf = (config.showif && !showIfResult)
                  || (config.showOnlyFor && !config.showOnlyFor.includes(this.selectedActionId))
                  || (config.showExceptFor && config.showExceptFor.includes(this.selectedActionId))
      return !hideIf
    },
    checkVisibility(config) {
      return this.checkConfigDisplay(config) && (!config.advanced || this.$root.displayAdvancedParams)
    },
    refFrom(config) {
      return config.subproperties || config.type == "geo" ? 'specialInput' : ''
    }
  }
}
