export default {
  props: ['config'],
  computed: {
    iconClass() {
      return `fa fa-${this.config.icon}`
    }
  },
  template: `
    <span class="input-group-addon addon-icon">
      <i :class="iconClass"></i>
    </span>
  `
}