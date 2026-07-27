// InputColor — actions-builder color input (ticket 16: native <input type=color>
// replaces the jQuery spectrum picker; disclosed simplification: no preset
// palette grid, the browser's own color dialog is used)
export default {
  props: ['value', 'config'],
  emits: ['input'],
  template: `
    <div class="yw-form-group input-group" :class="config.type" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      <input type="color" :value="value" v-on:input="$emit('input', $event.target.value)"
            class="yw-input"
            :required="config.required" ref="input"
      />
      <input-hint :config="config"></input-hint>
    </div>
    `
}
