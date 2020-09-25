// Text/Number/Color/slider
export default {
  props: [ 'value', 'config', 'selectedForm' ],
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <select :value="value" v-on:input="$emit('input', $event.target.value)" class="form-control">
        <option value=""></option>
        <option v-for="field in selectedForm.prepared" v-if="field.label" :value="field.id">{{ field.label }} - {{ field.id }}</option>
      </select>
    </div>
    `
}
