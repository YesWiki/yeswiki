$(document).ready(function () {
  let buttons = $('#formulaire .form-actions.form-group');
  let target = $('.anchor-for-for-actions').last();
  if (typeof buttons !== 'undefined' && buttons.length > 0 && typeof target !== 'undefined' && target.length){
    target.prepend(buttons);
  }
});