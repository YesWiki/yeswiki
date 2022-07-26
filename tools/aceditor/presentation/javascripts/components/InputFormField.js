// Text/Number/Color/slider
export default {
  props: [ 'value', 'config', 'selectedForm' ],
  emits: ['input'],
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
      let fields = (typeof this.selectedForm.prepared == 'object') ? Object.values(this.selectedForm.prepared) : this.selectedForm.prepared;
      if (this.config.only == 'lists')
        return fields.filter(a => (typeof a.options == 'object' && a.options !== null))
      else
        return fields
    }
  },
  watch: {
    fields: {
      handler(val, oldVal) {
        this.$emit('input', this.fields.map(f => f.id).join(','))
      },
      deep: true // to force following mutations
    }
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      
      <v-select v-if="config.multiple" v-model:value="fields" :options="fieldOptions" label="id" :multiple="true">
        <template v-slot:option="option">
          <span v-html="option.label"></span> - {{ option.id }}
        </template>
      </v-select>
      
      <select v-else :value="value" v-on:input="$emit('input', $event.target.value)" class="form-control">
        <option value=""></option>
        <template v-for="field in fieldOptions">
          <option v-if="field.label" :value="field.id">
            <span v-html="field.label"></span> - {{ field.id }}
          </option>
        </template>
      </select>
      
      <input-hint :config="config"></input-hint>
    </div>
    `
}
