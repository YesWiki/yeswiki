import { legacyIconToSprite } from './yw-icon-map.js'
import { claimRailSlot, registerRail } from './editor-rails.js'
import InputHelper from './components/InputHelper.js'
import InputHidden from './components/InputHidden.js'
import InputText from './components/InputText.js'
import InputPageList from './components/InputPageList.js'
import InputEntryList from './components/InputEntryList.js'
import InputNavLinks from './components/InputNavLinks.js'
import InputCheckbox from './components/InputCheckbox.js'
import InputList from './components/InputList.js'
import InputDivider from './components/InputDivider.js'
import InputIcon from './components/InputIcon.js'
import InputColor from './components/InputColor.js'
import InputFormField from './components/InputFormField.js'
import InputFacets from './components/InputFacets.js'
import InputSortFields from './components/InputSortFields.js'
import InputReaction from './components/InputReaction.js'
import InputIconMapping from './components/InputIconMapping.js'
import InputColorMapping from './components/InputColorMapping.js'
import InputColumnsWidth from './components/InputColumnsWidth.js'
import InputGeo from './components/InputGeo.js'
import InputClass from './components/InputClass.js'
import InputFieldMapping from './components/InputFieldMapping.js'
import PreviewAction from './components/PreviewAction.js'
import InputHint from './components/InputHint.js'
import AddonIcon from './components/AddonIcon.js'

const components = {
  InputPageList,
  InputEntryList,
  InputText,
  InputCheckbox,
  InputList,
  InputIcon,
  InputColor,
  InputFormField,
  InputHidden,
  InputDivider,
  InputFacets,
  InputSortFields,
  InputReaction,
  InputIconMapping,
  InputColorMapping,
  InputNavLinks,
  InputGeo,
  InputClass,
  InputFieldMapping,
  InputColumnsWidth,
  PreviewAction,
  InputHint,
  AddonIcon
}

// actionsBuilderData is defined is AceditorAction
const data = typeof actionsBuilderData === 'object' ? actionsBuilderData : { forms: {}, action_groups: {} }

/**
 * A sprite icon per action group, for the palette. Kept here rather than in the group's
 * own YAML: a group is documentation an extension ships, and asking every extension
 * author to name an icon before their actions can be listed would be a worse trade than
 * a shared fallback. An unmapped group gets one, and still appears.
 */
const PALETTE_ICONS = {
  'advanced-actions': 'tool',
  bazar: 'square-plus',
  buttons: 'external-link',
  contact: 'mail',
  entrylist: 'layout-grid',
  management: 'settings',
  reactions: 'thumb-up',
  syndication: 'rss',
  tags: 'tags',
  templates: 'brush',
  textsearch: 'loupe',
  video: 'player-play'
}

const paletteIcon = (groupId) => legacyIconToSprite(PALETTE_ICONS[groupId] || 'stack-2') || ''

// dynamically loads other components defined in extensions or in custom folder
if (data.extraComponents) {
  Object.entries(data.extraComponents).forEach(async([name, filepath]) => {
    const { default: tmp } = await import(filepath)
    components[name] = tmp
  })
}

export function setup(vueApp) {
  // Vue 3: Register global components on the app instance
  vueApp.component('input-hint', InputHint)
  vueApp.component('addon-icon', AddonIcon)
  vueApp.component('v-select', window['vue-select'])
}

export const appConfig = {
  components,
  mixins: [InputHelper],
  data() {
    return {
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
      // 'palette' to pick a component, 'settings' to configure the one that was picked
      view: 'palette',
      paletteFilter: '',
      isOpen: false,
      // which component the rail is on, so that a caller can ask for the same one twice
      openKey: null,
      // where a component that is not in the document yet should be written
      insertAt: null,
      // and where the one it IS on is written, which is what an update rewrites
      target: null,
      // Current Aceditor in use
      editor: null
    }
  },
  computed: {
    /**
     * True while the rail is placing a component the document does not contain yet. The
     * cursor is then free to move without the rail following it: what is on screen was
     * asked for from the toolbar, and only inserting or closing it ends that.
     */
    isPlacingNewAction() {
      return this.isOpen && !this.isEditingExistingAction
    },
    actionGroup() {
      if (!this.currentGroupId) return { label: '', actions: {} }
      return this.actionGroups[this.currentGroupId] || { label: '', actions: {} }
    },
    /**
     * What the rail is on, for its header: the component. The group it belongs to is how
     * the palette is arranged, not what is being configured -- and with the component
     * decided before this panel shows, naming the drawer instead of the thing in it left
     * every button's settings titled "Buttons".
     */
    railTitle() {
      return this.selectedAction?.label || this.actionGroup?.label || ''
    },
    /**
     * Read from `wiki.lang` rather than translated in the template: both keys live in the
     * javascript catalog (src/lang/yeswikijs_*.php), which is the one the browser reads
     * and the one Twig's `_t()` does not see.
     */
    submitLabel() {
      return this.isEditingExistingAction
        ? wiki.lang.ACTION_BUILDER_UPDATE_CODE
        : wiki.lang.ACTION_BUILDER_INSERT_CODE
    },
    /**
     * The palette: every component that can be inserted, under its group's heading and
     * narrowed by the filter box. Flat rather than group-then-action, because "which
     * group is a calendar in" is a question nobody should have to answer -- picking an
     * item names both.
     *
     * `onlyEdit` groups (attach, pdf) describe actions you reach by putting the cursor
     * in one, never by inserting a new one; they are not offered. Nor are the `common*`
     * pseudo-actions, which are shared parameter panels rather than components.
     */
    paletteGroups() {
      const needle = this.paletteFilter.trim().toLowerCase()
      const matches = (text) => !needle || String(text).toLowerCase().includes(needle)

      return Object.entries(this.actionGroups)
        .filter(([, group]) => group && !group.onlyEdit)
        .map(([groupId, group]) => ({
          id: groupId,
          label: group.label,
          icon: paletteIcon(groupId),
          actions: Object.entries(group.actions || {})
            .filter(([actionId, action]) => action && action.label && !actionId.startsWith('common'))
            .filter(([, action]) => matches(action.label) || matches(group.label))
            .map(([actionId, action]) => ({ id: actionId, label: action.label }))
        }))
        .filter((group) => group.actions.length > 0)
    },
    actions() {
      const actions = this.actionGroup?.actions || {}
      // Filter out any undefined values that could cause template errors
      const result = {}
      Object.entries(actions).forEach(([key, value]) => {
        if (value) result[key] = value
      })
      return result
    },
    selectedAction() { return this.actions[this.selectedActionId] },
    needFormField() { return this.actionGroup?.needFormField },
    // Some action group (like bazar) have common properties available for each actions
    // so we always display those commons properties in different panels
    configPanels() {
      const result = []
      if (this.selectedAction?.properties
          && Object.values(this.selectedAction.properties).some((conf) => conf.type)) {
        result.push({ params: this.selectedAction, class: 'specific-action-params' })
      }
      Object.entries(this.actions).forEach(([actionName, params]) => {
        if (actionName.startsWith('common') && params) result.push({ params })
      })
      return result
    },
    isSomeAdvancedParams() {
      return this.configPanels.some((panel) => {
        const props = Object.values(panel.params?.properties || {})
        return props.some((prop) => prop?.advanced)
      })
    },
    isEntryListAction() {
      return this.currentGroupId === 'entrylist'
    },
    selectedActionAllConfigs() {
      let result = {}
      this.configPanels.forEach((panel) => {
        result = { ...result, ...(panel.params?.properties || {}) }
      })
      return result
    },
    wikiCodeStart() {
      let actionId = this.selectedActionId
      if (this.isEntryListAction) actionId = 'entrylist'
      let result = `{{${actionId}`
      for (const key in this.actionParams) {
        result += ` ${key}="${this.actionParams[key]}"`
      }
      result += ' }}'
      return result
    },
    wikiCodeDefaultContent() {
      const wrappedContent = this.selectedAction?.wrappedContentExample || ''
      let content = wrappedContent
      if (this.selectedActionId === 'tabs' && this.actionParams.titles) {
        content = this.actionParams.titles.split(',')
          .map((tabName) => wrappedContent.replace('{tabName}', tabName))
          .join('\n')
      }
      if (this.selectedActionId === 'accordion') {
        content = '\n'
        for (let i = 0; i < this.values.nb; i++) {
          content += `${wrappedContent}\n`
        }
      }
      if (this.selectedActionId === 'grid') {
        content = '\n'
        const size = 12 / this.values.nb
        for (let i = 0; i < this.values.nb; i++) {
          content += `${wrappedContent.replace('{size}', size)}\n`
        }
      }
      if (!['label', 'accordion', 'grid'].includes(this.selectedActionId)) content = `\n${content}\n`
      return content
    },
    wikiCodeEnd() {
      return `{{end elem="${this.selectedActionId}"}}`
    },
    wikiCode() {
      let result = this.wikiCodeStart
      if (this.selectedAction?.isWrapper && !this.isEditingExistingAction) {
        result += this.wikiCodeDefaultContent
        result += this.wikiCodeEnd
      }
      return result
    },
    wikiCodeForIframe() {
      let result = this.wikiCodeStart
      if (this.selectedAction?.isWrapper && result) {
        result += this.wikiCodeDefaultContent
        result += this.wikiCodeEnd
      }
      return result
    }
  },
  methods: {
    open(editor, options) {
      this.editor = editor
      // refreshed even when the panel below is left alone: both are places in a document
      // that moves as it is edited, and the caller recomputes them on every cursor change
      this.target = options.target || null
      this.insertAt = options.insertAt || null
      // the component already on screen: leave it be. The cursor re-asks for it on every
      // move inside it, and rebuilding the settings under the user would lose what they
      // have typed into them
      if (this.isOpen && options.key && options.key === this.openKey) return
      this.openKey = options.key || null
      // the tint in the text means "this is what the panel is on": nothing is, when the
      // panel is about to place a component the document does not have yet
      if (!options.action) this.editor.clearHighlight()
      // the slot on the right holds one rail at a time. Registered from here rather
      // than from the wrapper that mounts this app, so that the object the slot knows
      // and the one asking for it are the same one -- see editor-rails.js
      registerRail(this)
      claimRailSlot(this)
      // a docked rail rather than an overlay: `hidden` is the whole state
      document.getElementById('actions-builder-panel').hidden = false
      this.isOpen = true
      this.currentGroupId = options.groupName
      this.currentSelectedAction = options.action
      this.isEditingExistingAction = !!options.action
      // editing the component under the cursor, or a caller naming one group, both know
      // what they want already; opening with neither is the "what can I insert?" case
      this.view = options.action || options.groupName ? 'settings' : 'palette'
      this.paletteFilter = ''
      setTimeout(() => this.initValues(), 0)
    },
    close() {
      if (!this.isOpen) return
      if (this.editor) this.editor.clearHighlight()
      document.getElementById('actions-builder-panel').hidden = true
      this.isOpen = false
      this.openKey = null
    },
    /** The rail's one button: write what it has been configured into the document. */
    writeIntoEditor() {
      if (this.isEditingExistingAction) {
        this.updateExistingAction(this.wikiCode)
      } else {
        this.insertNewAction(this.wikiCode)
      }
    },
    /**
     * Write a component the document does not have yet, where it was decided it would go
     * when the rail opened, and stay on it: what was just placed is now what is edited.
     */
    insertNewAction(wikiCode) {
      this.isEditingExistingAction = true
      this.openKey = null
      this.editor.insertAt(this.insertAt, wikiCode)
    },
    /**
     * Rewrite the component this panel was opened on -- which is not necessarily the one
     * the cursor is in: landing on a `{{end elem="..."}}` opens the panel on the tag it
     * closes, several lines above.
     */
    updateExistingAction(wikiCode) {
      this.editor.replaceRange(this.target, wikiCode)
    },
    /** Picked from the palette: this names the group AND the action, so both are set. */
    selectFromPalette(groupId, actionId) {
      this.currentGroupId = groupId
      this.currentSelectedAction = null
      this.isEditingExistingAction = false
      this.view = 'settings'
      // initValues() clears selectedActionId on the way in, so the choice is applied
      // after it rather than before -- and the watcher then loads the action's params
      setTimeout(() => {
        this.initValues()
        this.selectedActionId = actionId
      }, 0)
    },
    backToPalette() {
      this.view = 'palette'
      this.paletteFilter = ''
      // whatever was being edited is abandoned here: the rail is picking a component to
      // add again, which is also what keeps the cursor from pulling it back (open())
      this.isEditingExistingAction = false
      this.openKey = null
      if (this.editor) this.editor.clearHighlight()
    },
    initValues() {
      this.values = {}
      this.actionParams = {}
      const previousSelectedActionId = this.selectedActionId
      if (this.isEditingExistingAction) {
        // use a fake dom to parse wiki code attributes
        const holder = document.createElement('div')
        holder.innerHTML = `<${this.currentSelectedAction}/>`
        const fakeDom = holder.firstElementChild

        for (const attribute of fakeDom.attributes) {
          this.values[attribute.name] = attribute.value
        }

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
        if (newActionId == 'entrylist') {
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
        if (this.isEntryListAction) this.values.dynamic = true
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
      if (!(this.selectedFormsIds instanceof Array) || this.selectedFormsIds.length == 0) return ''

      return this.selectedFormsIds.slice(0, 1)[0] ?? '' // only the first one
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
          // One request per form, against the API that replaced `?wiki/json&demand=forms`
          // (that handler is gone; it answered this fetch with an error PAGE, which
          // response.json() rejected on -- leaving `selectedForms` null, and every
          // form-based action in this panel with no parameters at all).
          Promise.all(idsToSearch.map((id) => fetch(wiki.url(`?api/forms/${encodeURIComponent(id)}`))
            .then((response) => (response.ok ? response.json() : null))
            .catch(() => null))).then((forms) => {
            this.loadingForms = this.loadingForms.filter((e) => !idsToSearch.includes(e))
            forms.forEach((form) => {
              if (form && form.id != undefined && idsToSearch.includes(`${form.id}`)) {
                this.loadedForms[form.id] = form
              }
            })
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
      this.values[propName] = value
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
        if (configValue && !this.values[propName]) this.values[propName] = configValue
      }
      if (this.isEntryListAction && this.selectedAction.properties && this.selectedAction.properties.template) this.values.template = this.selectedAction.properties.template.value
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
            || typeof value == 'object' || config && config.mapped === false
            || config && !this.checkConfigDisplay(config)) { continue }
        result[key] = value
      }
      // Adds values from special components
      if (this.$refs.specialInput) this.$refs.specialInput.forEach((p) => result = { ...result, ...p.getValues() })

      // default value for 'entrylist'
      if (this.selectedActionId == 'entrylist') result.template = result.template || 'liste_accordeon'

      // put in first position 'id' and 'template' if existing
      const orderedResult = {}
      if (result.id) orderedResult.id = result.id
      if (result.template) orderedResult.template = result.template
      // Order params, and remove empty values
      Object.keys(result).sort().forEach((key) => { if (result[key] !== '') orderedResult[key] = result[key] })
      this.actionParams = orderedResult
    },
    watchSelectedActionId() {
      if (!this.isEntryListAction && !this.isEditingExistingAction) {
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
