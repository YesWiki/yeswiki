export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CUSTOM_LABEL'),
    name: 'custom',
    attrs: { type: 'custom' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#help-circle"/></svg>',
  },
  attributes: Object.fromEntries(
    Array.from({ length: 15 }, (unused, i) => [
      `${i + 1}`,
      { label: `Param ${i + 1}`, value: '' },
    ]),
  ),
  disabledAttributes: ['name', 'label', 'required', 'default'],
}
