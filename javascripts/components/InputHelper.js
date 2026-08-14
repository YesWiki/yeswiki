// Some methods to be reused as mixins for component who want to build dynamically
// input components
export default {
  methods: {
    componentIdFrom(config) {
      if (!config) return 'input-hidden'
      return `input-${['text', 'number', 'range', 'url', 'email'].includes(config.type) ? 'text' : config.type || 'hidden'}`
    },
    // Whether or not display this field (and include it's key/value in the action params)
    checkConfigDisplay(config) {
      if (!config) return false
      let showIfResult = true
      // condition with showif attribute
      if (config.showif) {
        let showIfConf = config.showif
        if (typeof showIfConf === 'string') {
          // allow shortcut conf like showif: myfield
          showIfConf = {}
          showIfConf[config.showif] = 'notNull'
        }
        // Check every condition is respected
        for (const field in showIfConf) {
          // a condition may name a parameter written by a composite input rather than an
          // ordinary setting -- "once there is a facet" names `groups`
          const said = this.values[field] ?? this.specialValues?.[field]
          const value = (said || false).toString()
          const expectedValue = showIfConf[field].toString()
          if (expectedValue === 'notNull')
            showIfResult = showIfResult && !['', 'false'].includes(value)
          else if (Array.isArray(expectedValue))
            showIfResult = showIfResult && expectedValue.includes(value)
          else if (value)
            showIfResult =
              showIfResult && new RegExp(expectedValue, 'i').exec(value) != null
        }
      }
      // Other conditions
      const hideIf =
        (config.showif && !showIfResult) ||
        (config.showOnlyFor &&
          !config.showOnlyFor.includes(this.selectedActionId)) ||
        (config.showExceptFor &&
          config.showExceptFor.includes(this.selectedActionId))
      return !hideIf
    },
    /**
     * Whether to draw this setting at all.
     *
     * The same question as checkConfigDisplay() since the "advanced parameters" box went:
     * a rail that hides half of what a component can be told, behind a checkbox, is a rail
     * you have to know the answer before you can find. Order carries importance instead --
     * what a component is pointed at first, the rest under it. Kept as its own name
     * because a dozen templates ask it, and because "is this drawn" and "does this count
     * as set" are two questions that have been the same before.
     */
    checkVisibility(config) {
      if (!config) return false
      return this.checkConfigDisplay(config)
    },
    refFrom(config) {
      if (!config) return ''
      return config.subproperties || config.type === 'geo' ? 'specialInput' : ''
    },
    getFieldsFormSelectedForms(selectedForms, extraFields = []) {
      const fields = []
      for (const key in selectedForms) {
        const prepared =
          typeof selectedForms[key].prepared == 'object'
            ? Object.values(selectedForms[key].prepared)
            : selectedForms[key].prepared

        prepared.forEach((field) => {
          if (
            fields.every(
              (f) =>
                (!f.id && field.id) || (f.id && !field.id) || f.id !== field.id,
            )
          ) {
            fields.push(field)
          }
        })
      }
      if (extraFields.includes('form_id')) {
        const options = {}
        Object.keys(this.selectedForms).forEach((key) => {
          options[key] = this.selectedForms[key].label || key
        })
        // fake a field
        fields.push({
          id: 'form_id',
          name: 'form_id',
          propertyName: 'form_id',
          label: _t('ACTION_BUILDER_FORM_ID'),
          options: { ...options }, // clone object
        })
      }
      const extraFieldsWithoutOptions = {
        // not a field of the form: what a Content computes for itself, which is what a
        // list falls back to when no field is named (ADR-0010)
        title: _t('ACTION_BUILDER_GENERATED_TITLE'),
        created_at: _t('ACTION_BUILDER_CREATION_DATE'),
        updated_at: _t('ACTION_BUILDER_MODIFICATION_DATE'),
        owner: _t('ACTION_BUILDER_OWNER'),
        url: _t('URL'),
      }
      for (const key in extraFieldsWithoutOptions) {
        if (extraFields.includes(key)) {
          // fake a field
          fields.push({
            id: key,
            name: key,
            propertyName: key,
            label: extraFieldsWithoutOptions[key],
          })
        }
      }
      return fields
    },
    formatExtraFieldsAsArray(extraFields) {
      return !extraFields
        ? []
        : Array.isArray(extraFields)
          ? extraFields
          : typeof extraFields == 'string'
            ? [extraFields]
            : typeof extraFields == 'object'
              ? Object.values(extraFields)
              : []
    },
  },
}
