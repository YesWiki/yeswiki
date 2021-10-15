export default {
  props: [ 'value', 'config' ],
  computed: {
    customValue: {
      get() {
        let result = this.value
        if (this.config.checkedvalue) {
          result = this.value == this.config.checkedvalue
        }
        if (result == "false") result = false
        return result
      },
      set(newValue) {
        if (this.config.checkedvalue) {
          newValue = newValue ? this.config.checkedvalue : this.config.uncheckedvalue
        }
        this.$emit('input', newValue)
      }
    }
  },
  template: `
    <div class="form-group checkbox" :title="config.hint" >
      <label>
        <input type="checkbox" v-model="customValue" />
        <span>{{ config.label }}</span>
      </label>
      <input-hint :config="config"></input-hint>
    </div>`
}
