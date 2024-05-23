import InputHelper from './InputHelper.js'
import InputList from './InputList.js'
import InputCheckbox from './InputCheckbox.js'

export default {
  props: ['name', 'value', 'config', 'selectedForms', 'values'],
  components: { InputList, InputCheckbox },
  mixins: [InputHelper],
  data() {
    return { classValues: {} }
  },
  mounted() {
    this.resetValues()
    this.parseNewValues(this.values)
  },
  methods: {
    resetValues() {
      this.classValues = {}
      for (const propName in this.config.subproperties) {
        const config = this.config.subproperties[propName] || {}
        this.classValues[propName] = config.default || config.value || ''
      }
    },
    parseNewValues(newValues) {
      if (newValues.class) {
        const classes = newValues.class.split(' ')
        const classesGroupedBy2 = []
        classes.forEach((c, idx) => {
          if ((idx + 1) < classes.length) {
            classesGroupedBy2.push(`${c} ${classes[idx + 1]}`)
          }
        })
        const classesMerged = [...classes, ...classesGroupedBy2]
        let optionsList = []
        for (const propName in this.config.subproperties) {
          const componentDefinition = this.config.subproperties[propName] || {}
          if (componentDefinition.type == 'list') {
            optionsList = Object.keys(componentDefinition.options)
            for (const classValue of classesMerged) {
              if (optionsList.find((o) => o == classValue)) this.classValues[propName] = classValue
            }
          } else if (componentDefinition.type == 'checkbox') {
            const checkedValue = componentDefinition.checkedvalue || ''
            const unCheckedValue = componentDefinition.uncheckedvalue || ''
            for (const classValue of classesMerged) {
              if ((classValue == checkedValue && checkedValue != '')
                || (classValue == unCheckedValue && unCheckedValue != '')) {
                this.classValues[propName] = classValue
              }
            }
          }
        }
      }
    },
    getValues() {
      const result = Object.values(this.classValues)
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
                   :config="property" :selected-forms="selectedForms">
        </component>
      </template>
    </div>`
}
