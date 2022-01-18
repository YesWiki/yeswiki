export default {
  props: [ 'entry', 'prop' ],
  computed: {
    value() {
      const value = this.entry[this.prop]
      switch (this.type) {
        case 'listedatedeb':
          return value.toLocaleString([], {dateStyle: 'medium', timeStyle: 'short'})
        default:
          return value
      } 
    },
    field() {
      return this.$root.fieldInfo(this.prop)
    },
    type() {
      return this.field.type
    }
  },
  template: `
    <div v-bind="$attrs" v-if="value" v-html="value">
    </div>
  `
}
