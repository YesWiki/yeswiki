// ext/Number/Color/slider
export default {
  props: ['value', 'config'],
  mounted() {
    if (!this.value) {
      if (this.$root.isEditingExistingAction && this.config.default != undefined) {
        // when editing, do not use config.value if `!default` gives `true` (case for '')
        this.$emit('input', '')
      } else if (this.config.value) {
        this.$emit('input', this.config.value)
      }
    }
  },
  template: `
    <div class="form-group input-group" :class="config.type" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>  
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <input :type="config.type" :value="value"
             v-on:input="$emit('input', $event.target.value)" class="form-control"
             :required="config.required" :min="config.min" :max="config.max" ref="input"
      />
      <input-hint :config="config"></input-hint>
    </div>
    `
}
