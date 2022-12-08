function updateEmailForUser() {
  $('[name=nomwiki]').each(function() {
    const emailField = $(this).data('fieldnametoupdate')
    const userEmail = $(this).data('useremail')
    if (emailField.length > 0 && userEmail.length > 0) {
      const emailInput = $(this).closest('form#formulaire').find(`input#${emailField}`)
      if (emailInput.length > 0 && (emailInput.val().length == 0 || emailInput.val() == false)) {
        emailInput.val(userEmail)
        emailInput.trigger('change')
      }
    }
  })
}

updateEmailForUser()
$(document).on('yw-modal-open', updateEmailForUser)
