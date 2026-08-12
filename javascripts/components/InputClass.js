import InputHelper from './InputHelper.js'
import InputList from './InputList.js'
import InputCheckbox from './InputCheckbox.js'

export default {
  props: ['name', 'value', 'config', 'selectedForms', 'values'],
  emits: ['input'],
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
      // `value` is what a field is pre-filled with when a component is INSERTED; `default`
      // is what the action does anyway. Falling through from one to the other meant that
      // opening the rail on a `{{section class="cover full-width text-left"}}` seeded the
      // shape picker with its suggestion and wrote `shape-rounded` into the class of a
      // section nobody had asked to reshape. The app applies the same rule for top-level
      // settings (initValuesOnActionSelected); this is the class picker's copy of it.
      const editing = this.$root.isEditingExistingAction
      for (const propName in this.config.subproperties) {
        const config = this.config.subproperties[propName] || {}
        this.classValues[propName] =
          (editing ? config.default : config.value || config.default) || ''
      }
    },
    parseNewValues(newValues) {
      if (newValues.class) {
        const classes = newValues.class.split(' ')
        const classesGroupedBy2 = []
        classes.forEach((c, idx) => {
          if (idx + 1 < classes.length) {
            classesGroupedBy2.push(`${c} ${classes[idx + 1]}`)
          }
        })
        const classesMerged = [...classes, ...classesGroupedBy2]
        let optionsList = []
        for (const propName in this.config.subproperties) {
          const componentDefinition = this.config.subproperties[propName] || {}
          if (componentDefinition.type === 'list') {
            optionsList = Object.keys(componentDefinition.options)
            for (const classValue of classesMerged) {
              if (optionsList.find((o) => o === classValue))
                this.classValues[propName] = classValue
            }
          } else if (componentDefinition.type === 'checkbox') {
            const checkedValue = componentDefinition.checkedvalue || ''
            const unCheckedValue = componentDefinition.uncheckedvalue || ''
            for (const classValue of classesMerged) {
              if (
                (classValue === String(checkedValue) && checkedValue !== '') ||
                (classValue === String(unCheckedValue) && unCheckedValue !== '')
              ) {
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
      // filtered rather than collapsed: every unset sub-setting contributes an empty string,
      // so joining leaves a run of spaces per gap -- and the `/\s+/` that was collapsing
      // them had no `g`, so it fixed the first gap and left the rest. A class of
      // `cover full-width  text-left` is what came back from opening the rail on a section.
      return { class: result.filter(Boolean).join(' ').trim() }
    },
    updateValue(propName, value) {
      this.classValues[propName] = value
      this.$emit('input', this.getValues())
    },
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
    </div>`,
}
