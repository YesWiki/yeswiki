import { selectConf, listsMapping } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_RADIO_LABEL'),
    name: 'radio-group',
    attrs: { type: 'radio-group' },
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-ui-radios" viewBox="0 0 16 16"><path d="M7 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-1zM0 12a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm7-1.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-1zm0-5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 8a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zM3 1a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm0 4.5a1.5 1.5 0 1 1 0-3 1.5 1"></svg>'
  },
  attributes: {
    ...selectConf,
    ...{
      fillingMode: {
        label: _t('BAZ_FORM_EDIT_FILLING_MODE_LABEL'),
        options: {
          ' ': _t('BAZ_FORM_EDIT_FILLING_MODE_NORMAL'),
          tags: _t('BAZ_FORM_EDIT_FILLING_MODE_TAGS')
        }
      },
      queries: {
        label: _t('BAZ_FORM_EDIT_QUERIES_LABEL'),
        value: '',
        placeholder: 'ex. : checkboxfiche6=PageTag ; cf. https://yeswiki.net/?LierFormulairesEntreEux'
      }
    }
  },
  defaultIdentifier: 'bf_choice',
  advancedAttributes: ['read', 'write', 'semantic', 'queries', 'fillingMode', 'options'],
  // disabledAttributes: [],
  attributesMapping: { ...listsMapping, ...{ 7: 'fillingMode' } }
  // renderInput(fieldData) {},
}
