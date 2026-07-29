import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TEXT_LABEL'),
    name: 'text',
    attrs: { type: 'text' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#cursor-text"/></svg>'
  },
  attributes: {
    size: { label: _t('BAZ_FORM_EDIT_TEXT_SIZE'), value: '' },
    max_chars: { label: _t('BAZ_FORM_EDIT_TEXT_MAX_LENGTH'), value: '' },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    separator: { label: '' }, // separate important attrs from others
    sub_type: {
      label: _t('BAZ_FORM_EDIT_TEXT_TYPE_LABEL'),
      options: {
        text: _t('BAZ_FORM_EDIT_TEXT_TYPE_TEXT'),
        number: _t('BAZ_FORM_EDIT_TEXT_TYPE_NUMBER'),
        range: _t('BAZ_FORM_EDIT_TEXT_TYPE_RANGE'),
        url: _t('BAZ_FORM_EDIT_TEXT_TYPE_URL'),
        password: _t('BAZ_FORM_EDIT_TEXT_TYPE_PASSWORD'),
        color: _t('BAZ_FORM_EDIT_TEXT_TYPE_COLOR')
      }
    },
    placeholder: { label: _t('BAZ_FORM_EDIT_PLACEHOLDER'), value: '' },
    read_access: readConf,
    write_access: writeConf,
    pattern: {
      label: _t('BAZ_FORM_EDIT_TEXT_PATTERN'),
      value: '',
      placeholder: `${_t('BAZ_FORM_EDIT_ADVANCED_MODE')} Ex: [0-9]+ ou [A-Za-z]{3}, ...`
    }
  },
  advancedAttributes: ['placeholder', 'read_access', 'write_access', 'pattern'],
  // disabledAttributes: [],
  editorSetup(api) {
    const applySubtype = () => {
      const subtype = api.getValue('sub_type')
      if (subtype === 'range' || subtype === 'number') {
        api.setLabel('max_chars', _t('BAZ_FORM_EDIT_MAX_VAL'))
        api.setLabel('size', _t('BAZ_FORM_EDIT_MIN_VAL'))
      } else {
        api.setLabel('max_chars', _t('BAZ_FORM_EDIT_MAX_LENGTH'))
        api.setLabel('size', _t('BAZ_FORM_EDIT_NB_CHARS'))
      }
      if (subtype === 'color') {
        api.hide('max_chars')
        api.hide('size')
      } else {
        api.show('max_chars')
        api.show('size')
      }
    }
    api.onChange('sub_type', applySubtype)
    applySubtype()
  }
}
