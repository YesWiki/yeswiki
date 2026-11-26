export const COLUMN_LAYOUTS = {
  '6-6': {
    spans: [6, 6],
    short: '1/2+1/2',
    label: () => _t('BAZ_FORM_EDIT_COLUMNS_LAYOUT_6_6'),
  },
  '4-8': {
    spans: [4, 8],
    short: '1/3+2/3',
    label: () => _t('BAZ_FORM_EDIT_COLUMNS_LAYOUT_4_8'),
  },
  '8-4': {
    spans: [8, 4],
    short: '2/3+1/3',
    label: () => _t('BAZ_FORM_EDIT_COLUMNS_LAYOUT_8_4'),
  },
  '4-4-4': {
    spans: [4, 4, 4],
    short: '1/3×3',
    label: () => _t('BAZ_FORM_EDIT_COLUMNS_LAYOUT_4_4_4'),
  },
  '3-9': { spans: [3, 9], label: () => _t('BAZ_FORM_EDIT_COLUMNS_LAYOUT_3_9') },
  '9-3': { spans: [9, 3], label: () => _t('BAZ_FORM_EDIT_COLUMNS_LAYOUT_9_3') },
  '3-3-3-3': {
    spans: [3, 3, 3, 3],
    short: '1/4×4',
    label: () => _t('BAZ_FORM_EDIT_COLUMNS_LAYOUT_3_3_3_3'),
  },
}

export const COLUMN_MARKERS = ['columns', 'columnchange', 'columnsend']

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_COLUMNS'),
    name: 'columns',
    attrs: { type: 'columns' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#columns"/></svg>',
  },
  set: {
    label: _t('BAZ_FORM_EDIT_COLUMNS'),
    name: 'columns',
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#columns"/></svg>',
    fields: [
      {
        type: 'columns',
        layout: '6-6',
      },
      {
        type: 'columnchange',
      },
      {
        type: 'columnsend',
      },
    ],
  },
  attributes: {
    layout: {
      label: _t('BAZ_FORM_EDIT_COLUMNS_LAYOUT_LABEL'),
      options: Object.fromEntries(
        Object.entries(COLUMN_LAYOUTS).map(([key, { label }]) => [
          key,
          label(),
        ]),
      ),
    },
  },
  disabledAttributes: [
    'required',
    'default',
    'name',
    'label',
    'width',
    'new_row',
  ],
  editorHint: _t('BAZ_FORM_COLUMNS_HINT'),
}
