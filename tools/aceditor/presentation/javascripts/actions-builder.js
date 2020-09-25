import InputHidden from './components/InputHidden.js'
import InputText from './components/InputText.js'
import InputCheckbox from './components/InputCheckbox.js'
import InputList from './components/InputList.js'
import InputIcon from './components/InputIcon.js'
import InputFacette from './components/InputFacette.js'
import InputIconMapping from './components/InputIconMapping.js'
import WikiCodeInput from './components/WikiCodeInput.js'
import PreviewAction from './components/PreviewAction.js'
import AceEditorWrapper from './components/aceditor-wrapper.js'
import FlyingActionBar from './components/flying-action-bar.js'

console.log("data", data) // data variable has been defined in actions-builder.tpl.html

// Handle oldbrowser not supporting ES6
if (!('noModule' in HTMLScriptElement.prototype)) {
  $('#actions-builder-app').empty().append('<p>Désolé, votre Navigateur est trop vieux pour utiliser cette fonctionalité.. Mettez le à jour ! ou <a href="https://www.mozilla.org/fr/firefox/new/">installez Firefox</a> </p>')
} else {

new Vue({
  el: "#actions-builder-app",
  components: { InputText, InputCheckbox, InputList, InputIcon, InputFacette, InputIconMapping, InputHidden,
                WikiCodeInput, PreviewAction },
  data: {
    // Available Actions
    actions: data.actions,
    selectedActionId: "test",
    // Some Actions require to select a Form (like bazar actions)
    formIds: data.forms, // list of this YesWiki Forms
    selectedFormId: "2",
    selectedForm: null, // used only when useFormField is present
    loadedForms: {}, // we retrive Form by ajax, and store it in case we need to get it again
    // Values
    values: {},
    filterGroups: [],
    actionParams: {},
    iconMapping: [],
    editor: null, // Aceditor
  },
  computed: {
    needFormField() {
      return true
    },
    wikiCode() {
      let actionId = this.selectedActionId
      if (actionId.match(/^bazar.*/g) != null) actionId = 'bazarliste'
      var result = `{{${actionId}`
      for(var key in this.actionParams) {
        result += ` ${key}="${this.actionParams[key]}"`
      }
      result += ' }}'
      return result
    },
    selectedAction() {
      return this.actions[this.selectedActionId]
    },
    isEditingExistingAction() {
      if (!this.editor) return false;
      return this.editor.currentLine.match(/\{\{.*\}\}/g) != null
    }
  },
  methods: {
    init() {
      let newValues = {}
      if (this.isEditingExistingAction) {
        // use a fake dom to parse wiki code attributes
        let fakeDom = this.editor.currentLine.replace(/\s*{{\s*/, '<').replace('}}', '/>')
        const newActionId = 'bazarliste' // TODO read from fakeDom -> fakeDom.tag ?
        const attributes = $(fakeDom)[0].attributes
        for(let attribute of attributes) {
          newValues[attribute.name] = attribute.value
        }
        this.selectedFormId = newValues.id

        this.selectedActionId = newActionId
        // For bazar action, name is contained inside the template attribute
        if (newActionId == 'bazarliste') {
          for(let actionId in this.actions) {
            let action = this.actions[actionId]
            if (action && action.properties && action.properties.template && action.properties.template.value == newValues.template) {
              this.selectedActionId = actionId
            }
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
    getSelectedFormByAjax() {
      if (!this.selectedFormId) return;
      if (this.loadedForms[this.selectedFormId]) this.selectedForm = this.loadedForms[this.selectedFormId]
      else {
        $.getJSON(`/?root/bazar_api&object=form&id=${this.selectedFormId}`, data => {
          this.loadedForms[this.selectedFormId] = data
          this.selectedForm = data
        })
      }
    },
    initValuesOnActionSelected() {
      if (!this.selectedAction) return;
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
      if (!this.selectedAction) return {}
      let result = {}
      this.values.id = this.selectedFormId
      this.values.groups = this.filterGroups.map(g => g.field).filter(e => e != "").join(',')
      this.values.titles = this.filterGroups.map(g => g.title).filter(e => e != "").join(',')
      this.values.groupicons = this.filterGroups.map(g => g.icon).filter(e => e != "").join(',')
      this.values.icon = this.iconMapping.filter(m => m.id && m.icon).map(m => `${m.icon}=${m.id}`).join(',')
      for(var key in this.values) {
        let config = this.selectedAction.properties[key]
        let value = this.values[key]
        if (!config || value === config.default || value === "") continue
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
    selectedFormId: function() { this.getSelectedFormByAjax() },
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
    // Add a fake selected Form when we do not need it
    if (!this.needFormField) this.selectedForm = { prepared: [] }

    $(document).ready(() => {
      this.editor = new AceEditorWrapper()
      new FlyingActionBar(this.editor)
      $('.open-actions-builder-btn').click(() => {
        $('#actions-builder-modal').modal('show')
        this.init()
      })
    })
    this.addEmptyIconMapping()
    this.getSelectedFormByAjax()
    this.initValuesOnActionSelected()
  }
});
}
