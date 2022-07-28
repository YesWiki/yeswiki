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
          const value = (this.values[field] || false).toString()          
          const expectedValue = showIfConf[field].toString()
          if (expectedValue == 'notNull') showIfResult = showIfResult && !["", "false"].includes(value)
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
    },
    getFieldsFormSelectedForms(selectedForms, fakeFields = []){
      let fields = [];
      for (const key in selectedForms) {
        let prepared = (typeof selectedForms[key].prepared == 'object')
          ? Object.values(selectedForms[key].prepared)
          : selectedForms[key].prepared;
        
        prepared.forEach((field)=>{
            if (fields.every((f)=>(!f.id && field.id) ||(f.id && !field.id) || f.id != field.id)){
              fields.push(field);
            }
          });
      }
      if (fakeFields.includes("formId")){
        let options = {};
        Object.keys(this.selectedForms).forEach((key)=>{
          options[key] = this.selectedForms[key]['bn_label_nature'] || key;
        });
        // fake a field
        fields.push({
          id: 'id_typeannonce',
          name: 'id_typeannonce',
          propertyName: 'id_typeannonce',
          label: 'Id',
          options: {...options} // clone object
        });
      }
      return fields;
    }
  }
}
