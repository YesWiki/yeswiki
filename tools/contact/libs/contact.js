$(document).ready(() => {
  $('body').on('click', '.mail-submit', function (e) {
    e.stopPropagation()
    const form = $(this).parents('.ajax-mail-form')
    const inputsreq = form.find('input[required], textarea[required]')

    let atleastonefieldnotvalid = false
    let atleastonemailfieldnotvalid = false

    form.find('.help-block').remove()

    if (inputsreq.length > 0) {
      inputsreq.each(function () {
        if (
          !(
            $(this).val().length === 0 ||
            $(this).val() === '' ||
            $(this).val() === '0'
          )
        ) {
          $(this).parents('.form-group').removeClass('has-error')
        } else {
          atleastonefieldnotvalid = true
          $(this).parents('.form-group').addClass('has-error')
          $('<span>')
            .addClass('help-block')
            .text(_t('CONTACT_REQUIRED_FIELD'))
            .appendTo($(this).parents('.form-group'))
        }
      })
    }

    form.find('input[type=email]').each(function () {
      const reg = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
      const address = $(this).val()
      if (
        reg.test(address) === false &&
        !(address === '' && $(this).attr('required') !== 'required')
      ) {
        atleastonemailfieldnotvalid = true
        $(this).parents('.form-group').addClass('has-error')
        $('<span>')
          .addClass('help-block')
          .text(_t('CONTACT_EMAIL_NOT_VALID'))
          .appendTo($(this).parents('.form-group'))
      } else {
        $(this).parents('.form-group').removeClass('has-error')
      }
    })

    if (
      atleastonefieldnotvalid === true ||
      atleastonemailfieldnotvalid === true
    ) {
      $('html, body').animate(
        { scrollTop: form.find('.has-error:first').offset().top - 80 },
        800,
      )
    } else {
      submitWhenVerified(form)
    }

    return false
  })
})

async function submitWhenVerified(form) {
  await verifyBotGuard(form)
  $.ajax({
    type: 'POST',
    url: form.attr('action'),
    data: form.serialize(),
    success(msg) {
      const response = $('<div>').html(msg)
      form.find('.alert').remove()
      refreshBotGuardFields(form, response)
      form.prepend(response.children())
      if (form.find('.alert-success').length > 0) {
        form[0].reset()
      }
    },
  })
}
