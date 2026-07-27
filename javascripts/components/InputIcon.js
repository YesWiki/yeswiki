// InputIcon — actions-builder icon input (ticket 16: a plain text input with a
// live preview replaces the jQuery fontawesome-iconpicker popover; disclosed
// simplification: no searchable icon grid, type the FontAwesome class directly,
// e.g. "fas fa-home")
export default {
  props: ['value', 'config'],
  emits: ['input'],
  template: `
    <div class="yw-form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="yw-form-label">{{ config.label || 'Icone' }}</label>
      <div class="input-group">
        <input type="text" :value="value" v-on:input="$emit('input', $event.target.value)"
               class="yw-input" ref="input" placeholder="fas fa-home"/>
        <span class="input-group-addon"><i :class="value || 'fas fa-icons'"></i></span>
      </div>
      <input-hint :config="config"></input-hint>
    </div>
  `
}
