import InputHelper from './InputHelper.js'

// Text/Number/Color/slider
export default {
  props: ['value', 'config', 'selectedForms'],
  emits: ['input'],
  mixins: [InputHelper],
  data() {
    return { fields: [] }
  },
  mounted() {
    this.selectWhatTheValueNames()
  },
  computed: {
    fieldOptions() {
      // no form picked yet, or its fields are still on their way: an empty list of fields,
      // not a crash. `Object.keys(null)` throws, and it threw before the panel had drawn
      // anything at all -- which took the whole panel with it, not just this one input.
      if (!this.selectedForms) return []
      const extraFields = this.formatExtraFieldsAsArray(this.config.extraFields)
      if (
        extraFields.includes('form_id') &&
        Object.keys(this.selectedForms).length < 2
      ) {
        extraFields.splice(extraFields.indexOf('form_id'), 1)
      }
      let fields = this.getFieldsFormSelectedForms(
        this.selectedForms,
        extraFields,
      )
      if (this.config.only === 'lists') {
        fields = fields.filter(
          (a) => typeof a.options == 'object' && a.options !== null,
        )
      }
      // ...and the kinds of field this slot can actually use (`ofTypes`). A field with no
      // type is one of the pseudo-fields `extraFields` adds -- `created_at`, `owner` --
      // and a setting that asked for those meant them.
      const allowed = this.config.fieldTypes
      if (Array.isArray(allowed) && allowed.length > 0) {
        fields = fields.filter(
          (field) => !field.type || allowed.includes(field.type),
        )
      }

      return fields
    },
  },
  methods: {
    /**
     * Match the value against the fields on offer -- but only once there ARE fields.
     *
     * A form arrives by fetch, so this input mounts before it: matching against an empty
     * list selects nothing, the watcher below reports that as "", and whatever the value
     * was is gone. Which is how a slot with a declared default (the title's is the
     * Content's own computed title) came up empty, and why a mapping read out of a tag
     * had to be handed back to it afterwards.
     */
    selectWhatTheValueNames() {
      if (this.fieldOptions.length === 0) return
      // `value` is what the setting SUGGESTS, and a suggestion is for a component being
      // inserted -- filling it in on one the page already holds would rewrite a tag the
      // reader only opened to look at. The root draws the same distinction for top-level
      // settings (initValuesOnActionSelected); a slot of a mapping is handled here.
      const suggested = this.$root.isEditingExistingAction
        ? this.config.default
        : this.config.value || this.config.default
      const named = this.value ? this.value.split(',') : [suggested]
      this.fields = this.fieldOptions.filter((field) =>
        named.includes(field.id),
      )
    },
  },
  watch: {
    fieldOptions() {
      // the form landed: now the value can be matched against something
      if (this.fields.length === 0) this.selectWhatTheValueNames()
    },
    fields() {
      this.$emit('input', this.fields.map((f) => f.id).join(','))
    },
  },
  template: `
    <div class="yw-form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      
      <v-select v-if="config.multiple" v-model="fields" :options="fieldOptions" label="id" :multiple="true">
        <template v-slot:option="option">
          <span v-html="option.label"></span> - {{ option.id }}
        </template>
      </v-select>
      
      <select v-else :value="value" v-on:input="$emit('input', $event.target.value)" class="yw-input">
        <option value=""></option>
        <template v-for="field in fieldOptions" :key="field.id">
          <option v-if="field.label" :value="field.id">{{ field.label }} - {{ field.id }}</option>
        </template>
      </select>
      
      <input-hint :config="config"></input-hint>
    </div>
    `,
}
