import { selectConf, listsMapping } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CHECKBOX_LABEL'),
    name: 'checkbox-group',
    attrs: { type: 'checkbox-group' },
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2-square" viewBox="0 0 16 16"><path d="M3 14.5A1.5 1.5 0 0 1 1.5 13V3A1.5 1.5 0 0 1 3 1.5h8a.5.5 0 0 1 0 1H3a.5.5 0 0 0-.5.5v10a.5.5 0 0 0 .5.5h10a.5.5 0 0 0 .5-.5V8a.5.5 0 0 1 1 0v5a1.5 1.5 0 0 1-1.5 1.5H3z"/><path d="m8.354 10.354 7-7a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"/></svg>'
  },
  defaultIdentifier: 'bf_checkboxes',
  attributes: {
    ...selectConf,
    ...{
      fillingMode: {
        label: _t('BAZ_FORM_EDIT_FILLING_MODE_LABEL'),
        options: {
          ' ': _t('BAZ_FORM_EDIT_FILLING_MODE_NORMAL'),
          tags: _t('BAZ_FORM_EDIT_FILLING_MODE_TAGS'),
          dragndrop: _t('BAZ_FORM_EDIT_FILLING_MODE_DRAG_AND_DROP')
        }
      },
      queries: {
        label: _t('BAZ_FORM_EDIT_QUERIES_LABEL'),
        value: '',
        placeholder: 'ex. : checkboxfiche6=PageTag ; cf. https://yeswiki.net/?LierFormulairesEntreEux'
      }
    }
  },
  advancedAttributes: ['read', 'write', 'semantic', 'queries', 'options'],
  // disabledAttributes: [],
  attributesMapping: { ...listsMapping, ...{ 7: 'fillingMode' } }
  // renderInput(fieldData) {},
}
