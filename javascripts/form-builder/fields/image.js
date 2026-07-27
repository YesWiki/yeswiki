import { readConf, writeconf } from './commons/attributes.js'

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
    thumbnail_height: { label: _t('BAZ_FORM_EDIT_IMAGE_HEIGHT'), value: '300' },
    thumbnail_width: { label: _t('BAZ_FORM_EDIT_IMAGE_WIDTH'), value: '400' },
    image_height: { label: _t('BAZ_FORM_EDIT_IMAGE_HEIGHT_RESIZE'), value: '600' },
    image_width: { label: _t('BAZ_FORM_EDIT_IMAGE_WIDTH_RESIZE'), value: '800' },
    image_class: {
      label: _t('BAZ_FORM_EDIT_IMAGE_ALIGN_LABEL'),
      value: 'right',
      options: { left: _t('LEFT'), right: _t('RIGHT') }
    },
    image_default: {
      label: _t('BAZ_FORM_EDIT_IMAGE_DEFAULT'),
      class: 'default-file',
      value: '',
      type: 'file',
      accept: 'image/*'
    },
    max_size: { label: _t('BAZ_FORM_EDIT_FILE_MAXSIZE_LABEL'), value: '' },
    read_access: readConf,
    write_access: writeconf
  },
  advancedAttributes: ['read_access', 'write_access', 'thumbnail_height', 'thumbnail_width', 'image_height', 'image_width', 'max_size'],
  // disabledAttributes: [],
  renderInput() {
    return { field: '<input type="file"/>' }
  }
}
