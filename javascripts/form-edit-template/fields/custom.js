export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CUSTOM_LABEL'),
    name: 'custom',
    attrs: { type: 'custom' },
    icon: '<i class="fas fa-question-circle"></i>'
  },
  attributes: {
    param0: { label: 'Param0', value: '' },
    param1: { label: 'Param1', value: '' },
    param2: { label: 'Param2', value: '' },
    param3: { label: 'Param3', value: '' },
    param4: { label: 'Param4', value: '' },
    param5: { label: 'Param5', value: '' },
    param6: { label: 'Param6', value: '' },
    param7: { label: 'Param7', value: '' },
    param8: { label: 'Param8', value: '' },
    param9: { label: 'Param9', value: '' },
    param10: { label: 'Param10', value: '' },
    param11: { label: 'Param11', value: '' },
    param12: { label: 'Param12', value: '' },
    param13: { label: 'Param13', value: '' },
    param14: { label: 'Param14', value: '' },
    param15: { label: 'Param15', value: '' }
  },
  // disabledAttributes: [],
  attributesMapping: {
    0: 'param0',
    1: 'param1',
    2: 'param2',
    3: 'param3',
    4: 'param4',
    5: 'param5',
    6: 'param6',
    7: 'param7',
    8: 'param8',
    9: 'param9',
    10: 'param10',
    11: 'param11',
    12: 'param12',
    13: 'param13',
    14: 'param14',
    15: 'param15'
  },
  renderInput(field) {
    return { field: '' }
  }
}
