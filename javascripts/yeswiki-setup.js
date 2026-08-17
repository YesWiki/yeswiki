ywInitEach('body', () => {
  const driverSelect = document.getElementById('db_driver_select')
  if (driverSelect) {
    const updateFieldsVisibility = () => {
      const isSqlite = driverSelect.value === 'sqlite'
      document
        .querySelectorAll('.db-server-field, .db-server-info')
        .forEach((element) => {
          element.style.display = isSqlite ? 'none' : ''
        })
      ;['db_host', 'db_user', 'db_database'].forEach((id) => {
        const field = document.getElementById(id)
        if (field) field.required = !isSqlite && !field.disabled
      })
    }
    driverSelect.addEventListener('change', updateFieldsVisibility)
    updateFieldsVisibility()
  }

  const contentChoices = document.querySelectorAll(
    'input[type="radio"][name="contentSQL"]',
  )
  if (contentChoices.length) {
    const updateAdminForm = (useBackup) => {
      document.querySelectorAll('.admin-form').forEach((element) => {
        element.classList.toggle('hide', useBackup)
      })
      document.querySelectorAll('.admin-message').forEach((element) => {
        element.classList.toggle('hide', !useBackup)
      })
      document.querySelectorAll('.admin-form .yw-input').forEach((field) => {
        field.required = !useBackup && !field.disabled
      })
    }
    contentChoices.forEach((choice) => {
      choice.addEventListener('change', () =>
        updateAdminForm(choice.value !== 'default'),
      )
    })
    const checked = document.querySelector(
      'input[type="radio"][name="contentSQL"]:checked',
    )
    updateAdminForm(checked !== null && checked.value !== 'default')
  }
})
