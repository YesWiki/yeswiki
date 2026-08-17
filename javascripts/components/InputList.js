import InputHelper from './InputHelper.js'

export default {
  props: ['name', 'value', 'config', 'selectedForms', 'values'],
  emits: ['input'],
  mixins: [InputHelper],
  computed: {
    /** Whether the setting names its own empty choice, rather than leaving it nameless. */
    hasOwnBlank() {
      return Object.keys(this.optionsList).includes('')
    },
    optionsList() {
      if (this.config.dataFromFormField) {
        if (!this.selectedForms || !this.values[this.config.dataFromFormField])
          return []
        let extraFields = this.formatExtraFieldsAsArray(this.config.extraFields)
        extraFields =
          extraFields.includes('form_id') &&
          Object.keys(this.selectedForms).length > 1
            ? ['form_id']
            : []
        const fields = this.getFieldsFormSelectedForms(
          this.selectedForms,
          extraFields,
        )
        const fieldConfig = fields.find(
          (e) =>
            String(e.id) === String(this.values[this.config.dataFromFormField]),
        )
        return fieldConfig ? fieldConfig.options : []
      }

      if (Array.isArray(this.config.options)) {
        return this.config.options.reduce(
          (result, option) => ((result[option] = option), result),
          {},
        )
      }
      const result = {}
      for (const key in this.config.options) {
        const option = this.config.options[key]
        if (
          typeof option !== 'object' ||
          !option.showif ||
          this.checkConfigDisplay(option)
        ) {
          result[key] = typeof option === 'object' ? option.label : option
        }
      }
      return result
    },
  },
  template: `
    <div class="yw-form-group input-group" :class="config.type" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      <select :value="value" v-on:input="$emit('input', $event.target.value)"
              :required="config.required" class="yw-input">
        {# The blank choice, unless the setting declares one of its own. A component that
           names its empty value -- a texture of "Uni", a shape of "Rectangle", visible to
           "tout le monde" -- had two options with the same empty value, and the browser
           picks the first: the setting showed blank while its real default sat below,
           unreachable except by choosing it. #}
        <option value="" v-if="!hasOwnBlank"></option>
        <option v-for="(optLabel, optValue) in optionsList" :value="optValue" :selected="value == optValue">
          {{ optLabel }}
        </option>
      </select>
      <input-hint :config="config"></input-hint>
    </div>
    `,
}
