import { readConf, writeconf, semanticConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TEXT_LABEL'),
    name: 'text',
    attrs: { type: 'text' },
    icon:
      '<svg height="512pt" viewBox="0 -90 512 512" width="512pt" xmlns="http://www.w3.org/2000/svg"><path d="m452 0h-392c-33.085938 0-60 26.914062-60 60v212c0 33.085938 26.914062 60 60 60h392c33.085938 0 60-26.914062 60-60v-212c0-33.085938-26.914062-60-60-60zm20 272c0 11.027344-8.972656 20-20 20h-392c-11.027344 0-20-8.972656-20-20v-212c0-11.027344 8.972656-20 20-20h392c11.027344 0 20 8.972656 20 20zm-295-151v131h-40v-131h-57v-40h152v40zm40 91h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zm0 0"/></svg>'
  },
  attributes: {
    size: { label: _t('BAZ_FORM_EDIT_TEXT_SIZE'), value: '' },
    maxlength: { label: _t('BAZ_FORM_EDIT_TEXT_MAX_LENGTH'), value: '' },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    separator: { label: '' }, // separate important attrs from others
    subtype: {
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
    read: readConf,
    write: writeconf,
    semantic: semanticConf,
    pattern: {
      label: _t('BAZ_FORM_EDIT_TEXT_PATTERN'),
      value: '',
      placeholder: `${_t('BAZ_FORM_EDIT_ADVANCED_MODE')} Ex: [0-9]+ ou [A-Za-z]{3}, ...`
    }
  },
  advancedAttributes: ['read', 'write', 'semantic', 'pattern'],
  // disabledAttributes: [],
  renderInput(fieldData) {
    let string = `<input type="${fieldData.subtype}"`
    if (fieldData.subtype == 'url') {
      string += ` placeholder="${fieldData.value || ''}"/>`
    } else if (fieldData.subtype == 'range' || fieldData.subtype == 'number') {
      string += ` min="${fieldData.size || ''}" max="${fieldData.maxlength || ''}"/>`
    } else {
      string += ` value="${fieldData.value}"/>`
    }
    return { field: string }
  }
}
