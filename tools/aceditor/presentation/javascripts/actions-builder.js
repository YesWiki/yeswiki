import InputHelper from './components/InputHelper.js'
import InputHidden from './components/InputHidden.js'
import InputText from './components/InputText.js'
import InputCheckbox from './components/InputCheckbox.js'
import InputList from './components/InputList.js'
import InputIcon from './components/InputIcon.js'
import InputColor from './components/InputColor.js'
import InputFormField from './components/InputFormField.js'
import InputFacette from './components/InputFacette.js'
import InputIconMapping from './components/InputIconMapping.js'
import InputColorMapping from './components/InputColorMapping.js'
import WikiCodeInput from './components/WikiCodeInput.js'
import PreviewAction from './components/PreviewAction.js'
import AceEditorWrapper from './components/aceditor-wrapper.js'
import FlyingActionBar from './components/flying-action-bar.js'

// Helper method to filter object based on their values
Object.filter = (obj, predicate) =>
    Object.keys(obj)
          .filter( key => predicate(obj[key]) )
          .reduce( (res, key) => (res[key] = obj[key], res), {} );

console.log("data", data) // data variable has been defined in actions-builder.tpl.html

// Handle oldbrowser not supporting ES6
if (!('noModule' in HTMLScriptElement.prototype)) {
  $('#actions-builder-app').empty().append('<p>Désolé, votre Navigateur est trop vieux pour utiliser cette fonctionalité.. Mettez le à jour ! ou <a href="https://www.mozilla.org/fr/firefox/new/">installez Firefox</a> </p>')
} else {

window.myapp = new Vue({
  el: "#actions-builder-app",
  components: { InputText, InputCheckbox, InputList, InputIcon, InputColor, InputFormField, InputHidden,
                InputFacette, InputIconMapping, InputColorMapping,
                WikiCodeInput, PreviewAction },
  mixins: [ InputHelper ],
  data: {
    // Available Actions
    actions: data.actions,
    selectedActionId: "",
    // Some Actions require to select a Form (like bazar actions)
    formIds: data.forms, // list of this YesWiki Forms
    selectedFormId: "",
    selectedForm: null, // used only when useFormField is present
    loadedForms: {}, // we retrive Form by ajax, and store it in case we need to get it again
    // Values
    values: {},
    actionParams: {},
    // Aceditor
    editor: null
  },
  computed: {
    needFormField() {
      return true
    },
    wikiCode() {
      let actionId = this.selectedActionId
      if (actionId.startsWith('bazar')) actionId = 'bazarliste'
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
    // Some action group (like bazar) have common properties available for each actions
    configPanels() {
      let result = [this.selectedAction]
      for(let actionName in this.actions) {
        if (actionName.startsWith('common')) result.push(this.actions[actionName])
      }
      return result
    },
    isEditingExistingAction() {
      if (!this.editor) return false;
      return this.editor.currentLine.match(/\{\{.*\}\}/g) != null
    },
    selectedActionAllConfigs() {
      let result = {}
      this.configPanels.forEach(panel => result = {...result, ...panel.properties })
      return result
    }
  },
  methods: {
    initValues() {
      this.values = {}
      if (this.isEditingExistingAction) {
        // use a fake dom to parse wiki code attributes
        let fakeDom = $(this.editor.currentLine.replace(/\s*{{\s*/, '<').replace('}}', '/>'))[0]

        for(let attribute of fakeDom.attributes) this.values[attribute.name] = attribute.value
        if (this.needFormField) {
          this.selectedFormId = this.values.id
          this.getSelectedFormByAjax()
        }

        const newActionId = fakeDom.tagName.toLowerCase()
        this.selectedActionId = newActionId
        // For bazar action, name is contained inside the template attribute
        if (newActionId == 'bazarliste') {
          for(let actionId in this.actions) {
            let action = this.actions[actionId]
            if (action && action.properties && action.properties.template && action.properties.template.value == this.values.template)
              this.selectedActionId = actionId
          }
        }
        if (this.$refs.specialInput) this.$refs.specialInput.forEach(component => component.parseNewValues(this.values))
      } else {
        if (this.$refs.specialInput) this.$refs.specialInput.forEach(component => component.resetValues())
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
          // On first form loaded, we load again the values so the special components are rendered
          if (!this.selectedForm) setTimeout(() => this.initValues(), 0)
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
    updateActionParams() {
      if (!this.selectedAction) return {}
      let result = {}
      if (this.needFormField) result.id = this.selectedFormId

      for(let key in this.values) {
        let config = this.selectedActionAllConfigs[key]
        let value = this.values[key]
        if (result.hasOwnProperty(key) || !config || value === config.default || typeof value == "object") continue
        result[key] = value
      }
      // Adds values from special components
      if (this.$refs.specialInput) this.$refs.specialInput.forEach(p => result = {...result, ...p.getValues()})

      // Order params, and remove empty values
      const orderedResult = { id: result.id, template: result.template };
      Object.keys(result).sort().forEach(key => { if (result[key] != "") orderedResult[key] = result[key] })
      this.actionParams = orderedResult
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
        this.initValues()
      })
    })
  }
});
}
