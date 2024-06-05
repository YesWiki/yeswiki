document.addEventListener('DOMContentLoaded', () => {
  // on init, don't require wikiadmin fields, backup database restore doesn't need them
  $('.admin-form [required]').prop('required', false)

  $('[name="contentSQL"]').on('click', function() {
    if ($(this).val() === 'private/backups/content.sql') {
      $('.admin-form').addClass('hide')
      $('.admin-message').removeClass('hide')
      $('.admin-form [required]').prop('required', false)
    } else if ($(this).val() === 'default') {
      $('.admin-form').removeClass('hide')
      $('.admin-message').addClass('hide')
      $('.admin-form .form-control').prop('required', true)
    }
  })
})
