export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TABS'),
    name: 'tabs',
    attrs: { type: 'tabs' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#stack-2"/></svg>',
  },
  // Define an entire group of fields to be added to the stage at a time.
  set: {
    label: _t('BAZ_FORM_EDIT_TABS'),
    name: 'tabs',
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#stack-2"/></svg>',
    fields: [
      {
        type: 'tabs',
        label: _t('BAZ_FORM_EDIT_TABS'),
      },
      {
        type: 'tabchange',
        label: _t('BAZ_FORM_EDIT_TABCHANGE'),
      },
      {
        type: 'tabchange',
        label: _t('BAZ_FORM_EDIT_TABCHANGE'),
      },
      {
        type: 'tabchange',
        label: _t('BAZ_FORM_EDIT_TABCHANGE'),
      },
    ],
  },
  attributes: {
    form_titles: {
      label: _t('BAZ_FORM_EDIT_TABS_FOR_FORM'),
      value: _t('BAZ_FORM_EDIT_TABS_FORMTITLES_VALUE'),
      placeholder: _t('BAZ_FORM_EDIT_TABS_FORMTITLES_DESCRIPTION'),
      description: _t('BAZ_FORM_EDIT_TABS_FORMTITLES_DESCRIPTION'),
    },
    view_titles: {
      label: _t('BAZ_FORM_EDIT_TABS_FOR_ENTRY'),
      value: '',
      placeholder: _t('BAZ_FORM_EDIT_TABS_VIEWTITLES_DESCRIPTION'),
      description: _t('BAZ_FORM_EDIT_TABS_VIEWTITLES_DESCRIPTION'),
    },
    move_submit_button_to_last_tab: {
      label: _t('BAZ_FORM_EDIT_TABS_MOVESUBMITBUTTONTOLASTTAB_LABEL'),
      options: { '': _t('NO'), moveSubmit: _t('YES') },
      description: _t(
        'BAZ_FORM_EDIT_TABS_MOVESUBMITBUTTONTOLASTTAB_DESCRIPTION',
      ),
    },
    btn_color: {
      label: _t('BAZ_FORM_EDIT_TABS_BTNCOLOR_LABEL'),
      options: {
        'btn-primary': _t('PRIMARY'),
        'btn-secondary-1': `${_t('SECONDARY')} 1`,
        'btn-secondary-2': `${_t('SECONDARY')} 2`,
      },
    },
    btn_size: {
      label: _t('BAZ_FORM_EDIT_TABS_BTNSIZE_LABEL'),
      options: { '': _t('NORMAL_F'), 'btn-xs': _t('SMALL_F') },
    },
  },
  disabledAttributes: ['required', 'default', 'name', 'label'],
  editorHint: _t('BAZ_FORM_TABS_HINT', {
    br: '<BR>',
    'tabs-field-label': _t('BAZ_FORM_EDIT_TABS'),
    'tabchange-field-label': _t('BAZ_FORM_EDIT_TABCHANGE'),
  }),
  editorSetup(api) {
    // legacy syntax accepted | as titles separator, the editor normalizes to ,
    const titles = api.getValue('form_titles')
    if (typeof titles === 'string' && titles.includes('|')) {
      api.setValue('form_titles', titles.replace(/\|/g, ','))
    }
  },
}
