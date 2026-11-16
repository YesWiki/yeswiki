import { canPickFromScreen, pickInto } from '../eyedropper.js'

export default {
  props: ['value', 'config'],
  emits: ['input'],
  data() {
    return { canPickFromScreen: canPickFromScreen() }
  },
  methods: {
    pickInto,
    pickLabel() {
      return _t('PICK_COLOUR_FROM_SCREEN')
    },
  },
  template: `
    <div class="yw-form-group input-group" :class="config.type" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      <input type="color" :value="value" v-on:input="$emit('input', $event.target.value)"
            class="yw-input"
            :required="config.required" ref="input"
      />
      <button v-if="canPickFromScreen" type="button" class="yw-btn yw-btn--sm yw-eyedropper"
              @click="pickInto($refs.input)" :title="pickLabel()" :aria-label="pickLabel()">
        <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#color-picker"/></svg>
      </button>
      <input-hint :config="config"></input-hint>
    </div>
    `,
}
