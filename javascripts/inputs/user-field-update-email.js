function updateEmailForUser() {
  document.querySelectorAll('[name=nomwiki]').forEach((nomwikiInput) => {
    const emailField = nomwikiInput.dataset.fieldnametoupdate
    const userEmail = nomwikiInput.dataset.useremail
    if (emailField && emailField.length > 0 && userEmail && userEmail.length > 0) {
      const form = nomwikiInput.closest('form#formulaire')
      const emailInput = form ? form.querySelector(`input#${emailField}`) : null
      // '0' is treated as empty too, matching the original jQuery `.val() == false` loose check
      if (emailInput && (emailInput.value.length === 0 || emailInput.value === '0')) {
        emailInput.value = userEmail
        emailInput.dispatchEvent(new Event('change', { bubbles: true }))
      }
    }
  })
}

updateEmailForUser()
document.addEventListener('yw-modal-open', updateEmailForUser)
