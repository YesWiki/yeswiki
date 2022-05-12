import EntryField from './EntryField.js'

export default {
  props: [ 'entry', 'prop', 'withlabel', 'oneline' ],
  components: {
    'EntryField': EntryField
  },
  computed: {
    renderViaEntryField (){
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
          return !this.displayOnOneLine || Array.isArray(this.value);
        default:
          return false;
      } 
    },
    displayLabel (){
      return [0,"0",false,"false"].indexOf(this.withlabel) == -1;
    },
    displayOnOneLine (){
      return [1,"1",true,"true"].indexOf(this.oneline) > -1;
    },
    value() {
      const value = this.entry[this.prop] || ""
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
          const values = value.split(',').map(v => this.field.options[v])
          return values.length == 0 ? "" : (values.length == 1 ? values[0] : values );
        case 'email':
          return ""; // security
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
    label(){
      return this.field.label || "";
    }
  },
  template: `
    <div v-if="renderViaEntryField && displayLabel && value">
      <div><strong>{{ label }} :</strong></div>
      <EntryField :entry="entry" :prop="prop"></EntryField>
    </div>
    <EntryField v-else-if="renderViaEntryField" :entry="entry" :prop="prop"></EntryField>
    <h3 v-else-if="type == 'titre' && value" v-html="value"></h3>
    <img v-else-if="type == 'image' && value" class="popup-visual" :src="$root.urlImage(entry,prop,'thumbnails')"></img>
    <div v-else-if="displayLabel && displayOnOneLine && value">
      <strong>{{ label }} :</strong>&nbsp;
      <span v-html="value"></span>
    </div>
    <div v-else-if="displayLabel && value">
      <div><strong>{{ label }} :</strong></div>
      <div v-html="value"></div>
    </div>
    <div v-else-if="value" v-html="value"></div>
  `
}
