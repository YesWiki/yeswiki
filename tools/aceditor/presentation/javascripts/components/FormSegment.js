export default {
  props: [ 'name', 'config', 'parentValue', 'formFieldOptions' ],
  computed: {
    value: {
      get() { return this.parentValue },
      set(newValue) { this.$emit('update:value', newValue) }
    }
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label && config.type != 'checkbox'" class="control-label">{{ config.label }}</label>
      <!-- Text/Number/Color -->
      <template v-if="['text', 'number', 'color'].includes(config.type)">
        <input :type="config.type" v-model="value" class="form-control" :required="config.required"
               :min="config.min" :max="config.max"
               />
      </template>
      <!-- List -->
      <template v-else-if="config.type == 'list'">
        <select class="form-control" v-model="value" :required="config.required">
          <option value=""></option>
          <option v-for="option in config.options" :value="option" >{{ option }}</option>
        </select>
      </template>
      <!-- Checkbox -->
      <template v-else-if="config.type == 'checkbox'">
        <label>
          <input type="checkbox" v-model="value"/>
          <span>{{ config.label }}</span>
        </label>
      </template>
      <!-- Form Field -->
      <template v-else-if="config.type == 'form_field'">
        <select v-model="value" class="form-control">
          <option value=""></option>
          <option v-for="field in formFieldOptions" v-if="field.label" :value="field.id">{{ field.label }} - {{ field.id }}</option>
        </select>
      </template>
      <!-- Other Property -->
      <template v-else>
        <input type="hidden" v-model="value" />
      </template>
    </div>
  `
}
