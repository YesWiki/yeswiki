// ext/Number/Color/slider
export default {
  props: ['value', 'config'],
  emits: ['input'],
  mounted() {
    if (!this.value) {
      if (this.$root.isEditingExistingAction && this.config.default != null) {
        // when editing, do not use config.value if `!default` gives `true` (case for '')
        this.$emit('input', '')
      } else if (this.config.value) {
        this.$emit('input', this.config.value)
      }
    }
  },
  template: `
    <div class="yw-form-group input-group" :class="config.type" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>  
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      <input :type="config.type" :value="value"
             v-on:input="$emit('input', $event.target.value)" class="yw-input"
             :required="config.required" :min="config.min" :max="config.max"
             :step="config.step" ref="input"
      />
      <input-hint :config="config"></input-hint>
    </div>
    `,
}
