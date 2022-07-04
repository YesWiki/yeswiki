export default {
  props: [ 'entry', 'prop' ],
  data() {
    return {
      isDateTime: false
    }
  },
  computed: {
    value() {
      const value = this.entry[this.prop] || ""
      switch (this.type) {
        case 'listedatedeb':
          if (!value) return ""
          if (value.includes('T')) this.isDateTime = true
          return new Date(value)
        case 'liste':
        case 'listefiche':
        case 'checkbox':
        case 'checkboxfiche':
        case 'radio':
        case 'radiofiche':
        case 'listefiches':
        case 'listefichesliees':
        case 'tags':
          const values = value.split(',').map(v => this.field.options[v])
          return values.length <= 1 ? values[0] : values
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
    <div v-bind="$attrs" v-if="value">
      <div v-if="Array.isArray(value)" class="field field-array">
        <span v-for="v in value" v-html="v"></span>
      </div>
      <div v-else-if="type == 'listedatedeb'" class="field field-date">
        <span class="day">{{ value.toLocaleDateString([], { day: 'numeric' }) }}</span>
        <span class="month">{{ value.toLocaleDateString([], { month: 'short' }).replace('.', '') }}</span>
        <span class="default">
          <template v-if="isDateTime">
            {{ value.toLocaleString([], {dateStyle: 'medium', timeStyle: 'short'}) }}
          </template>
          <template v-else>
            {{ value.toLocaleDateString([], {year: "numeric", month: "short", day: "numeric"}) }}
          </template>
        </span>
      </div>
      <div v-else v-html="value" class="field field-default"></div>
    </div>
  `
}
