// Text/Number/Color/slider
export default {
  props: [ 'value', 'config', 'selectedForm' ],
  data() {
    return {
      fields: []
    }
  },
  mounted() {
    let initalValues = this.value ? this.value.split(',') : [this.config.default]
    this.fields = this.fieldOptions.filter((field) => initalValues.includes(field.id))
  },
  computed: {
    fieldOptions() {
      if (this.config.only == 'lists')
        if (typeof this.selectedForm.prepared == 'object'){
          let fields = [];
          for (let key in this.selectedForm.prepared) {
            if (typeof this.selectedForm.prepared[key].options == 'object' 
                && this.selectedForm.prepared[key].options !== null){
              fields.push(this.selectedForm.prepared[key])
            }
          }
          return fields ;
        } else {
          return this.selectedForm.prepared.filter(a => (typeof a.options == 'object' && a.options !== null))
        }
      else
        return this.selectedForm.prepared
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
