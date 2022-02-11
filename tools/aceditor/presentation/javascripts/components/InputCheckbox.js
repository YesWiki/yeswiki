export default {
  props: [ 'value', 'config' ],
  data() {
    return {
      // boolean internal value cause the real value could be a string when using checkedvalue and uncheckedvalue
      checked: undefined
    }
  },
  mounted() {
    if (this.value === undefined) {
      // if no value, we initialize to false, the the param will be correctly set 
      // i.e. myparam="false" or myparam="0" if uncheckvalue is defined
      this.checked = false
    }
    else {
      if (this.config.checkedvalue) this.checked = this.value == this.config.checkedvalue
      else this.checked = this.value
    }
  },
  watch: {
    checked() {
      let result
      if (this.config.checkedvalue) result = this.checked ? this.config.checkedvalue : this.config.uncheckedvalue
      else result = this.checked
      this.$emit('input', result)
    }
  },
  template: `
    <div class="form-group input-group checkbox" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>
      <label>
        <input type="checkbox" v-model="checked" />
        <span>{{ config.label }}</span>
      </label>
      <input-hint :config="config"></input-hint>
    </div>`
}
