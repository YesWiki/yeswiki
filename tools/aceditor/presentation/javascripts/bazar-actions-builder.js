import InputSegment from './components/InputSegment.js'
import IconPicker from './components/InputSegmentIconPicker.js'
import WikiCodeInput from './components/WikiCodeInput.js'
import PreviewAction from './components/PreviewAction.js'
import AceEditorWrapper from './components/aceditor-wrapper.js'
import FlyingActionBar from './components/flying-action-bar.js'

console.log("data", data) // data global variable has been defined in bazar-actions-builder.tpl.html

// Handle oldbrowser not supporting ES6
if (!('noModule' in HTMLScriptElement.prototype)) {
  $('#bazar-actions-builder-app').empty().append('<p>Désolé, votre Navigateur est trop vieux pour utiliser cette fonctionalité.. Mettez le à jour ! ou <a href="https://www.mozilla.org/fr/firefox/new/">installez Firefox</a> </p>')
} else {

new Vue({
  el: "#bazar-actions-builder-app",
  components: { InputSegment, IconPicker, WikiCodeInput, PreviewAction },
  data: {
    formIds: data.forms,
    selectedFormId: "",
    forms: {},
    selectedForm: null,
    actions: data.actions,
    selectedActionId: "",
    values: {},
    filterGroups: [],
    actionParams: {},
    iconMapping: [],
    editor: null, // editor
  },
  computed: {
    wikiCode() {
      var result = `{{bazarliste`
      for(var key in this.actionParams) {
        result += ` ${key}="${this.actionParams[key]}"`
      }
      result += ' }}'
      return result
    },
    selectedAction() {
      return this.actions[this.selectedActionId]
    },
    iconOptions() {
      if (!this.selectedForm || !this.values.iconfield) return []
      var config = this.selectedForm.prepared.filter(e => e.id == this.values.iconfield)[0]
      return config ? config.values.label : []
    },
    isEditingExistingAction() {
      return this.editor.currentLine.match(/^\s*\{\{\s*bazar.*/g) != null
    }
  },
  methods: {
    setValue(propName, value) {
      this.values[propName] = value
      this.updateActionParams()
    },
    init() {
      let newValues = {}
      if (this.isEditingExistingAction) {
        // use a fake dom to parse wiki code attributes
        let fakeDom = this.editor.currentLine.replace(/\s*{{\s*/, '<').replace('}}', '/>')
        const attributes = $(fakeDom)[0].attributes
        for(let attribute of attributes) {
          newValues[attribute.name] = attribute.value
        }
        this.selectedFormId = newValues.id
        for(let actionId in this.actions) {
          let action = this.actions[actionId]
          if (action && action.properties && action.properties.template.value == newValues.template) {
            this.selectedActionId = actionId
          }
        }
        if (newValues.icon) {
          this.iconMapping = []
          newValues.icon.split(',').forEach(el => {
            this.iconMapping.push({icon: el.split('=')[0], id: el.split('=')[1]})
          })
        }
        if (newValues.groups) {
          this.filterGroups = []
          let groups = newValues.groups.split(',')
          let titles = newValues.titles ? newValues.titles.split(',') : []
          let icons = newValues.groupicons ? newValues.groupicons.split(',') : []
          for(var i = 0; i < groups.length; i++) {
            this.filterGroups.push({
              field: groups[i],
              title: titles.length >= i ? titles[i] : '' ,
              icon: icons.length >= i ? icons[i] : ''
            })
          }
        }
        this.values.groups = this.filterGroups.map(g => g.field).filter(e => e != "").join(',')
        this.values.titles = this.filterGroups.map(g => g.title).filter(e => e != "").join(',')
        this.values.groupicons = this.filterGroups.map(g => g.icon).filter(e => e != "").join(',')
        this.values.icon = this.iconMapping.filter(m => m.id && m.icon).map(m => `${m.icon}=${m.id}`).join(',')
        this.values = newValues
      } else {
        this.values = {}
        this.selectedFormId = ''
        this.selectedActionId = ''
      }
      this.updateActionParams();
    },
    getSelectedForm() {
      if (!this.selectedFormId) return;
      if (this.forms[this.selectedFormId]) this.selectedForm = this.forms[this.selectedFormId]
      else {
        $.getJSON(`/?root/bazar_api&object=form&id=${this.selectedFormId}`, data => {
          this.forms[this.selectedFormId] = data
          this.selectedForm = data
        })
      }
    },
    initValuesOnActionSelected() {
      if (!this.selectedActionId) return;
      // Populate the values field from the config
      for(var propName in this.selectedAction.properties) {
        var configValue = this.selectedAction.properties[propName].value
        if (configValue && !this.values[propName]) this.values[propName] = configValue
      }
      this.values.template = this.selectedAction.properties.template.value
      this.updateActionParams()
    },
    addEmptyFilterGroup() {
      this.filterGroups.push({field: '', title: '', icon: ''})
    },
    removeFilterGroup(group) {
      this.filterGroups = this.filterGroups.filter( el => el.field != group.field)
    },
    updateActionParams() {
      let result = {}
      this.values.id = this.selectedFormId
      this.values.groups = this.filterGroups.map(g => g.field).filter(e => e != "").join(',')
      this.values.titles = this.filterGroups.map(g => g.title).filter(e => e != "").join(',')
      this.values.groupicons = this.filterGroups.map(g => g.icon).filter(e => e != "").join(',')
      this.values.icon = this.iconMapping.filter(m => m.id && m.icon).map(m => `${m.icon}=${m.id}`).join(',')
      for(var key in this.values) {
        let value = this.values[key]
        if (value === false || value === "") continue
        result[key] = value
      }
      this.actionParams = result
    },
    addEmptyIconMapping() {
      this.iconMapping.push({id: '', icon: ''})
    },
    removeIconMapping(mapping) {
      this.iconMapping = this.iconMapping.filter(el => el.id != mapping.id)
    }
  },
  watch: {
    selectedFormId: function() { this.getSelectedForm() },
    selectedActionId: function() { this.initValuesOnActionSelected() },
    values: {
      handler(val){ this.updateActionParams(val) },
      deep: true
    },
    filterGroups: {
      handler(val){ this.updateActionParams(val) },
      deep: true
    },
    iconMapping: {
      handler(val){ this.updateActionParams(val) },
      deep: true
    },
  },
  mounted() {
    $(document).ready(() => {
      this.editor = new AceEditorWrapper()
      new FlyingActionBar(this.editor)
      $('.editor-btn-actions-bazar').click(() => {
        $('#bazar-actions-modal').modal('show')
        this.init()
      })
    })
    this.addEmptyIconMapping()
    this.getSelectedForm()
    this.initValuesOnActionSelected()
  }
});
}
