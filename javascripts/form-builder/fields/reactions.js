import { readConf, writeconf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_REACTIONS_FIELD'),
    name: 'reactions',
    attrs: { type: 'reactions' },
    icon: '<i class="far fa-thumbs-up"></i>'
  },
  attributes: {
    label_reaction: {
      label: _t('BAZ_REACTIONS_FIELD_ACTIVATE_LABEL'),
      value: '',
      placeholder: _t('BAZ_ACTIVATE_REACTIONS'),
      description: _t('BAZ_REACTIONS_FIELD_ACTIVATE_HINT')
    },
    default: {
      label: _t('BAZ_REACTIONS_FIELD_DEFAULT_ACTIVATION_LABEL'),
      options: { oui: _t('YES'), non: _t('NO') }
    },
    labels: {
      label: _t('BAZ_REACTIONS_FIELD_LABELS_LABEL'),
      value: '',
      description: _t('BAZ_REACTIONS_FIELD_LABELS_HINT')
    },
    images: {
      label: _t('BAZ_REACTIONS_FIELD_IMAGES_LABEL'),
      value: '',
      placeholder: _t('BAZ_REACTIONS_FIELD_IMAGES_PLACEHOLDER'),
      description: _t('BAZ_REACTIONS_FIELD_IMAGES_HINT')
    },
    ids: {
      label: _t('BAZ_REACTIONS_FIELD_IDS_LABEL'),
      value: '',
      description: _t('BAZ_REACTIONS_FIELD_IDS_HINT')
    },
    read_access: readConf,
    write_access: writeconf
  },
  disabledAttributes: [
    'label', 'required'
  ],
  renderInput(field) {
    return { field: `<i class="far fa-thumbs-up"></i> ${field.label_reaction || _t('BAZ_ACTIVATE_REACTIONS')}` }
  }
}
