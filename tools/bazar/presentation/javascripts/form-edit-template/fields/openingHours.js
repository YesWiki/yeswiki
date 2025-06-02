import { readConf, writeconf, semanticConf, defaultMapping } from '/tools/bazar/presentation/javascripts/form-edit-template/fields/commons/attributes.js'

export default {
  field: {
    label: "horaires ouverture",
    name: 'openinghours',
    attrs: { type: 'openingHours' },
    icon: '<i class="far fa-calendar-alt"></i>',
  },
  defaultIdentifier: 'horaires_ouverture',
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  advancedAttributes: ['read', 'write', 'semantic'],
  renderInput(fieldData) {
    return { field: '<input type="date"/>' }
  },
}
