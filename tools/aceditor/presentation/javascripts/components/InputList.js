import InputHelper from './InputHelper.js'

export default {
  props: [ 'name', 'value', 'config', 'selectedForm', 'values' ],
  mixins: [ InputHelper ],
  computed: {
    optionsList() {
      // Get the data from a specific form field
      if (this.config.dataFromFormField) {
        if (!this.selectedForm || !this.values[this.config.dataFromFormField]) return []
        var fieldConfig = this.selectedForm.prepared.find(e => e.id == this.values[this.config.dataFromFormField])
        return fieldConfig ? fieldConfig.options : []
      }
      // Options are provided in configuration
      else {
        if (Array.isArray(this.config.options)) {
          return this.config.options.reduce((result,option)=> (result[option] = option, result), {});
        }
        let result = {}
        for(let key in this.config.options) {
          let option = this.config.options[key]
          if (typeof option !== "object" || !option.showif || this.checkConfigDisplay(option)) {
            result[key] = typeof option === "object" ? option.label : option
          }
        }
        return result;
      }
    }    
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
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
