export default {
  props: ['value', 'config'],
  emits: ['input'],
  data() {
    return {
      checked: undefined,
    }
  },
  methods: {
    setCheckedFromValue(value) {
      if (value === undefined) {
        const defaultValue = this.config.default || 'false'
        const checkedvalue = this.config.checkedvalue || 'true'
        this.checked = `${defaultValue}` === `${checkedvalue}`
      } else {
        const checkedvalue = this.config.checkedvalue || 'true'
        this.checked = `${value}` === `${checkedvalue}`
      }
    },
  },
  mounted() {
    this.setCheckedFromValue(this.value)
  },
  watch: {
    checked() {
      let result
      if (this.config.checkedvalue)
        result = this.checked
          ? this.config.checkedvalue
          : this.config.uncheckedvalue
      else result = this.checked
      this.$emit('input', result)
    },
    value() {
      this.setCheckedFromValue(this.value)
    },
  },
  template: `
    <div class="yw-form-group input-group checkbox" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>
      <label v-if="config.title" class="yw-form-label checkbox__title">{{ config.title }}</label>
      <label>
        <input type="checkbox" v-model="checked" />
        <span>{{ config.label }}</span>
      </label>
      <input-hint :config="config"></input-hint>
    </div>`,
}
