import IconPicker from './InputSegmentIconPicker.js'

export default {
  props: [ 'name', 'config', 'value', 'formFieldOptions', 'customClass' ],
  components: { IconPicker },
  computed: {
    customValue: {
      get() {
        let result = this.value
        if (this.config.type == "checkbox" && this.config.checkedvalue) {
          result = this.value == this.config.checkedvalue
        }
        return this.value
      },
      set(newValue) {
        if (this.config.type == "checkbox" && this.config.checkedvalue) {
          newValue = newValue ? this.config.checkedvalue : this.config.uncheckedvalue
        }
        this.$emit('input', newValue)
      }
    },
    optionsList() {
      let result = this.config.options.map(el => {
        const splited = el.split('->')
        return { value: splited[0], label: splited.length > 1 ? splited[1] : splited[0] }
      })
      result.unshift({value: '', label: ''})
      return result
    }
  },
  template: `
    <div class="form-group" :class="[config.type, customClass]" :title="config.hint" >
      <label v-if="config.label && config.type != 'checkbox'" class="control-label">{{ config.label }}</label>
      <!-- Text/Number/Color/slider -->
      <template v-if="['text', 'number', 'color', 'range'].includes(config.type)">
        <input :type="config.type" v-model="customValue" class="form-control" :required="config.required"
               :min="config.min" :max="config.max" ref="input"
               />
      </template>
      <!-- Icon -->
      <template v-else-if="config.type == 'icon'">
        <icon-picker v-model="customValue"></icon-picker>
      </template>
      <!-- List -->
      <template v-else-if="config.type == 'list'">
        <select class="form-control" v-model="customValue" :required="config.required">
          <option v-for="option in optionsList" :value="option.value" >{{ option.label }}</option>
        </select>
      </template>
      <!-- Checkbox -->
      <template v-else-if="config.type == 'checkbox'">
        <label>
          <input type="checkbox" v-model="customValue" />
          <span>{{ config.label }}</span>
        </label>
      </template>
      <!-- Form Field -->
      <template v-else-if="config.type == 'form_field'">
        <select v-model="customValue" class="form-control">
          <option value=""></option>
          <option v-for="field in formFieldOptions" v-if="field.label" :value="field.id">{{ field.label }} - {{ field.id }}</option>
        </select>
      </template>
      <!-- Other Property -->
      <template v-else>
        <input type="hidden" v-model="customValue" />
      </template>
    </div>
  `
}
