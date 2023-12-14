import renderHelper from './commons/render-helper.js'

// Export configuration for the whiteboard field
export default {
  field: {  
    label: 'Whiteboard',
    name: 'whiteboard',
    attrs: { type: 'whiteboard' },
    icon: '<i class="fas fa-chalkboard"></i>'
  },
  // Return an object with field information and a callback for rendering
  renderInput(field) {
    return {
      field: '',
      onRender() {
        renderHelper.prependHint(field, _t('BAZ_WHITEBOARD_HINT', { '\\n': '<br>' }))
      }
    }
  },
}
