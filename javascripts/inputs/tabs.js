ywInitEach('.bazar-form .form-actions.yw-form-group', (buttons) => {
  const anchors = buttons
    .closest('form')
    .querySelectorAll('.anchor-for-for-actions')
  const target = anchors.length > 0 ? anchors[anchors.length - 1] : null
  if (target) {
    target.prepend(buttons)
  }
})
