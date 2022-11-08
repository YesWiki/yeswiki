import InputHelper from './InputHelper.js'
import InputFormField from './InputFormField.js'

export default {
  props: ['name', 'value', 'config', 'selectedForms', 'values'],
  components: { InputFormField },
  mixins: [InputHelper],
  data() {
    return { mappingValues: {} }
  },
  mounted() {
    this.resetValues()
    this.parseNewValues(this.values)
  },
  methods: {
    resetValues() {
      this.mappingValues = {}
      for (const propName in this.config.subproperties) {
        this.mappingValues[propName] = (this.config.subproperties[propName] || {}).default || ''
      }
    },
    parseNewValues(newValues) {
      if (newValues[this.name]) {
        const mappings = newValues[this.name].split(',') // ["bf_image=my_image", "bf_other=my_other"]
        const propList = Object.keys(this.config.subproperties) // ["bf_image", "bf_baseline"]
        for (const mapping of mappings) {
          const propName = mapping.split('=')[0]
          if (propName && propList.includes(propName)) {
            this.mappingValues[propName] = mapping.split('=')[1]
          }
        }
      }
    },
    getValues() {
      const result = []
      for (const propName in this.mappingValues) {
        const value = this.mappingValues[propName]
        if (propName && value != (this.config.subproperties[propName] || {}).default && value != ','
            && Object.keys(this.config.subproperties).includes(propName)) {
          result.push(`${propName}=${value}`)
        }
      }
      const obj = {}
      obj[this.name] = result.join(',')
      return obj
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
                   :config="property" :selected-forms="selectedForms">
        </component>
      </template>
      <input-hint :config="config"></input-hint>
    </div>`
}
