import InputHelper from './InputHelper.js'
import InputFormField from './InputFormField.js'

export default {
  props: [ 'name', 'value', 'config', 'selectedForm', 'values' ],
  components: { InputFormField },
  mixins: [ InputHelper ],
  data() {
    return {
      mappingValues: {}
    }
  },
  mounted() {
    this.resetValues()
    this.parseNewValues(this.values)
  },
  methods: {
    resetValues() {
      this.mappingValues = {}
      for(let propName in this.config.subproperties) {
        this.mappingValues[propName] = this.config.subproperties[propName].default || ''
      }
    },
    parseNewValues(newValues) {
      if (newValues.correspondance) {
        const mappings = newValues.correspondance.split(',') // ["bf_image=my_image", "bf_other=my_other"]
        const propList = Object.keys(this.config.subproperties) // ["bf_image", "bf_baseline"]
        for(let mapping of mappings) {
          let popName = mapping.split('=')[0]
          if (propList.includes(propName)) {
            this.mappingValues[popName] = mapping.split('=')[1]
          }
        }
      }
    },
    getValues() {
      let result = []
      for(let key in this.mappingValues) {
        if (key && this.mappingValues[key] && Object.keys(this.config.subproperties).includes(key)) {
          result.push(`${key}=${this.mappingValues[key]}`)
        }
      }
      return { correspondance: result.join(',') }
    },
    updateValue(propName, value) {
      this.mappingValues[propName] = value
      this.$emit('input', this.getValues())
    }
  },
  template: `
    <div class="multi-input-container">
      <template v-for="(property, propName) in config.subproperties">
        <component :is="componentIdFrom(property)" v-show="checkVisibility(property)"
                   :value="mappingValues[propName]" v-on:input="updateValue(propName, $event)"
                   :name="propName" :values="values"
                   :config="property" :selected-form="selectedForm">
        </component>
      </template>
      <input-hint :config="config"></input-hint>
    </div>`
}
