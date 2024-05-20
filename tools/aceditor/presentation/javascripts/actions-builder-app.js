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
import InputReaction from './components/InputReaction.js'
import InputIconMapping from './components/InputIconMapping.js'
import InputColorMapping from './components/InputColorMapping.js'
import InputColumnsWidth from './components/InputColumnsWidth.js'
import InputGeo from './components/InputGeo.js'
import InputClass from './components/InputClass.js'
import InputCorrespondance from './components/InputCorrespondance.js'
import WikiCodeInput from './components/WikiCodeInput.js'
import PreviewAction from './components/PreviewAction.js'
import InputHint from './components/InputHint.js'
import AddonIcon from './components/AddonIcon.js'

const components = {
  InputPageList,
  InputText,
  InputCheckbox,
  InputList,
  InputIcon,
  InputColor,
  InputFormField,
  InputHidden,
  InputDivider,
  InputFacette,
  InputReaction,
  InputIconMapping,
  InputColorMapping,
  InputGeo,
  InputClass,
  InputCorrespondance,
  InputColumnsWidth,
  WikiCodeInput,
  PreviewAction
}

// actionsBuilderData is defined is AceditorAction
const data = typeof actionsBuilderData === 'object' ? actionsBuilderData : { forms: {}, action_groups: {} }

// dynamically loads other components defined in extensions or in custom folder
if (data.extraComponents) {
  Object.entries(data.extraComponents).forEach(async([name, filepath]) => {
    const { default: tmp } = await import(filepath)
    components[name] = tmp
  })
}

export function setup() {
  // Declare this one globally because we use it everywhere
  Vue.component('input-hint', InputHint)
  Vue.component('addon-icon', AddonIcon)
  Vue.component('v-select', VueSelect.VueSelect)
}

export const app = {
  el: '#actions-builder-app',
  components,
  mixins: [InputHelper],
  data: {
    // Available Actions
    actionGroups: data.action_groups,
    currentGroupId: '',
    selectedActionId: '',
    // Some Actions require to select a Form (like bazar actions)
    formIds: data.forms, // list of this YesWiki Forms
    selectedFormsIds: '',
    selectedForms: null, // used only when useFormField is present
    loadedForms: {}, // we retrive Form by ajax, and store it in case we need to get it again
    loadingForms: [],
    // Values
    values: {},
    actionParams: {},
    isEditingExistingAction: false,
    displayAdvancedParams: false,
    // Current Aceditor in use
    editor: null
  },
  computed: {
    actionGroup() { return this.currentGroupId ? this.actionGroups[this.currentGroupId] : {} },
    actions() { return this.actionGroup.actions || {} },
    selectedAction() { return this.actions[this.selectedActionId] },
    needFormField() { return this.actionGroup.needFormField },
    // Some action group (like bazar) have common properties available for each actions
    // so we always display those commons properties in different panels
    configPanels() {
      const result = []
      if (this.selectedAction.properties
          && Object.values(this.selectedAction.properties).some((conf) => conf.type)) {
        result.push({ params: this.selectedAction, class: 'specific-action-params' })
      }
      Object.entries(this.actions).forEach(([actionName, params]) => {
        if (actionName.startsWith('common')) result.push({ params })
      })
      return result
    },
    isSomeAdvancedParams() {
      return this.configPanels.some((panel) => {
        const props = Object.values(panel.params.properties)
        return props.some((prop) => prop.advanced)
      })
    },
    isBazarListeAction() {
      return this.currentGroupId === 'bazarliste'
    },
    selectedActionAllConfigs() {
      let result = {}
      this.configPanels.forEach((panel) => { result = { ...result, ...panel.params.properties } })
      return result
    },
    wikiCodeBase() {
      let actionId = this.selectedActionId
      if (this.isBazarListeAction) actionId = 'bazarliste'
      let result = `{{${actionId}`
      for (const key in this.actionParams) {
        result += ` ${key}="${this.actionParams[key]}"`
      }
      result += ' }}'
      return result
    },
    wikiCode() {
      let result = this.wikiCodeBase
      if (this.selectedAction.isWrapper && !this.isEditingExistingAction) {
        result += `\n${this.selectedAction.wrappedContentExample}{{end elem="${this.selectedActionId}"}}`
      }
      return result
    },
    wikiCodeForIframe() {
      let result = this.wikiCodeBase
      if (this.selectedAction.isWrapper && result) {
        result += `${this.selectedAction.wrappedContentExample}\n`
        result += `{{end elem="${this.selectedActionId}"}}`
      }
      return result
    }
  },
  methods: {
    open(editor, options) {
      this.editor = editor
      $('#actions-builder-modal').modal('show')
      this.currentGroupId = options.groupName
      this.currentSelectedAction = options.action
      this.isEditingExistingAction = !!options.action
      setTimeout(() => this.initValues(), 0)
    },
    initValues() {
      this.values = {}
      this.actionParams = {}
      const previousSelectedActionId = this.selectedActionId
      if (this.isEditingExistingAction) {
        // use a fake dom to parse wiki code attributes
        const fakeDom = $(`<${this.currentSelectedAction}/>`)[0]

        for (const attribute of fakeDom.attributes) Vue.set(this.values, attribute.name, attribute.value)

        const newActionId = fakeDom.tagName.toLowerCase()
        this.selectedActionId = newActionId
        // Get Action group
        for (const groupId in this.actionGroups) {
          if (Object.keys(this.actionGroups[groupId].actions).includes(newActionId)) {
            this.currentGroupId = groupId
            break
          }
        }
        // Get Form if needed
        if (this.needFormField) {
          if (!this.selectedFormsIds) {
            this.selectedFormsIds = this.getValidFormsIds()
          }
          this.getSelectedFormsByAjax()
        }

        // For bazar action, name is contained inside the template attribute
        if (newActionId == 'bazarliste') {
          for (const actionId in this.actions) {
            const action = this.actions[actionId]
            if (action && action.properties && action.properties.template && action.properties.template.value == this.values.template) { this.selectedActionId = actionId }
          }
        }
        if (this.$refs.specialInput) this.$refs.specialInput.forEach((component) => component.parseNewValues(this.values))
      } else {
        if (this.$refs.specialInput) this.$refs.specialInput.forEach((component) => component.resetValues())
        this.selectedFormsIds = null
        this.selectedActionId = ''
        // Bazar dynamic by default
        if (this.isBazarListeAction) Vue.set(this.values, 'dynamic', true)
      }
      this.updateActionParams()
      // If only one action available, select it
      if (Object.keys(this.actions).length == 1) {
        if (this.selectedActionId == '' && previousSelectedActionId == Object.keys(this.actions)[0]) {
          this.selectedActionId = Object.keys(this.actions)[0]
          // force watcher without changing value because VueJs will not detect the change
          // The comparison between changes is done at regular interval, so there will not have detection
          // of change if the value retrieve its previous value before the end of the interval
          this.watchSelectedActionId()
        } else {
          this.selectedActionId = Object.keys(this.actions)[0]
        }
      }
    },
    // prefer methods to computed to prevent cache
    getSelectedFormId() {
      return (this.selectedFormsIds) ? this.selectedFormsIds.slice(0, 1)[0] : '' // only the first one
    },
    setSelectedFormId() {
      const newValue = this.$refs.formSelection.value
      if (['number', 'string'].includes(typeof newValue)) {
        if (this.selectedFormsIds) {
          this.selectedFormsIds[0] = newValue
        } else {
          this.selectedFormsIds = [newValue]
        }
        this.getSelectedFormsByAjax()
      }
    },
    getValidFormsIds() {
      return (this.values.id || '').split(',')
        .filter((id) => ['number', 'string'].includes(typeof id))
        .map((id) => id.replace(/(^[0-9]$)|^https?:\/\/.+->([0-9]+)$/u, '$1$2'))
        .filter((e) => e.match(/^\d+$/))
    },
    getSelectedFormsByAjax() {
      if (!this.selectedFormsIds) return
      if (this.selectedFormsIds.every((fid) => this.loadedForms.hasOwnProperty(fid))) {
        this.selectedForms = {}
        for (const key in this.loadedForms) {
          this.selectedForms[key] = this.loadedForms[key]
        }
        if (this.selectedAction) {
          // action choosen updateActionParams
          setTimeout(() => this.updateActionParams(), 0)
        }
      } else {
        const idsToSearch = this.selectedFormsIds.filter((fid) => !this.loadedForms.hasOwnProperty(fid) && !this.loadingForms.includes(fid))
        if (idsToSearch.length > 0) {
          idsToSearch.forEach((id) => this.loadingForms.push(id))
          const params = { demand: 'forms' }
          if (idsToSearch.length == 1) {
            params.id = idsToSearch[0]
          } else {
            idsToSearch.forEach((id, index) => {
              params[`id[${index}]`] = id
            })
          }
          $.getJSON(wiki.url('?wiki/json', params), (data) => {
            this.loadingForms = this.loadingForms.filter((e) => !idsToSearch.includes(e))
            // keep ? because standart http rewrite waits for CamelCase and 'root' is not
            if (Array.isArray(data) && data[0] != undefined) {
              // copy forms
              data.forEach((form) => {
                if (form.bn_id_nature != undefined && idsToSearch.includes(form.bn_id_nature)) {
                  this.loadedForms[form.bn_id_nature] = form
                }
              })
            }
            // default forms for missing
            idsToSearch.forEach((fid) => {
              // fake empty form
              if (!this.loadedForms.hasOwnProperty(fid)) {
                this.loadedForms[fid] = { prepared: {} }
              }
            })
            // On first form loaded, we load again the values so the special components are rendered and we can parse values on each special component
            if (!this.selectedForms && this.isEditingExistingAction) setTimeout(() => this.initValues(), 0)
            this.selectedForms = {}
            for (const key in this.loadedForms) {
              if (this.selectedFormsIds && this.selectedFormsIds.includes(key)) {
                this.selectedForms[key] = this.loadedForms[key]
              }
            }
            if (this.selectedAction) {
              // action choosen updateActionParams
              setTimeout(() => this.updateActionParams(), 0)
            }
          })
        }
      }
    },
    updateValue(propName, value) {
      Vue.set(this.values, propName, value)
      this.updateActionParams()
    },
    initValuesOnActionSelected() {
      if (!this.selectedAction) return
      // Populate the values field from the config
      for (const propName in this.selectedAction.properties) {
        // if editing, do not fill value with value when `!default == true`, use only default
        const configValue = this.isEditingExistingAction
          ? this.selectedAction.properties[propName].default
          : (this.selectedAction.properties[propName].value || this.selectedAction.properties[propName].default)
        if (configValue && !this.values[propName]) Vue.set(this.values, propName, configValue)
      }
      if (this.isBazarListeAction && this.selectedAction.properties && this.selectedAction.properties.template) this.values.template = this.selectedAction.properties.template.value
      setTimeout(() => this.updateActionParams(), 0)
    },
    updateActionParams() {
      if (!this.selectedAction) return
      let result = {}
      if (this.needFormField) {
        if (this.values.id) {
          const ids = this.values.id.split(',').slice(1)
          ids.unshift(this.getSelectedFormId())
          result.id = ids.join(',')
        } else {
          result.id = this.getSelectedFormId()
        }
      }

      for (const key in this.values) {
        const config = this.selectedActionAllConfigs[key]
        const value = this.values[key]
        if (result.hasOwnProperty(key) || value === undefined || config && config.default && `${value}` == `${config.default}`
            || typeof value == 'object' || config && !this.checkConfigDisplay(config)) { continue }
        result[key] = value
      }
      // Adds values from special components
      if (this.$refs.specialInput) this.$refs.specialInput.forEach((p) => result = { ...result, ...p.getValues() })

      // default value for 'bazarliste'
      if (this.selectedActionId == 'bazarliste') result.template = result.template || 'liste_accordeon'

      // put in first position 'id' and 'template' if existing
      const orderedResult = {}
      if (result.id) orderedResult.id = result.id
      if (result.template) orderedResult.template = result.template
      // Order params, and remove empty values
      Object.keys(result).sort().forEach((key) => { if (result[key] !== '') orderedResult[key] = result[key] })
      this.actionParams = orderedResult
    },
    watchSelectedActionId() {
      if (!this.isBazarListeAction && !this.isEditingExistingAction) {
        this.values = {}
      }
      this.initValuesOnActionSelected()
    }
  },
  watch: {
    selectedFormsIds(val, oldVal) {
      if (!oldVal || (val && (oldVal.length != val.length
        || (Array.isArray(val) && !Array.isArray(oldVal))
        || !val.every((e) => oldVal.includes(e))
      ))) {
        this.getSelectedFormsByAjax()
      }
    },
    selectedActionId() {
      this.watchSelectedActionId()
    }
  }
}
