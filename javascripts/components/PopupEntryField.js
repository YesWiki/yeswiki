import EntryField from './EntryField.js'

export default {
  props: ['entry', 'prop', 'withlabel', 'oneline', 'imagewidth', 'imageheight', 'imagemethod', 'imagetoken'],
  components: { EntryField },
  computed: {
    renderViaEntryField() {
      switch (this.type) {
        case 'listedatedeb':
        case 'liste':
        case 'listefiche':
        case 'checkbox':
        case 'checkboxfiche':
        case 'radio':
        case 'radiofiche':
        case 'listefiches':
        case 'listefichesliees':
        case 'tags':
          return !this.displayOnOneLine || Array.isArray(this.value)
        default:
          return false
      }
    },
    displayLabel() {
      return [0, '0', false, 'false'].indexOf(this.withlabel) == -1
    },
    displayOnOneLine() {
      return [1, '1', true, 'true'].indexOf(this.oneline) > -1
    },
    value() {
      const value = this.entry[this.prop] || ''
      switch (this.type) {
        case 'listedatedeb':
        case 'liste':
        case 'listefiche':
        case 'checkbox':
        case 'checkboxfiche':
        case 'radio':
        case 'radiofiche':
        case 'listefiches':
        case 'listefichesliees':
        case 'tags':
          const values = value.split(',').map((v) => this.field.options[v])
          return values.length == 0 ? '' : (values.length == 1 ? values[0] : values)
        case 'email':
          return '' // security
        case 'link':
          return value ? `<a href="${encodeURI(value)}" class="newtab">${value}</a>` : ''
        default:
          return value
      }
    },
    field() {
      return this.$root.fieldInfo(this.prop)
    },
    type() {
      return this.field.type
    },
    label() {
      return this.field.label || ''
    }
  },
  template: `
    <div v-if="renderViaEntryField && displayLabel && value" v-bind="$attrs">
      <div><strong>{{ label }} :</strong></div>
      <EntryField :entry="entry" :prop="prop" v-bind="$attrs"></EntryField>
    </div>
    <EntryField v-else-if="renderViaEntryField" :entry="entry" :prop="prop" v-bind="$attrs"></EntryField>
    <h3 v-else-if="type == 'titre' && value" v-html="value" v-bind="$attrs"></h3>
    <img
      v-else-if="type == 'image' && value" class="popup-visual" 
      v-bind="$attrs"
      :src="$root.urlImage(entry,prop,imagewidth,imageheight,imagemethod)"
      @error="$root.urlImageResizedOnError(entry,prop,imagewidth,imageheight,imagemethod,imagetoken)">
    </img>
    <div v-else-if="displayLabel && displayOnOneLine && value" v-bind="$attrs">
      <strong>{{ label }} :</strong>&nbsp;
      <span v-html="value"></span>
    </div>
    <div v-else-if="displayLabel && value" v-bind="$attrs">
      <div><strong>{{ label }} :</strong></div>
      <div v-html="value"></div>
    </div>
    <div v-else-if="value" v-html="value" v-bind="$attrs"></div>
  `
}
