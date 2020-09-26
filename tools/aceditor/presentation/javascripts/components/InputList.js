// ext/Number/Color/slider
export default {
  props: [ 'name', 'value', 'config', 'selectedForm', 'values' ],
  computed: {
    optionsList() {
      // Get the data from a specific form field
      if (this.config.dataFromFormField) {
        if (!this.selectedForm || !this.values[this.config.dataFromFormField]) return []
        var fieldConfig = this.selectedForm.prepared.find(e => e.id == this.values[this.config.dataFromFormField])
        return fieldConfig ? fieldConfig.values.label : []
      }
      // Options are provided in configuration
      else {
        let result = {}
        this.config.options.forEach(el => {
          const splited = el.split('->')
          result[splited[0]] = splited.length > 1 ? splited[1] : splited[0]
        })
        return result
      }
    }
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <select :value="value" v-on:input="$emit('input', $event.target.value)"
              :required="config.required" class="form-control">
        <option value=""></option>
        <option v-for="(label, value) in optionsList" :value="value" >{{ label }}</option>
      </select>
    </div>
    `
}
