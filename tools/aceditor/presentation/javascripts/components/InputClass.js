import InputHelper from './InputHelper.js'
import InputList from './InputList.js'

export default {
  props: [ 'name', 'value', 'config', 'selectedForm', 'values' ],
  components: { InputList },
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
        this.classValues[propName] = this.config.subproperties[propName].default || ''
      }
    },
    parseNewValues(newValues) {
      if (newValues.class) {
        const classes = newValues.class.split(' ')
        let optionsList = []
        for(let classValue of classes) {
          for(let propName in this.config.subproperties) {
            optionsList = Object.keys(this.config.subproperties[propName].options)
            if (optionsList.find(o => o == classValue)) this.classValues[propName] = classValue
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
