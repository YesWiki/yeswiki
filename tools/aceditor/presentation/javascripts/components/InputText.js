// ext/Number/Color/slider
export default {
  props: [ 'value', 'config' ],
  mounted() {
    if (!this.value && this.config.value) this.$emit('input', this.config.value)
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <input :type="config.type" :value="value"
             v-on:input="$emit('input', $event.target.value)" class="form-control"
             :required="config.required" :min="config.min" :max="config.max" ref="input"
      />
      <input-hint :config="config"></input-hint>
    </div>
    `
}
