import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_URL_LABEL'),
    name: 'url',
    attrs: { type: 'url' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#link"/></svg>'
  },
  attributes: {
    displayvideo: {
      label: _t('BAZAR_URL_DISPLAY_VIDEO'),
      options: { ' ': _t('NO'), displayvideo: _t('YES') }
    },
    ratio: {
      transient: true,
      label: _t('BAZAR_VIDEO_RATIO_LABEL'),
      options: { '': '16/9', '4par3': '4/3' }
    },
    maxwidth: {
      transient: true,
      label: _t('BAZAR_VIDEO_MAXWIDTH_LABEL'),
      value: '',
      description: _t('BAZAR_VIDEO_MAX_HINT')
    },
    maxheight: {
      transient: true,
      label: _t('BAZAR_VIDEO_MAXHEIGHT_LABEL'),
      value: '',
      description: _t('BAZAR_VIDEO_MAX_HINT')
    },
    options: {
      label: 'options',
      value: ''
    },
    class: {
      label: _t('BAZAR_VIDEO_POSITION_LABEL'),
      options: {
        '': 'standard',
        'pull-left': _t('BAZAR_VIDEO_POSITION_LEFT'),
        'pull-right': _t('BAZAR_VIDEO_POSITION_RIGHT')
      }
    },
    read_access: readConf,
    write_access: writeConf
  },
  advancedAttributes: ['read_access', 'write_access', 'hint', 'ratio', 'maxwidth', 'maxheight', 'options', 'class'],
  // disabledAttributes: [],
  editorSetup(api) {
    // `options` is the stored attribute: 'ratio|maxwidth|maxheight'; the three
    // visible inputs edit its parts. It stays hidden, like the position class.
    api.hide('options')
    const parts = (api.getValue('options') || '').split('|')
    if (parts.length > 1) {
      if (!api.getValue('ratio')) api.setValue('ratio', parts[0] || '', { silent: true })
      if (!api.getValue('maxwidth')) api.setValue('maxwidth', parts[1] || '', { silent: true })
      if (!api.getValue('maxheight')) api.setValue('maxheight', parts[2] || '', { silent: true })
    }
    const packOptions = () => {
      const ratio = api.getValue('ratio') || ''
      const maxwidth = api.getValue('maxwidth') || ''
      const maxheight = api.getValue('maxheight') || ''
      const displayvideo = api.getValue('displayvideo') || ''
      api.setValue(
        'options',
        (displayvideo !== 'displayvideo' && !ratio && !maxwidth && !maxheight)
          ? ''
          : `${ratio}|${maxwidth}|${maxheight}`
      )
    }
    const applyVisibility = () => {
      const isVideo = api.getValue('displayvideo') === 'displayvideo';
      ['ratio', 'maxwidth', 'maxheight', 'class'].forEach(
        (attr) => (isVideo ? api.show(attr) : api.hide(attr))
      )
      packOptions()
    };
    ['ratio', 'maxwidth', 'maxheight'].forEach((attr) => api.onChange(attr, packOptions))
    api.onChange('displayvideo', applyVisibility)
    applyVisibility()
  }
}
