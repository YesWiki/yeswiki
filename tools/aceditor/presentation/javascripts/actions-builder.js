import InputHelper from './components/InputHelper.js'
import InputHidden from './components/InputHidden.js'
import InputText from './components/InputText.js'
import InputPageList from './components/InputPageList.js'
import InputCheckbox from './components/InputCheckbox.js'
import InputList from './components/InputList.js'
import InputDivider from './components/InputDivider.js'
import InputIcon from './components/InputIcon.js'
import InputColor from './components/InputColor.js'
import InputFormField from './components/InputFormField.js'
import InputFacette from './components/InputFacette.js'
import InputReaction from './components/InputReaction.js';
import InputIconMapping from './components/InputIconMapping.js'
import InputColorMapping from './components/InputColorMapping.js'
import InputColumnsWidth from './components/InputColumnsWidth.js'
import InputGeo from './components/InputGeo.js'
import InputClass from './components/InputClass.js'
import InputCorrespondance from './components/InputCorrespondance.js'
import WikiCodeInput from './components/WikiCodeInput.js'
import PreviewAction from './components/PreviewAction.js'
import AceEditorWrapper from './components/aceditor-wrapper.js'
import FlyingActionBar from './components/flying-action-bar.js'
import InputHint from './components/InputHint.js'
import AddonIcon from './components/AddonIcon.js'

var components = { InputPageList, InputText, InputCheckbox, InputList, InputIcon, InputColor, 
  InputFormField, InputHidden, InputDivider,
  InputFacette, InputReaction, InputIconMapping, InputColorMapping, InputGeo, InputClass, InputCorrespondance,
  InputColumnsWidth,
  WikiCodeInput, PreviewAction };
if (actionsBuilderData.hasOwnProperty('extraComponents')){
  for (const name in actionsBuilderData.extraComponents) {
    let filepath = actionsBuilderData.extraComponents[name];
    
    let {default: tmp} = await import(filepath);
    components[name]=tmp;
  }
}

const ACTIONS_BACKWARD_COMPATIBILITY = {
  calendrier: 'bazarcalendar',
  map: 'bazarcarto'
}
console.log("actionsBuilderData", actionsBuilderData) // data variable has been defined in actions-builder.tpl.html

// Declare this one globally because we use it everywhere
Vue.component('input-hint', InputHint)
Vue.component('addon-icon', AddonIcon)
Vue.component('v-select', VueSelect.VueSelect);

// Handle oldbrowser not supporting ES6
if (!('noModule' in HTMLScriptElement.prototype)) {
  $('#actions-builder-app').empty().append('<p>Désolé, votre Navigateur est trop vieux pour utiliser cette fonctionalité.. Mettez le à jour ! ou <a href="https://www.mozilla.org/fr/firefox/new/">installez Firefox</a> </p>')
} else {

window.myapp = new Vue({
  el: "#actions-builder-app",
  components: components,
  mixins: [ InputHelper ],
  data: {
    // Available Actions
    actionGroups: actionsBuilderData.action_groups,
    currentGroupId: '',
    selectedActionId: "",
    // Some Actions require to select a Form (like bazar actions)
    formIds: actionsBuilderData.forms, // list of this YesWiki Forms
    selectedFormsIds: "",
    selectedForms: null, // used only when useFormField is present
    loadedForms: {}, // we retrive Form by ajax, and store it in case we need to get it again
    loadingForms: [],
    // Values
    values: {},
    actionParams: {},
    // Aceditor
    editor: null,
    displayAdvancedParams: false,
  },
  computed: {
    actionGroup() { return this.currentGroupId ? this.actionGroups[this.currentGroupId] : {} },
    actions() { return this.actionGroup.actions || {} },
    selectedAction() { return this.actions[this.selectedActionId] },
    needFormField() { return this.actionGroup.needFormField },
    // Some action group (like bazar) have common properties available for each actions
    // so we always display those commons properties in different panels
    configPanels() {
      let result = []
      if (this.selectedAction.properties && Object.values(this.selectedAction.properties).some(conf => conf.type)) {
        result.push({params: this.selectedAction, class: 'specific-action-params'})
      }
      for(let actionName in this.actions) {
        if (actionName.startsWith('common')) result.push({params: this.actions[actionName]})
      }
      return result
    },
    isSomeAdvancedParams() {
      return this.configPanels.some(panel => {
        let props = Object.values(panel.params.properties)
        return props.some(prop => prop.advanced)
      })
    },
    // Are we editing an action or creating a new one?
    isEditingExistingAction() {
      if (!this.editor) return false;
      return this.editor.currentSelectedAction != ""
    },
    isBazarListeAction(){
      return this.currentGroupId == 'bazarliste'
    },
    selectedActionAllConfigs() {
      let result = {}
      this.configPanels.forEach(panel => result = {...result, ...panel.params.properties })
      return result
    },
    wikiCodeBase() {
      let actionId = this.selectedActionId
      if (this.isBazarListeAction) actionId = 'bazarliste'
      var result = `{{${actionId}`
      for(var key in this.actionParams) {
        result += ` ${key}="${this.actionParams[key]}"`
      }
      result += ' }}'
      return result
    },
    wikiCode() {
      let result = this.wikiCodeBase;
      if (this.selectedAction.isWrapper && !this.isEditingExistingAction) {
        result += `\n${this.selectedAction.wrappedContentExample}{{end elem="${this.selectedActionId}"}}`
      }
      return result
    },
    wikiCodeForIframe() {
      let result = this.wikiCodeBase;
      if (this.selectedAction.isWrapper && result) {
        result += `${this.selectedAction.wrappedContentExample}\n`
        result += `{{end elem="${this.selectedActionId}"}}`
      }
      return result
    }
  },
  methods: {
    initValues() {
      this.values = {}
      this.actionParams = {}
      if (this.isEditingExistingAction) {
        // use a fake dom to parse wiki code attributes
        let fakeDom = $(`<${this.editor.currentSelectedAction}/>`)[0]

        for(let attribute of fakeDom.attributes) Vue.set(this.values, attribute.name, attribute.value)

        let newActionId = fakeDom.tagName.toLowerCase()
        // backward compatibilty
        if (newActionId in ACTIONS_BACKWARD_COMPATIBILITY) newActionId = ACTIONS_BACKWARD_COMPATIBILITY[newActionId]
        this.selectedActionId = newActionId
        // Get Action group
        for(let groupId in this.actionGroups) {
          if (Object.keys(this.actionGroups[groupId].actions).includes(newActionId)) {
            this.currentGroupId = groupId
            break
          }
        }
        // Get Form if needed
        if (this.needFormField) {
          if (!this.selectedFormsIds){
            this.selectedFormsIds = this.getValidFormsIds()
          }
          this.getSelectedFormsByAjax()
        }

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
        this.selectedFormsIds = null
        this.selectedActionId = ''
        // Bazar dynamic by default
        if (this.isBazarListeAction) Vue.set(this.values, "dynamic", true)
      }
      this.updateActionParams()
      // If only one action available, select it
      if (Object.keys(this.actions).length == 1) this.selectedActionId = Object.keys(this.actions)[0]
    },
    // prefer methods to computed to prevent cache
    getSelectedFormId(){
      return (this.selectedFormsIds) ? this.selectedFormsIds.slice(0,1)[0] : ""; // only the first one
    },
    setSelectedFormId() {
      let newValue = this.$refs.formSelection.value
      if (["number","string"].includes(typeof newValue)){
        if (this.selectedFormsIds){
          this.selectedFormsIds[0] = newValue
        } else {
          this.selectedFormsIds = [newValue]
        }
        this.getSelectedFormsByAjax()
      }
    },
    getValidFormsIds(){
      let fieldIds = this.values.id.split(',');
      return fieldIds
        .filter((id) => ["number","string"].includes(typeof id))
        .map(
          (id) => id.replace(
              /(^[0-9]$)|^https?:\/\/.+->([0-9]+)$/u,
              "$1$2"
            )
        )
        .filter((e) => e.match(/^\d+$/));
    },

    getSelectedFormsByAjax() {
      let selectedFormId = this.getSelectedFormId();
      if (!this.selectedFormsIds) return;
      if (this.selectedFormsIds.every((fid)=>this.loadedForms.hasOwnProperty(fid)))
      {
        this.selectedForms = {}
        for (const key in this.loadedForms) {
          this.selectedForms[key] = this.loadedForms[key]
        }
        if (this.selectedAction){
          // action choosen updateActionParams
          setTimeout(() => this.updateActionParams(), 0);
        }
      }
      else {
        let idsToSearch = this.selectedFormsIds.filter((fid)=>{
          return !this.loadedForms.hasOwnProperty(fid) && !this.loadingForms.includes(fid);
        });
        if (idsToSearch.length > 0){
          idsToSearch.forEach((id)=>this.loadingForms.push(id))
          let params = {
            demand: 'forms'
          };
          if (idsToSearch.length == 1){
            params['id'] = idsToSearch[0];
          } else {
            idsToSearch.forEach((id,index)=>{
              params[`id[${index}]`] = id;
            });
          }
          $.getJSON(wiki.url('?root/json', params), data => {
            this.loadingForms = this.loadingForms.filter((e)=>!idsToSearch.includes(e))
              // keep ? because standart http rewrite waits for CamelCase and 'root' is not
            if (Array.isArray(data) && data[0] != undefined){
              // copy forms
              data.forEach((form) => {
                if (form.bn_id_nature != undefined && idsToSearch.includes(form.bn_id_nature)){
                  this.loadedForms[form.bn_id_nature] = form;
                }
              });
            }
            // default forms for missing
            idsToSearch.forEach((fid) => {
              // fake empty form
              if (!this.loadedForms.hasOwnProperty(fid)){
                this.loadedForms[fid] = {prepared:{}};
              }
            });
            // On first form loaded, we load again the values so the special components are rendered and we can parse values on each special component
            if (!this.selectedForms && this.isEditingExistingAction) setTimeout(() => this.initValues(), 0)
            this.selectedForms = {}
            for (const key in this.loadedForms) {
              if (this.selectedFormsIds && this.selectedFormsIds.includes(key)){
                this.selectedForms[key] = this.loadedForms[key]
              }
            }
            if (this.selectedAction){
              // action choosen updateActionParams
              setTimeout(() => this.updateActionParams(), 0);
            }
          })
        }
      }
    },
    updateValue(propName, value) {
      this.values[propName] = value
      this.updateActionParams()
    },
    initValuesOnActionSelected() {
      if (!this.selectedAction) return;
      // Populate the values field from the config
      for(var propName in this.selectedAction.properties) {        
        var configValue = this.selectedAction.properties[propName].value || this.selectedAction.properties[propName].default
        if (configValue && !this.values[propName]) Vue.set(this.values, propName, configValue)
      }
      if (this.isBazarListeAction && this.selectedAction.properties && this.selectedAction.properties.template) this.values.template = this.selectedAction.properties.template.value
      setTimeout(() => this.updateActionParams(), 0);
    },
    updateActionParams() {
      if (!this.selectedAction) return
      let result = {}
      if (this.needFormField) {
        if (this.values.id){
          let ids = this.values.id.split(',').slice(1);
          ids.unshift(this.getSelectedFormId());
          result.id = ids.join(',')
        } else {
          result.id = this.getSelectedFormId()
        }
      }

      for(let key in this.values) {
        let config = this.selectedActionAllConfigs[key]
        let value = this.values[key]
        if (result.hasOwnProperty(key) || value === undefined || config && config.default && `${value}` == `${config.default}`
            || typeof value == "object" || config && !this.checkConfigDisplay(config) ) 
          continue
        result[key] = value
      }
      // Adds values from special components
      if (this.$refs.specialInput) this.$refs.specialInput.forEach(p => result = {...result, ...p.getValues()})

      // default value for 'bazarliste'
      if (this.selectedActionId == 'bazarliste') result.template = result.template || 'liste_accordeon'
      
      // put in first position 'id' and 'template' if existing
      const orderedResult = {}
      if (result.id) orderedResult['id'] = result.id
      if (result.template) orderedResult['template'] = result.template
      // Order params, and remove empty values
      Object.keys(result).sort().forEach(key => { if (result[key] !== "") orderedResult[key] = result[key] })
      this.actionParams = orderedResult
    },
    actionGroupsWithBackwardCompatibility() {
      let actionGroupsWithBackwardCompatibility = this.actionGroups
      if (actionGroupsWithBackwardCompatibility.bazarliste.actions){
        for(let key in ACTIONS_BACKWARD_COMPATIBILITY){
          actionGroupsWithBackwardCompatibility.bazarliste.actions[key] = key
        }
      }
      return actionGroupsWithBackwardCompatibility
    }
  },
  watch: {
    selectedFormsIds:function (val, oldVal) {
      if (!oldVal || (val && (oldVal.length != val.length ||
        (Array.isArray(val) && !Array.isArray(oldVal)) ||
        !val.every((e)=>oldVal.includes(e))
        ))){
        this.getSelectedFormsByAjax()
      }
    },
    selectedActionId: function() { 
      if (!this.isBazarListeAction && !this.isEditingExistingAction){
        this.values = {};
      }
      this.initValuesOnActionSelected();
    }
  },
  mounted() {
    $(document).ready(() => {
      this.editor = new AceEditorWrapper()
      new FlyingActionBar(this.editor, this.actionGroupsWithBackwardCompatibility())
      $('.open-actions-builder-btn').click((event) => {
        $('#actions-builder-modal').modal('show')
        this.currentGroupId = $(event.target).data('group-name')
        setTimeout(() => this.initValues(), 0)
      })
    })
  }
});
}
