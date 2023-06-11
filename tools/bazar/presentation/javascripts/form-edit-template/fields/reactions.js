import renderHelper from './commons/render-helper.js'
import { readConf, writeconf, semanticConf, defaultMapping } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_REACTIONS_FIELD'),
    name: 'reactions',
    attrs: { type: 'reactions' },
    icon: '<i class="far fa-thumbs-up"></i>'
  },
  attributes: {
    fieldlabel: {
      label: _t('BAZ_REACTIONS_FIELD_ACTIVATE_LABEL'),
      value: '',
      placeholder: _t('BAZ_ACTIVATE_REACTIONS')
    },
    value: {
      label: _t('BAZ_REACTIONS_FIELD_DEFAULT_ACTIVATION_LABEL'),
      options: { oui: _t('YES'), non: _t('NO') }
    },
    labels: {
      label: _t('BAZ_REACTIONS_FIELD_LABELS_LABEL'),
      value: ''
    },
    images: {
      label: _t('BAZ_REACTIONS_FIELD_IMAGES_LABEL'),
      value: '',
      placeholder: _t('BAZ_REACTIONS_FIELD_IMAGES_PLACEHOLDER')
    },
    ids: {
      label: _t('BAZ_REACTIONS_FIELD_IDS_LABEL'),
      value: ''
    },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  disabledAttributes: [
    'label', 'required'
  ],
  attributesMapping: {
    ...defaultMapping,
    ...{
      2: 'ids',
      3: 'labels',
      4: 'images',
      6: 'fieldlabel'
    }
  },
  renderInput(field) {
    return {
      field: `<i class="far fa-thumbs-up"></i> ${field.fieldlabel || _t('BAZ_ACTIVATE_REACTIONS')}`,
      onRender() {
        renderHelper.defineLabelHintForGroup(field, 'fieldlabel', _t('BAZ_REACTIONS_FIELD_ACTIVATE_HINT'))
        renderHelper.defineLabelHintForGroup(field, 'ids', _t('BAZ_REACTIONS_FIELD_IDS_HINT'))
        renderHelper.defineLabelHintForGroup(field, 'images', _t('BAZ_REACTIONS_FIELD_IMAGES_HINT'))
        renderHelper.defineLabelHintForGroup(field, 'labels', _t('BAZ_REACTIONS_FIELD_LABELS_HINT'))
      }
    }
  }
}
