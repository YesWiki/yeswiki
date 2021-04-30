// Text/Number/Color/slider
export default {
  props: [ 'value', 'config', 'selectedForm' ],
  computed: {
    fieldOptions() {
      if (this.config.only == 'lists')
        return this.selectedForm.prepared.filter(a => (typeof a.options == 'object' && a.options !== null))
      else
        return this.selectedForm.prepared
    }
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <select :value="value" v-on:input="$emit('input', $event.target.value)" class="form-control">
        <option value=""></option>
        <option v-for="field in fieldOptions" v-if="field.label" :value="field.id"><span v-html="field.label"></span> - {{ field.id }}</option>
      </select>
      <input-hint :config="config"></input-hint>
    </div>
    `
}
