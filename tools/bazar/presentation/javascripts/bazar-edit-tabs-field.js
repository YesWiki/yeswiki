$(document).ready(() => {
  const buttons = $('#formulaire .form-actions.form-group')
  const target = $('.anchor-for-for-actions').last()
  if (typeof buttons !== 'undefined' && buttons.length > 0 && typeof target !== 'undefined' && target.length > 0) {
    target.prepend(buttons)
  }
})
