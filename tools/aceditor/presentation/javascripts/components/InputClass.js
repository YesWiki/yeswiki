import InputHelper from './InputHelper.js'
import InputList from './InputList.js'
import InputCheckbox from './InputCheckbox.js'

export default {
  props: [ 'name', 'value', 'config', 'selectedForm', 'values' ],
  components: { InputList, InputCheckbox },
  mixins: [ InputHelper ],
  data() {
    return {
      classValues: {}
    }
  },
  mounted() {
    this.resetValues()
    this.parseNewValues(this.values)
  },
  methods: {
    resetValues() {
      this.classValues = {}
      for(let propName in this.config.subproperties) {
        let config = this.config.subproperties[propName] || {}
        this.classValues[propName] = config.default || config.value || ''
      }
    },
    parseNewValues(newValues) {
      if (newValues.class) {
        const classes = newValues.class.split(' ')
        let optionsList = []
        for(let propName in this.config.subproperties) {
          let componentDefinition = this.config.subproperties[propName] || {};
          if (componentDefinition.type == 'list'){
            optionsList = Object.keys(componentDefinition.options)
            for(let classValue of classes) {
              if (optionsList.find(o => o == classValue)) this.classValues[propName] = classValue
            }
          } else if (componentDefinition.type == 'checkbox') {
            let checkedValue = componentDefinition.checkedvalue || "";
            let unCheckedValue = componentDefinition.uncheckedvalue || "";
            for(let classValue of classes) {
              if ((classValue == checkedValue && checkedValue != "") || 
                (classValue == unCheckedValue && unCheckedValue != "")){
                this.classValues[propName] = classValue
              }
            }
          }
        }
      }
    },
    getValues() {
      let result = Object.values(this.classValues)
      if (!this.values.text && this.values.icon) result.push('btn-icon') // special handling for button action
      return { class: result.join(' ').trim().replace(/\s+/, ' ') }
    },
    updateValue(propName, value) {
      this.classValues[propName] = value
      this.$emit('input', this.getValues())
    }
  },
  template: `
    <div class="multi-input-container">
      <template v-for="(property, propName) in config.subproperties">
        <component :is="componentIdFrom(property)" v-show="checkVisibility(property)"
                   :value="classValues[propName]" v-on:input="updateValue(propName, $event)"
                   :name="propName" :values="values"
                   :config="property" :selected-form="selectedForm">
        </component>
      </template>
    </div>`
}
