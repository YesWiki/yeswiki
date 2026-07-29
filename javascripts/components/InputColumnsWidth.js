import InputMultiInput from './InputMultiInput.js'

export default {
  mixins: [InputMultiInput],
  methods: {
    parseNewValues(newValues) {
      if (newValues.columnswidth) {
        this.elements = []
        newValues.columnswidth.split(',').forEach((el) => {
          this.elements.push({ field: el.split('=')[0], width: el.split('=')[1] })
        })
      }
    },
    getValues() {
      return { columnswidth: this.elements.filter((m) => m.field && m.width).map((m) => `${m.field}=${m.width}`).join(',') }
    }
  },
  template: `
    <div class="multi-input-container" :class="name">
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      <div class="inline-form" v-for="element in elements">
        <template v-for="(property, propName) in config.subproperties">
          <component :is="componentIdFrom(property)" v-model="element[propName]"
                     v-show="checkVisibility(property)" :name="propName" :values="values"
                     :config="property" :selected-forms="selectedForms">
          </component>
        </template>
        <!-- Remove Button -->
        <div class="yw-form-group btn-close-container">
          <button class="btn btn-default btn-icon" @click="removeElement(element)">
            <svg class="yw-icon btn-remove-group" aria-hidden="true"><use href="src/assets/icons.svg#x"/></svg>
          </button>
        </div>
      </div>
      <input-hint :config="config"></input-hint>
      <!-- Add Button -->
      <button @click="addElement" class="btn btn-info btn-icon btn-add-element">
        <span v-if="config['btn-label-add']">{{ config['btn-label-add'] }}</span>
        <svg class="yw-icon" aria-hidden="true" v-else><use href="src/assets/icons.svg#plus"/></svg>
      </button>
    </div>`
}
