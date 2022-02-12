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
      // Cast values to string before compare, because in yaml we might use boolean or number, but
      // wikicode will always use strings
      let checkedvalue = this.config.checkedvalue || "true"
      this.checked = `${this.value}` == `${checkedvalue}`
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
