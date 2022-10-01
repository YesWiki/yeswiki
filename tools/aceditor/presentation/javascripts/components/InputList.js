import InputHelper from './InputHelper.js'

export default {
  props: ['name', 'value', 'config', 'selectedForms', 'values'],
  mixins: [InputHelper],
  computed: {
    optionsList() {
      // Get the data from a specific form field
      if (this.config.dataFromFormField) {
        if (!this.selectedForms || !this.values[this.config.dataFromFormField]) return []
        let extraFields = this.formatExtraFieldsAsArray(this.config.extraFields)
        // allow only 'id_typeannonce'
        extraFields = (extraFields.includes('id_typeannonce') && Object.keys(this.selectedForms).length > 1)
          ? ['id_typeannonce']
          : []
        const fields = this.getFieldsFormSelectedForms(this.selectedForms, extraFields)
        const fieldConfig = fields.find((e) => e.id == this.values[this.config.dataFromFormField])
        return fieldConfig ? fieldConfig.options : []
      }
      // Options are provided in configuration

      if (Array.isArray(this.config.options)) {
        return this.config.options.reduce((result, option) => (result[option] = option, result), {})
      }
      const result = {}
      for (const key in this.config.options) {
        const option = this.config.options[key]
        if (typeof option !== 'object' || !option.showif || this.checkConfigDisplay(option)) {
          result[key] = typeof option === 'object' ? option.label : option
        }
      }
      return result
    }
  },
  template: `
    <div class="form-group input-group" :class="config.type" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <select :value="value" v-on:input="$emit('input', $event.target.value)"
              :required="config.required" class="form-control">
        <option value=""></option>
        <option v-for="(optLabel, optValue) in optionsList" :value="optValue" :selected="value == optValue">
          {{ optLabel }}
        </option>
      </select>
      <input-hint :config="config"></input-hint>
    </div>
    `
}
