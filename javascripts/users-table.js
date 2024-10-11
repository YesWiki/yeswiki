const usersTableService = {
  isRunning: false,
  createUser(elem) {
    $(elem).tooltip('hide')
    const form = $(elem).closest('form')
    if (form.length > 0) {
      const inputName = $(form).find('[name=name]')
      const name = inputName.length > 0 ? $(inputName).val() : ''
      const inputEmail = $(form).find('[name=email]')
      const email = inputEmail.length > 0 ? $(inputEmail).val() : ''
      $.ajax({
        method: 'post',
        url: wiki.url('api/users'),
        data: {
          name,
          email
        },
        success(data) {
          const userName = data.user.name
          const userEmail = data.user.email
          const userLink = data.user.link
          const { signuptime } = data.user
          // append In Datable
          const table = $(form).siblings('.dataTables_wrapper').first()
          const tableId = $(table).prop('id').slice(0, -'_wrapper'.length)
          inputName.val('')
          inputEmail.val('')
          $(`#${tableId}`).DataTable().row.add([
            '',
            userName,
            '',
            userEmail,
            signuptime,
            '',
            ''
          ]).draw()
          if (userLink !== '') {
            $(`#users-table-link-change-password`).html("<br/><label>"+_t('LINK_TO_CHANGE_PASSWORD')+"</label><br/><a href='"+userLink+"' target='_blank'>"+userLink+"</a>")
          }
          toastMessage(_t('USERSTABLE_USER_CREATED', { name: userName }), 1100, 'alert alert-success')
        },
        error(e) {
          toastMessage(_t('USERSTABLE_USER_NOT_CREATED', { name, error: e.responseJSON && e.responseJSON.error ? e.responseJSON.error : '' }), 3000, 'alert alert-danger')
        },
        complete() {
          $(elem).removeAttr('disabled')
          usersTableService.isRunning = false
        }
      })
    }
  },
  deleteUser(event) {
    event.preventDefault()
    if (!usersTableService.isRunning) {
      usersTableService.isRunning = true
      const elem = event.target
      if (elem) {
        $(elem).attr('disabled', 'disabled')
        $(elem).tooltip('hide')
        const name = $(elem).data('name')
        const csrfToken = wiki.antiCsrfToken || 'error wiki has not antiCsrfToken'
        const targetNode = $(elem).data('targetNode')
        const modal = $(elem).data('modal')

        $.ajax({
          method: 'post',
          url: wiki.url(`api/users/${name}/delete`),
          data: { csrfToken },
          timeout: 30000, // 30 seconds
          error(e) {
            multiDeleteService.addErrorMessage(
              $(modal),
              `${_t('USERSTABLE_USER_NOT_DELETED', { username: name })} : ${
                e.responseJSON && e.responseJSON.error ? e.responseJSON.error : ''}`
            )
          },
          success() {
            multiDeleteService.removeLine($(targetNode).closest('.dataTables_wrapper').prop('id'), name)
            $(modal).find('.modal-body .multi-delete-results').first().append(
              $('<div>').text(_t('USERSTABLE_USER_DELETED', { username: name }))
            )
          },
          complete() {
            multiDeleteService.updateProgressBar($(modal), ['test'], 0)
            usersTableService.isRunning = false
          }
        })
      }
    }
  }
}

$('form.form-inline button.create-user').on('click', (event) => {
  event.preventDefault()
  if (!usersTableService.isRunning) {
    usersTableService.isRunning = true
    const elem = event.target
    if (elem) {
      $(elem).attr('disabled', 'disabled')
      usersTableService.createUser(elem)
    }
  }
})

$('#userTableDeleteModal.modal').on('shown.bs.modal', function(event) {
  multiDeleteService.initProgressBar($(this))
  $(this).find('.modal-body .multi-delete-results').html('')
  const deleteButton = $(this).find('button.start-btn-delete-user')
  $(deleteButton).removeAttr('disabled')
  const button = $(event.relatedTarget) // Button that triggered the modal
  const name = $(button).data('name')
  const csrfToken = $(button).closest('tr').find(`td > label > input[data-itemId="${name}"][data-csrfToken]`).first()
    .data('csrftoken')
  $(this).find('#userNameToDelete').text(name)
  $(deleteButton).data('name', name)
  $(deleteButton).data('csrfToken', csrfToken)
  $(deleteButton).data('targetNode', button)
  $(deleteButton).data('modal', this)
  $(deleteButton).on('click', usersTableService.deleteUser)
})
