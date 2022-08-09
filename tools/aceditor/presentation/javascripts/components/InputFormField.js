import InputHelper from './InputHelper.js'

// Text/Number/Color/slider
export default {
  props: [ 'value', 'config', 'selectedForms' ],
  mixins: [ InputHelper ],
  data() {
    return {
      fields: []
    }
  },
  mounted() {
    let initalValues = this.value ? this.value.split(',') : [this.config.value || this.config.default]
    this.fields = this.fieldOptions.filter((field) => initalValues.includes(field.id))
  },
  computed: {
    fieldOptions() {
      let extraFields = this.formatExtraFieldsAsArray(this.config.extraFields);
      if (extraFields.includes('id_typeannonce') && Object.keys(this.selectedForms).length < 2){
        extraFields.splice(extraFields.indexOf('id_typeannonce'),1);
      }
      let fields = this.getFieldsFormSelectedForms(this.selectedForms,extraFields);
      if (this.config.only == 'lists'){
        fields = fields.filter(a => (typeof a.options == 'object' && a.options !== null))
      }
      return fields
    }
  },
  watch: {
    fields() {
      this.$emit('input', this.fields.map(f => f.id).join(','))
    },
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      
      <v-select v-if="config.multiple" v-model="fields" :options="fieldOptions" label="id" :multiple="true">
        <template v-slot:option="option">
          <span v-html="option.label"></span> - {{ option.id }}
        </template>
      </v-select>
      
      <select v-else :value="value" v-on:input="$emit('input', $event.target.value)" class="form-control">
        <option value=""></option>
        <option v-for="field in fieldOptions" v-if="field.label" :value="field.id">
          <span v-html="field.label"></span> - {{ field.id }}
        </option>
      </select>
      
      <input-hint :config="config"></input-hint>
    </div>
    `
}
