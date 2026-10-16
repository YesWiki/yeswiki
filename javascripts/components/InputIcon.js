import { openIconPicker, iconHtml, iconFromString } from '../icon-picker.js'

export default {
  props: ['value', 'config'],
  emits: ['input'],
  computed: {
    sources() {
      return this.config.sources || ['sprite']
    },
    previewHtml() {
      return iconHtml(iconFromString(this.value))
    },
  },
  methods: {
    pick(event) {
      openIconPicker({
        anchor: event.currentTarget,
        value: iconFromString(this.value),
        sources: this.sources,
        onPick: (icon) => this.$emit('input', icon.value),
      })
    },
  },
  template: `
    <div class="yw-form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      <button type="button" class="yw-input yw-icon-field__button" @click="pick"
              :aria-label="config.label">
        <span class="yw-icon-field__preview" v-html="previewHtml"></span>
      </button>
      <input-hint :config="config"></input-hint>
    </div>
  `,
}
