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
import InputFormList from './components/InputFormList.js'
import InputQuery from './components/InputQuery.js'
import InputFacets from './components/InputFacets.js'
import InputSortFields from './components/InputSortFields.js'
import InputReaction from './components/InputReaction.js'
import InputColumnsWidth from './components/InputColumnsWidth.js'
import InputGeo from './components/InputGeo.js'
import InputClass from './components/InputClass.js'
import InputImage from './components/InputImage.js'
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
  InputFormList,
  InputQuery,
  InputHidden,
  InputDivider,
  InputFacets,
  InputSortFields,
  InputReaction,
  InputNavLinks,
  InputGeo,
  InputClass,
  InputImage,
  InputFieldMapping,
  InputColumnsWidth,
  PreviewAction,
  InputHint,
  AddonIcon,
}

/**
 * What the page says it can build. Read when it is asked for, never snapshotted at import
 * time: a boosted navigation evaluates this module once and swaps pages under it, so a
 * snapshot taken on a page that carried no editor would stay empty for the whole session.
 */
function builderData() {
  return typeof actionsBuilderData === 'object' && actionsBuilderData !== null
    ? actionsBuilderData
    : { forms: {}, palette: [], components: {} }
}

/** Each Component names its own icon now; one that names none still gets a card. */
const componentIcon = (name) => legacyIconToSprite(name || 'stack-2') || ''

/** Which Component wrote this tag. */
function matchComponent(components, tag, values) {
  let best = null
  let bestPins = -1

  for (const [id, component] of Object.entries(components)) {
    if (!(component.tags || []).includes(tag)) continue
    const pins = component.pins || {}
    const matches = Object.entries(pins).every(
      ([name, value]) => `${values[name] ?? ''}` === `${value}`,
    )
    if (!matches) continue
    if (Object.keys(pins).length > bestPins) {
      best = id
      bestPins = Object.keys(pins).length
    }
  }

  return best
}

/** What the action does when the parameter is not there at all. */
function effectiveDefault(config) {
  if (!config) return undefined
  if ('default' in config) return config.default
  if (config.type === 'checkbox') return config.uncheckedvalue ?? 'false'

  return undefined
}

const isDefaultValue = (config, value) => {
  const fallback = effectiveDefault(config)

  return fallback !== undefined && `${value}` === `${fallback}`
}

/** The forms an `id="…"` parameter points at. */
const formIdsIn = (value) =>
  String(value ?? '')
    .split(',')
    .map((id) => id.trim().replace(/^https?:\/\/.+->(\d+)$/u, '$1'))
    .filter((id) => /^\d+$/.test(id))

/** Settings whose value is one token of a shared, space-separated parameter. */
const sharedTargets = (configs) => {
  const targets = {}
  for (const name in configs) {
    const target = configs[name]?.writesTo
    if (target) (targets[target] ??= []).push(name)
  }

  return targets
}

/** Which of a setting's own values a token of the shared parameter is. */
const tokensOf = (config) => {
  if (config.type === 'list') return Object.keys(config.options || {})
  if (config.type === 'checkbox' && config.checkedvalue) {
    return [String(config.checkedvalue)]
  }

  return []
}

const extraComponents = builderData().extraComponents
if (extraComponents) {
  Object.entries(extraComponents).forEach(async ([name, filepath]) => {
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
    const data = builderData()

    return {
      components: data.components,
      palette: data.palette,
      selectedActionId: '',
      formIds: data.forms,
      selectedFormsIds: '',
      selectedForms: null,
      loadedForms: {},
      loadingForms: [],
      values: {},
      specialValues: {},
      writtenValues: {},
      actionParams: {},
      isEditingExistingAction: false,
      view: 'palette',
      paletteFilter: '',
      isOpen: false,
      openKey: null,
      insertAt: null,
      target: null,
      written: null,
      sharedRest: {},
      editor: null,
    }
  },
  computed: {
    /** True while the rail is placing a component the document does not contain yet. */
    isPlacingNewAction() {
      return this.isOpen && !this.isEditingExistingAction
    },
    /** Whether the editor shows the component itself, in the page being written. */
    editorRendersComponents() {
      return Boolean(this.editor?.previewComponent)
    },
    /** The component the rail is on, whatever it was reached by. */
    selectedAction() {
      return this.components[this.selectedActionId]
    },
    /** What the rail is on, for its header: the component. */
    railTitle() {
      return this.selectedAction?.label || ''
    },
    /** The palette: every component that can be inserted, under its group's heading and narrowed by the filter box. */
    paletteGroups() {
      const needle = this.paletteFilter.trim().toLowerCase()
      const matches = (text) =>
        !needle || String(text).toLowerCase().includes(needle)

      return this.palette
        .map((category) => ({
          id: category.id,
          label: category.label,
          actions: category.components
            .filter(
              (component) =>
                matches(component.label) || matches(category.label),
            )
            .map((component) => ({
              id: component.id,
              label: component.label,
              icon: componentIcon(component.icon),
            })),
        }))
        .filter((category) => category.actions.length > 0)
    },
    /** What the component's form setting says, when it has one and it is on screen. */
    selectedFormValue() {
      const configs = this.selectedActionAllConfigs
      const name = Object.keys(configs).find(
        (key) => configs[key]?.type === 'form-list',
      )
      if (!name || !this.checkConfigDisplay(configs[name])) return ''

      return this.values[name] ?? ''
    },
    configPanels() {
      const result = []
      if (
        this.selectedAction?.properties &&
        Object.values(this.selectedAction.properties).some((conf) => conf.type)
      ) {
        result.push({
          params: this.selectedAction,
          class: 'specific-action-params',
        })
      }
      ;(this.selectedAction?.groups || []).forEach((params) =>
        result.push({ params }),
      )
      return result
    },
    selectedActionAllConfigs() {
      let result = {}
      this.configPanels.forEach((panel) => {
        result = { ...result, ...(panel.params?.properties || {}) }
      })
      return result
    },
    wikiCodeStart() {
      const [first = this.selectedActionId] = this.selectedAction?.tags || []
      const tag = this.tagDecidedBySetting() ?? first
      let result = `{{${tag}`
      for (const key in this.actionParams) {
        result += ` ${key}="${this.actionParams[key]}"`
      }
      result += '}}'
      return result
    },
    wikiCodeDefaultContent() {
      const wrappedContent = this.selectedAction?.wrappedContentExample || ''
      let content = wrappedContent
      if (this.selectedActionId === 'tabs' && this.actionParams.titles) {
        content = this.actionParams.titles
          .split(',')
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
      if (!['label', 'accordion', 'grid'].includes(this.selectedActionId))
        content = `\n${content}\n`
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
    },
  },
  methods: {
    open(editor, options) {
      this.readPalette()
      this.editor = editor
      this.target = options.target || null
      this.insertAt = options.insertAt || null
      if (this.isOpen && options.key && options.key === this.openKey) return
      this.openKey = options.key || null
      if (!options.action) this.editor.clearHighlight()
      registerRail(this)
      claimRailSlot(this)
      document.getElementById('actions-builder-panel').hidden = false
      this.isOpen = true
      this.currentGroupId = options.groupName
      this.currentSelectedAction = options.action
      this.isEditingExistingAction = !!options.action
      this.view = options.action || options.groupName ? 'settings' : 'palette'
      this.paletteFilter = ''
      this.written = null
      setTimeout(() => this.initValues(), 0)
    },
    /** The page that is on screen now is the one whose components the rail offers. */
    readPalette() {
      const data = builderData()
      if (data.palette === this.palette) return
      this.components = data.components
      this.palette = data.palette
      this.formIds = data.forms
    },
    close() {
      if (!this.isOpen) return
      if (this.editor) this.editor.clearHighlight()
      document.getElementById('actions-builder-panel').hidden = true
      this.isOpen = false
      this.openKey = null
      this.written = null
    },
    /** Write what the panel currently says into the document. */
    applyToEditor() {
      if (!this.isOpen || !this.selectedActionId) return
      const wikiCode = this.wikiCode
      if (wikiCode === this.written) return

      if (this.isEditingExistingAction) {
        if (!this.target) return
        this.updateExistingAction(wikiCode)
      } else {
        this.insertNewAction(wikiCode)
      }
      this.written = wikiCode
    },
    /** Write a component the document does not have yet, where it was decided it would go when the rail opened, and stay on it: what was just placed is now what is edited. */
    insertNewAction(wikiCode) {
      this.isEditingExistingAction = true
      this.openKey = null
      this.target = this.editor.insertAt(this.insertAt, wikiCode) || null
    },
    /** Rewrite the component this panel was opened on -- which is not necessarily the one the cursor is in: landing on a `{{end elem="..."}}` opens the panel on the tag it closes, several lines above. */
    updateExistingAction(wikiCode) {
      const moved = this.editor.replaceRange(this.target, wikiCode)
      if (moved) {
        this.target = moved
        this.editor.highlightRange?.(moved)
      }
    },
    /** Picked from the palette: this names the group AND the action, so both are set. */
    selectFromPalette(groupId, actionId) {
      this.currentGroupId = groupId
      this.currentSelectedAction = null
      this.isEditingExistingAction = false
      this.written = null
      this.view = 'settings'
      setTimeout(() => {
        this.initValues()
        this.selectedActionId = actionId
        setTimeout(() => this.applyToEditor(), 0)
      }, 0)
    },
    backToPalette() {
      this.view = 'palette'
      this.paletteFilter = ''
      this.isEditingExistingAction = false
      this.openKey = null
      if (this.editor) this.editor.clearHighlight()
    },
    initValues() {
      this.values = {}
      this.actionParams = {}
      if (this.isEditingExistingAction) {
        const holder = document.createElement('div')
        holder.innerHTML = `<${this.currentSelectedAction}/>`
        const fakeDom = holder.firstElementChild

        for (const attribute of fakeDom.attributes) {
          this.values[attribute.name] = attribute.value
        }

        const newActionId = fakeDom.tagName.toLowerCase()
        this.selectedActionId = newActionId
        this.selectedActionId =
          matchComponent(this.components, newActionId, this.values) ??
          newActionId
        const sourceSetting = Object.entries(
          this.selectedAction?.properties || {},
        ).find(([, config]) => config?.decidesTag)
        if (sourceSetting) this.values[sourceSetting[0]] = newActionId
        this.writtenValues = { ...this.values }
        if (this.$refs.specialInput)
          this.$refs.specialInput.forEach((component) =>
            component.parseNewValues(this.values),
          )
      } else {
        if (this.$refs.specialInput)
          this.$refs.specialInput.forEach((component) =>
            component.resetValues(),
          )
        this.writtenValues = {}
        this.selectedFormsIds = null
        this.selectedActionId = ''
      }
      this.updateActionParams()
    },
    /** The tag named by a `decidesTag` setting, when the component has one and the value it holds is a tag the component actually writes. */
    tagDecidedBySetting() {
      const configs = this.selectedActionAllConfigs
      const name = Object.keys(configs).find((key) => configs[key]?.decidesTag)
      if (!name) return null
      const value = this.values[name]
      return (this.selectedAction?.tags || []).includes(value) ? value : null
    },
    /** Fetch the forms the component is now pointed at, so that every setting made of its fields -- the field mappings, the search fields, the colour field -- has something to offer. */
    loadFormsFor(value) {
      const ids = formIdsIn(value)
      const current = this.selectedFormsIds || []
      const unchanged =
        ids.length === current.length && ids.every((id) => current.includes(id))
      if (!unchanged) this.selectedFormsIds = ids
    },
    getSelectedFormsByAjax() {
      if (!this.selectedFormsIds) return
      if (
        this.selectedFormsIds.every((fid) =>
          Object.prototype.hasOwnProperty.call(this.loadedForms, fid),
        )
      ) {
        this.selectedForms = {}
        for (const key in this.loadedForms) {
          if (this.selectedFormsIds.includes(key)) {
            this.selectedForms[key] = this.loadedForms[key]
          }
        }
        if (this.selectedAction) {
          setTimeout(() => this.updateActionParams(), 0)
        }
      } else {
        const idsToSearch = this.selectedFormsIds.filter(
          (fid) =>
            !Object.prototype.hasOwnProperty.call(this.loadedForms, fid) &&
            !this.loadingForms.includes(fid),
        )
        if (idsToSearch.length > 0) {
          idsToSearch.forEach((id) => this.loadingForms.push(id))
          Promise.all(
            idsToSearch.map((id) =>
              fetch(wiki.url(`?api/forms/${encodeURIComponent(id)}`))
                .then((response) => (response.ok ? response.json() : null))
                .catch(() => null),
            ),
          ).then((forms) => {
            this.loadingForms = this.loadingForms.filter(
              (e) => !idsToSearch.includes(e),
            )
            forms.forEach((form) => {
              if (
                form &&
                form.id != null &&
                idsToSearch.includes(`${form.id}`)
              ) {
                this.loadedForms[form.id] = form
              }
            })
            idsToSearch.forEach((fid) => {
              if (
                !Object.prototype.hasOwnProperty.call(this.loadedForms, fid)
              ) {
                this.loadedForms[fid] = { prepared: {} }
              }
            })
            if (this.$refs.specialInput)
              setTimeout(
                () =>
                  this.$refs.specialInput.forEach((component) =>
                    component.parseNewValues(this.writtenValues),
                  ),
                0,
              )
            this.selectedForms = {}
            for (const key in this.loadedForms) {
              if (
                this.selectedFormsIds &&
                this.selectedFormsIds.includes(key)
              ) {
                this.selectedForms[key] = this.loadedForms[key]
              }
            }
            if (this.selectedAction) {
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
    /** How many of the panel's six columns this setting takes. */
    spanOf(config) {
      if (config?.span) return config.span
      const wide = [
        'class',
        'field-mapping',
        'query',
        'columns-width',
        'nav-links',
        'facets',
        'sort-fields',
        'geo',
        'divider',
        'hint',
      ]

      return wide.includes(config?.type) ? 6 : 3
    },
    /** Take a shared parameter apart into the settings that write it. */
    splitSharedValues() {
      this.sharedRest = {}
      const configs = this.selectedActionAllConfigs
      for (const [target, names] of Object.entries(sharedTargets(configs))) {
        const written = String(this.values[target] ?? '')
        const tokens = written.split(/\s+/).filter(Boolean)
        const claimed = new Set()
        for (const name of names) {
          const mine = tokensOf(configs[name]).filter((t) => tokens.includes(t))
          if (mine.length) {
            this.values[name] = mine[0]
            claimed.add(mine[0])
          } else if (configs[name].type === 'checkbox') {
            this.values[name] = configs[name].uncheckedvalue ?? ''
          }
        }
        this.sharedRest[target] = tokens
          .filter((t) => !claimed.has(t))
          .join(' ')
        delete this.values[target]
      }
    },
    initValuesOnActionSelected() {
      if (!this.selectedAction) return
      for (const propName in this.selectedAction.properties) {
        const configValue = this.isEditingExistingAction
          ? this.selectedAction.properties[propName].default
          : this.selectedAction.properties[propName].value ||
            this.selectedAction.properties[propName].default
        if (configValue && !this.values[propName])
          this.values[propName] = configValue
      }
      Object.entries(this.selectedAction.pins || {}).forEach(
        ([name, value]) => {
          this.values[name] = value
        },
      )
      this.splitSharedValues()
      setTimeout(() => {
        this.updateActionParams()
        if (this.isEditingExistingAction && this.written === null) {
          this.written = this.wikiCode
        }
      }, 0)
    },
    updateActionParams() {
      if (!this.selectedAction) return
      let result = {}

      for (const key in this.values) {
        const config = this.selectedActionAllConfigs[key]
        const value = this.values[key]
        if (
          Object.prototype.hasOwnProperty.call(result, key) ||
          value === undefined ||
          isDefaultValue(config, value) ||
          typeof value == 'object' ||
          (config && config.mapped === false) ||
          (config && !this.checkConfigDisplay(config))
        ) {
          continue
        }
        result[key] = value
      }
      const configs = this.selectedActionAllConfigs
      for (const [target, names] of Object.entries(sharedTargets(configs))) {
        const tokens = names
          .filter((name) => this.checkConfigDisplay(configs[name]))
          .map((name) => this.values[name])
        const rest = this.sharedRest[target]
        const joined = [...tokens, rest]
          .map((t) => (t === undefined || t === null ? '' : String(t).trim()))
          .filter(Boolean)
          .join(' ')
        names.forEach((name) => delete result[name])
        if (joined) result[target] = joined
      }

      const special = {}
      if (this.$refs.specialInput)
        this.$refs.specialInput.forEach((p) => {
          if (p.config && !this.checkConfigDisplay(p.config)) return
          const written = p.getValues()
          Object.assign(special, written)
          result = { ...result, ...written }
        })
      this.specialValues = special

      if (this.selectedActionId === 'entrylist')
        result.template = result.template || 'liste_accordeon'

      const orderedResult = {}
      if (result.id) orderedResult.id = result.id
      if (result.template) orderedResult.template = result.template
      Object.keys(result)
        .sort()
        .forEach((key) => {
          if (result[key] !== '') orderedResult[key] = result[key]
        })
      this.actionParams = orderedResult
    },
    watchSelectedActionId() {
      if (!this.isEditingExistingAction) {
        const kept = {}
        const configs = this.selectedActionAllConfigs
        const form = Object.keys(configs).find(
          (key) => configs[key]?.type === 'form-list',
        )
        if (form && this.values[form]) kept[form] = this.values[form]
        this.values = kept
      }
      this.initValuesOnActionSelected()
    },
  },
  watch: {
    /** The form picker's value: what it says is which forms the panel is built from. */
    selectedFormValue(value) {
      this.loadFormsFor(value)
    },
    selectedFormsIds(val, oldVal) {
      if (
        !oldVal ||
        (val &&
          (oldVal.length !== val.length ||
            (Array.isArray(val) && !Array.isArray(oldVal)) ||
            !val.every((e) => oldVal.includes(e))))
      ) {
        this.getSelectedFormsByAjax()
      }
    },
    selectedActionId() {
      this.watchSelectedActionId()
    },
    /** Every change to any parameter ends up here, since they all feed `wikiCode` -- which is why the write follows this rather than each input. */
    wikiCode() {
      clearTimeout(this.applyTimer)
      this.applyTimer = setTimeout(() => this.applyToEditor(), 400)
    },
  },
}
