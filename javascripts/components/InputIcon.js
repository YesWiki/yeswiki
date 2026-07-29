// InputIcon — actions-builder icon input: a plain text input with a live preview.
// Accepts a Tabler sprite name ("heart") or a historic FontAwesome class string
// ("fas fa-heart"), both rendered through src/assets/icons.svg.
import { legacyIconMap, legacyIconToSprite } from '../yw-icon-map.js'

export default {
  props: ['value', 'config'],
  emits: ['input'],
  computed: {
    previewHtml() {
      const value = (this.value || '').trim()
      if (Object.values(legacyIconMap).includes(value)) {
        return `<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#${value}"/></svg>`
      }
      return legacyIconToSprite(value) || legacyIconToSprite('icons')
    }
  },
  template: `
    <div class="yw-form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="yw-form-label">{{ config.label || 'Icone' }}</label>
      <div class="input-group">
        <input type="text" :value="value" v-on:input="$emit('input', $event.target.value)"
               class="yw-input" ref="input" placeholder="heart"/>
        <span class="input-group-addon" v-html="previewHtml"></span>
      </div>
      <input-hint :config="config"></input-hint>
    </div>
  `
}
