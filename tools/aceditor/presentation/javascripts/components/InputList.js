// ext/Number/Color/slider
export default {
  props: [ 'value', 'config' ],
  computed: {
    optionsList() {
      let result = this.config.options.map(el => {
        const splited = el.split('->')
        return { value: splited[0], label: splited.length > 1 ? splited[1] : splited[0] }
      })
      result.unshift({value: '', label: ''})
      return result
    }
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <select :value="value" v-on:input="$emit('input', $event.target.value)"
              :required="config.required" class="form-control" >
        <option v-for="option in optionsList" :value="option.value" >{{ option.label }}</option>
      </select>
    </div>
    `
}
