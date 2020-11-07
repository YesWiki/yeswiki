// Some methods to be reused as mixins for component who want to build dynamically
// input components
export default {
  methods: {
    componentIdFrom(config) {
      return `input-${['text', 'number', 'range', 'url'].includes(config.type) ? 'text' : (config.type || 'hidden')}`
    },
    showIfFrom(config) {
      let showIfResult = true
      // visbility condition with showif attribute
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
          else if (value) showIfResult = showIfResult && (value == expectedValue || field == 'class' && value.class.includes(expectedValue))
        }
      }
      // Other visbility conditions
      const hideIf = (config.showif && !showIfResult)
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
