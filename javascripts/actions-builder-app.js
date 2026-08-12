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
  InputImage,
  InputFieldMapping,
  InputColumnsWidth,
  PreviewAction,
  InputHint,
  AddonIcon,
}

// actionsBuilderData is defined is AceditorAction
const data =
  typeof actionsBuilderData === 'object'
    ? actionsBuilderData
    : { forms: {}, palette: [], components: {} }

/** Each Component names its own icon now; one that names none still gets a card. */
const componentIcon = (name) => legacyIconToSprite(name || 'stack-2') || ''

/**
 * Which Component wrote this tag.
 *
 * Take every Component that lists the tag name; keep those whose pinned settings all match
 * what the tag actually says; the one with the most pins wins, and a Component with no pins
 * is the fallback. So `{{entrylist template="card"}}` is a `Cards` and
 * `{{entrylist template="something-nobody-declared"}}` is a plain entry list rather than
 * nothing at all.
 *
 * This replaces a branch that knew the answer for one group and no other: it matched
 * `properties.template.value` against the parsed template, `if (newActionId === 'entrylist')`.
 * Thirteen components were reachable that way and every other pinned component would have
 * been invisible.
 */
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

/**
 * What the action does when the parameter is not there at all.
 *
 * A setting sitting at its default is left out of the tag -- that is what keeps a component
 * from being written out with thirty parameters restating what it would have done anyway.
 * The test for it used to be `config.default && value === config.default`, which skips the
 * whole check whenever the default is falsy: `''`, `0` and `false` are exactly the defaults
 * worth omitting, and every one of them was written out instead.
 *
 * A checkbox with no declared default has one all the same -- unticked -- and it is what the
 * box shows before anyone touches it. Without this, merely opening the rail on a `{{section}}`
 * added `patternreverse="false"` to it.
 */
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

/** Settings whose value is one token of a shared, space-separated parameter. */
const sharedTargets = (configs) => {
  const targets = {}
  for (const name in configs) {
    const target = configs[name]?.writesTo
    if (target) (targets[target] ??= []).push(name)
  }

  return targets
}

/**
 * Which of a setting's own values a token of the shared parameter is.
 *
 * A choice knows its tokens (they are its options); a checkbox has exactly one, the value
 * it writes when ticked. Anything else contributes nothing, which is what stops a free-text
 * setting from claiming every word of the class it was written beside.
 */
const tokensOf = (config) => {
  if (config.type === 'list') return Object.keys(config.options || {})
  if (config.type === 'checkbox' && config.checkedvalue) {
    return [String(config.checkedvalue)]
  }

  return []
}

// dynamically loads other components defined in extensions or in custom folder
if (data.extraComponents) {
  Object.entries(data.extraComponents).forEach(async ([name, filepath]) => {
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
      // every Component by id, palette-visible or not: the rail opens on components the
      // palette does not offer (a file was inserted by the picker, not chosen from a card)
      components: data.components,
      palette: data.palette,
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
      // the last wiki code this rail put in the document, so that a change that does not
      // change anything is not written again
      written: null,
      // leftovers of a shared parameter: the class tokens no setting recognised, kept so
      // they can be put back. NOT in `values` -- everything in there is written into the
      // tag as a parameter of its own, so parking them there emitted `class__rest="..."`
      // beside the `class` they came out of.
      sharedRest: {},
      // Current Aceditor in use
      editor: null,
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
    /**
     * Whether the editor shows the component itself, in the page being written. Then this
     * panel is only the parameters: the render belongs in one place, and that place is
     * where the component is going to be.
     */
    editorRendersComponents() {
      return Boolean(this.editor?.previewComponent)
    },
    /** The component the rail is on, whatever it was reached by. */
    selectedAction() {
      return this.components[this.selectedActionId]
    },
    /**
     * What the rail is on, for its header: the component. The group it belongs to is how
     * the palette is arranged, not what is being configured -- and with the component
     * decided before this panel shows, naming the drawer instead of the thing in it left
     * every button's settings titled "Buttons".
     */
    railTitle() {
      return this.selectedAction?.label || ''
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
    /**
     * Whether this component has to be pointed at a form before it can render anything.
     * Declared by the component rather than by the drawer it used to live in: `needFormField`
     * was a property of the whole `entrylist` YAML group.
     */
    needFormField() {
      return Boolean(this.selectedAction?.needsForm)
    },
    // Some action group (like bazar) have common properties available for each actions
    // so we always display those commons properties in different panels
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
      // ...and the shared blocks it was handed. These used to be found by NAME -- any
      // entry of the same group called `common*` -- which is a convention nothing enforced.
      ;(this.selectedAction?.groups || []).forEach((params) =>
        result.push({ params }),
      )
      return result
    },
    isSomeAdvancedParams() {
      return this.configPanels.some((panel) => {
        const props = Object.values(panel.params?.properties || {})
        return props.some((prop) => prop?.advanced)
      })
    },
    selectedActionAllConfigs() {
      let result = {}
      this.configPanels.forEach((panel) => {
        result = { ...result, ...(panel.params?.properties || {}) }
      })
      return result
    },
    wikiCodeStart() {
      // The TAG a component writes, which is not its own name: `bazarcard` is one of
      // thirteen ways of writing `{{entrylist}}`. The first tag it lists is the one it
      // writes; the others are ones it also answers to (see the resolver below).
      const [first = this.selectedActionId] = this.selectedAction?.tags || []
      // ...unless a setting decides it. A Presentation writes whichever tag its source
      // setting names -- `Cards` over a form is `{{entrylist}}`, over a feed
      // `{{syndication}}`, and it is one card either way (ticket 37).
      const tag = this.tagDecidedBySetting() ?? first
      let result = `{{${tag}`
      for (const key in this.actionParams) {
        result += ` ${key}="${this.actionParams[key]}"`
      }
      // no space before the braces: `{{section bgcolor="x" }}` is what this used to write,
      // and a tag that comes back from the rail differing from the one that went in by a
      // space is a diff on every page anyone opens the rail on
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
      this.written = null
      setTimeout(() => this.initValues(), 0)
    },
    close() {
      if (!this.isOpen) return
      if (this.editor) this.editor.clearHighlight()
      document.getElementById('actions-builder-panel').hidden = true
      this.isOpen = false
      this.openKey = null
      this.written = null
    },
    /**
     * Write what the panel currently says into the document.
     *
     * Every change lands as it is made: the rail used to hold them back behind a button,
     * and closing it threw away whatever had been typed -- a rule nothing on screen said
     * out loud. Ctrl+Z is the way back now, in both editors (the ACeditor's undo has always
     * recorded programmatic edits; Vditor's needed ComponentEditor.recordUndo, which is why
     * that exists).
     *
     * Nothing is written until something has actually changed. Merely opening the rail on a
     * component must leave the page alone -- the rail normalises what it writes (it drops
     * parameters that are already the default, and empty ones), so writing on open would
     * rewrite tags nobody touched.
     */
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
    /**
     * Write a component the document does not have yet, where it was decided it would go
     * when the rail opened, and stay on it: what was just placed is now what is edited.
     */
    insertNewAction(wikiCode) {
      this.isEditingExistingAction = true
      this.openKey = null
      // ...and hold on to where it landed. Without this the rail declares itself to be
      // editing an existing component while pointing at nothing, so applyToEditor's
      // `if (!this.target) return` swallowed every change made after an insert -- pick
      // Cards, then change anything, and the page kept the first version.
      this.target = this.editor.insertAt(this.insertAt, wikiCode) || null
    },
    /**
     * Rewrite the component this panel was opened on -- which is not necessarily the one
     * the cursor is in: landing on a `{{end elem="..."}}` opens the panel on the tag it
     * closes, several lines above.
     */
    updateExistingAction(wikiCode) {
      // ...and follow it. In the source editor a target is a row/column range, so writing
      // a tag of a different length moves the thing this rail is on; the next change would
      // otherwise be written over whatever now sits at the old coordinates. (The WYSIWYG
      // editor addresses the widget element itself, which survives being rewritten, and
      // returns nothing here.)
      const moved = this.editor.replaceRange(this.target, wikiCode)
      if (moved) {
        this.target = moved
        this.editor.highlightRange?.(moved)
      }
    },
    /**
     * Picked from the palette: this names the group AND the action, so both are set.
     *
     * The component lands in the page here rather than waiting for a button. It is what
     * picking one means, and it puts the thing being configured in front of the person
     * configuring it -- there is nothing to judge a colour or a column count against while
     * the component is still hypothetical.
     */
    selectFromPalette(groupId, actionId) {
      this.currentGroupId = groupId
      this.currentSelectedAction = null
      this.isEditingExistingAction = false
      this.written = null
      this.view = 'settings'
      // initValues() clears selectedActionId on the way in, so the choice is applied
      // after it rather than before -- and the watcher then loads the action's params
      setTimeout(() => {
        this.initValues()
        this.selectedActionId = actionId
        // ...and once the parameters have settled into their defaults, write it. Not in
        // the same tick: the watcher that loads them has not run yet, so the tag would go
        // in bare and be rewritten a moment later, costing an undo stop for nothing.
        setTimeout(() => this.applyToEditor(), 0)
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
        // Get Form if needed
        if (this.needFormField) {
          if (!this.selectedFormsIds) {
            this.selectedFormsIds = this.getValidFormsIds()
          }
          this.getSelectedFormsByAjax()
        }

        // ...and which component that tag belongs to, which is not always the tag's own
        // name: thirteen of them write `{{entrylist}}` and are told apart by their pins
        this.selectedActionId =
          matchComponent(this.components, newActionId, this.values) ??
          newActionId
        // a Presentation is reached through any of its sources' tags, so the source it is
        // showing is the tag it was written as -- not whichever one the setting defaults to
        const sourceSetting = Object.entries(
          this.selectedAction?.properties || {},
        ).find(([, config]) => config?.decidesTag)
        if (sourceSetting) this.values[sourceSetting[0]] = newActionId
        if (this.$refs.specialInput)
          this.$refs.specialInput.forEach((component) =>
            component.parseNewValues(this.values),
          )
      } else {
        if (this.$refs.specialInput)
          this.$refs.specialInput.forEach((component) =>
            component.resetValues(),
          )
        this.selectedFormsIds = null
        this.selectedActionId = ''
        // a list is dynamic by default -- declared by the component now (`dynamic` is one
        // of its shared settings) rather than inferred from which drawer it came out of
        if (this.selectedAction?.needsForm) this.values.dynamic = true
      }
      this.updateActionParams()
      // (a group holding exactly one action used to be auto-selected here. A component is
      // always chosen outright now -- from a card, or by the cursor landing in one -- so
      // there is never a single candidate left to guess at.)
    },
    /**
     * The tag named by a `decidesTag` setting, when the component has one and the value it
     * holds is a tag the component actually writes. Null otherwise, so the first tag wins.
     */
    tagDecidedBySetting() {
      const configs = this.selectedActionAllConfigs
      const name = Object.keys(configs).find((key) => configs[key]?.decidesTag)
      if (!name) return null
      const value = this.values[name]
      return (this.selectedAction?.tags || []).includes(value) ? value : null
    },
    // prefer methods to computed to prevent cache
    getSelectedFormId() {
      if (
        !(this.selectedFormsIds instanceof Array) ||
        this.selectedFormsIds.length === 0
      )
        return ''

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
      return (this.values.id || '')
        .split(',')
        .filter((id) => ['number', 'string'].includes(typeof id))
        .map((id) => id.replace(/(^[0-9]$)|^https?:\/\/.+->([0-9]+)$/u, '$1$2'))
        .filter((e) => e.match(/^\d+$/))
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
          this.selectedForms[key] = this.loadedForms[key]
        }
        if (this.selectedAction) {
          // action choosen updateActionParams
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
          // One request per form, against the API that replaced `?wiki/json&demand=forms`
          // (that handler is gone; it answered this fetch with an error PAGE, which
          // response.json() rejected on -- leaving `selectedForms` null, and every
          // form-based action in this panel with no parameters at all).
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
            // default forms for missing
            idsToSearch.forEach((fid) => {
              // fake empty form
              if (
                !Object.prototype.hasOwnProperty.call(this.loadedForms, fid)
              ) {
                this.loadedForms[fid] = { prepared: {} }
              }
            })
            // On first form loaded, we load again the values so the special components are rendered and we can parse values on each special component
            if (!this.selectedForms && this.isEditingExistingAction)
              setTimeout(() => this.initValues(), 0)
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
    /**
     * How many of the panel's six columns this setting takes. Half by default; a composite
     * input takes the row whether it asked to or not, since it is a panel rather than a
     * control and there is nothing sensible to put beside it.
     */
    spanOf(config) {
      if (config?.span) return config.span
      const wide = [
        'class',
        'field-mapping',
        'icon-mapping',
        'color-mapping',
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
    /**
     * Take a shared parameter apart into the settings that write it.
     *
     * `class="cover full-width text-left"` is four independent choices in a trench coat.
     * They are ordinary settings now, so on the way in each one claims the tokens it knows
     * -- and whatever is left over is somebody's hand-written class, which is kept as it is
     * on the first setting that will hold it.
     */
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
        // whatever no setting recognised is not ours to drop
        this.sharedRest[target] = tokens
          .filter((t) => !claimed.has(t))
          .join(' ')
        delete this.values[target]
      }
    },
    initValuesOnActionSelected() {
      if (!this.selectedAction) return
      // Populate the values field from the config
      for (const propName in this.selectedAction.properties) {
        // if editing, do not fill value with value when `!default == true`, use only default
        const configValue = this.isEditingExistingAction
          ? this.selectedAction.properties[propName].default
          : this.selectedAction.properties[propName].value ||
            this.selectedAction.properties[propName].default
        if (configValue && !this.values[propName])
          this.values[propName] = configValue
      }
      // a component's pins are values it always writes, so they are part of what it says
      // before anyone touches a field. `entrylist` was the only one that got this, and it
      // got it by name.
      Object.entries(this.selectedAction.pins || {}).forEach(
        ([name, value]) => {
          this.values[name] = value
        },
      )
      this.splitSharedValues()
      setTimeout(() => {
        this.updateActionParams()
        // ...and now that the inputs have settled, remember what this component already
        // says. Several of them emit as they mount -- a checkbox reports itself unticked
        // before anyone has seen it -- so "has anything changed?" cannot be answered by
        // watching for a change; it is answered by comparing against this.
        //
        // Only when the document already holds the component. When one is being inserted
        // there is nothing to be the same as, and the first value computed is what gets
        // written.
        if (this.isEditingExistingAction && this.written === null) {
          this.written = this.wikiCode
        }
      }, 0)
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
      // ...and put the shared parameters back together: every setting that writes into
      // `class` contributes one token, in the order they are declared, with whatever the
      // page had that no setting recognised kept on the end.
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

      // Adds values from special components -- but only the ones on screen. They are
      // hidden with v-show, so a `showIf` that stops one being shown leaves it in $refs
      // and it went on contributing its parameter: picking a feed still wrote the form
      // mapping that only a form can have (ticket 37).
      if (this.$refs.specialInput)
        this.$refs.specialInput.forEach((p) => {
          if (p.config && !this.checkConfigDisplay(p.config)) return
          result = { ...result, ...p.getValues() }
        })

      // default value for 'entrylist'
      if (this.selectedActionId === 'entrylist')
        result.template = result.template || 'liste_accordeon'

      // put in first position 'id' and 'template' if existing
      const orderedResult = {}
      if (result.id) orderedResult.id = result.id
      if (result.template) orderedResult.template = result.template
      // Order params, and remove empty values
      Object.keys(result)
        .sort()
        .forEach((key) => {
          if (result[key] !== '') orderedResult[key] = result[key]
        })
      this.actionParams = orderedResult
    },
    watchSelectedActionId() {
      if (!this.selectedAction?.needsForm && !this.isEditingExistingAction) {
        this.values = {}
      }
      this.initValuesOnActionSelected()
    },
  },
  watch: {
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
    /**
     * Every change to any parameter ends up here, since they all feed `wikiCode` -- which
     * is why the write follows this rather than each input.
     *
     * Debounced, and the debounce is what makes undo usable: each write is one stop for
     * Ctrl+Z, so writing per keystroke would make typing `300` into a height field three
     * of them. A pause in typing is the unit of undo, which is what every editor does.
     */
    wikiCode() {
      clearTimeout(this.applyTimer)
      this.applyTimer = setTimeout(() => this.applyToEditor(), 400)
    },
  },
}
