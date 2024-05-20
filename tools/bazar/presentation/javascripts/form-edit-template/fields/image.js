import { readConf, writeconf, semanticConf, defaultMapping } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_IMAGE_LABEL'),
    name: 'image',
    attrs: { type: 'image' },
    icon: '<i class="fas fa-image"></i>'
  },
  defaultIdentifier: 'bf_image',
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    thumb_height: { label: _t('BAZ_FORM_EDIT_IMAGE_HEIGHT'), value: '300' },
    thumb_width: { label: _t('BAZ_FORM_EDIT_IMAGE_WIDTH'), value: '400' },
    resize_height: { label: _t('BAZ_FORM_EDIT_IMAGE_HEIGHT_RESIZE'), value: '600' },
    resize_width: { label: _t('BAZ_FORM_EDIT_IMAGE_WIDTH_RESIZE'), value: '800' },
    align: {
      label: _t('BAZ_FORM_EDIT_IMAGE_ALIGN_LABEL'),
      value: 'right',
      options: { left: _t('LEFT'), right: _t('RIGHT') }
    },
    default_image: {
      label: _t('BAZ_FORM_EDIT_IMAGE_DEFAULT'),
      class: 'default-file',
      value: '',
      type: 'file',
      accept: 'image/*'
    },
    maxsize: { label: _t('BAZ_FORM_EDIT_FILE_MAXSIZE_LABEL'), value: '' },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  advancedAttributes: ['read', 'write', 'semantic', 'thumb_height', 'thumb_width', 'resize_height', 'resize_width', 'maxsize'],
  // disabledAttributes: [],
  attributesMapping: {
    ...defaultMapping,
    ...{
      1: 'name',
      3: 'thumb_height',
      4: 'thumb_width',
      5: 'resize_height',
      6: 'resize_width',
      7: 'align',
      13: 'default_image',
      14: 'maxsize'
    }
  },
  renderInput(fieldData) {
    return { field: '<input type="file"/>' }
  }
}
