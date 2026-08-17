import InputHelper from './InputHelper.js'

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
    /** Match the value against the fields on offer -- but only once there ARE fields. */
    selectWhatTheValueNames() {
      if (this.fieldOptions.length === 0) return
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
