let shortUrl = ''

function isValidUrl(string) {
  try {
    const url = new URL(string)
    return url
  } catch (error) {
    return false
  }
}

function arrayIncludesAllRequiredFields(arr, fields) {
  return fields.every((v) => arr.some((i) => i.id === v.id && i.type === v.type))
}

function blockDuplicationName(tag) {
  $('[name=duplicate-action]').attr('disabled', 'disabled').addClass('disabled')
  $('#newTag').parents('.form-group').removeClass('has-success').addClass('has-error')
  $('#pagetag-message').html(_t('PAGE_NOT_AVAILABLE', { tag }))
}

function validateDuplicationName(tag) {
  $('[name=duplicate-action]').removeAttr('disabled').removeClass('disabled')
  $('#newTag').parents('.form-group').removeClass('has-error').addClass('has-success')
  $('#pagetag-message').html(_t('PAGE_AVAILABLE', { tag }))
}

function checkPageExistence(url) {
  $.ajax({
    method: 'GET',
    url
  }).done(() => {
    blockDuplicationName(url.replace(`${shortUrl}/?api/pages/`, ''))
  }).fail((jqXHR) => {
    if (jqXHR.status === 404) {
      validateDuplicationName(url.replace(`${shortUrl}/?api/pages/`, ''))
    } else {
      blockDuplicationName(url.replace(`${shortUrl}/?api/pages/`, ''))
    }
  })
}

function handleLoginResponse(data) {
  if (data.isAdmin === true) {
    $('#login-message').html(_t('CONNECTED_AS_ADMIN', { user: data.user })).parents('.form-group')
      .removeClass('has-error')
      .addClass('has-success')
    $('.login-fields').addClass('hide')
    $('.duplication-fields').removeClass('hide')
    checkPageExistence(`${shortUrl}/?api/pages/${$('#newTag').val()}`)
  } else {
    $('#login-message').html(_t('CONNECTED_BUT_NOT_ADMIN', { user: data.user })).parents('.form-group')
      .removeClass('has-success')
      .addClass('has-error')
    $('.duplication-fields').addClass('hide')
    $('.login-fields').removeClass('hide')
  }
}

document.addEventListener('DOMContentLoaded', () => {
  $('.duplication-wiki-form, .duplication-login-form, #form-duplication').on('submit', (e) => {
    e.stopPropagation()
    return false
  })
  $('#url-wiki').on('change', () => {
    $('.login-fields, .duplication-fields').addClass('hide')
    $('#login-message').html('')
  })

  $('.btn-distant-login').on('click', () => {
    $.ajax({
      method: 'POST',
      url: `${shortUrl}/?api/login`,
      data: {
        username: $('#username').val(),
        password: $('#password').val()
      }
    }).done((data) => {
      handleLoginResponse(data)
    }).fail((jqXHR) => {
      toastMessage(jqXHR.responseJSON.error, 3000, 'alert alert-danger')
      if (jqXHR.status === 401) {
        $('#login-message').html(`<div class="text-danger">${_t('NOT_CONNECTED')}</div>`)
        $('.login-fields').removeClass('hide')
      }
    })
    return false
  })

  $('[name="duplicate-action"]').on('click', (e) => {
    const btnAction = e.currentTarget.value
    $.ajax({
      method: 'POST',
      url: `${shortUrl}/?api/pages/${$('#newTag').val()}/duplicate`,
      data: $('#form-duplication').serialize()
    }).done((d) => {
      if (btnAction === 'open') {
        document.location = `${shortUrl}/?${d.newTag}`
      } else if (btnAction === 'edit') {
        document.location = `${shortUrl}/?${d.newTag}/edit`
      } else {
        const url = document.location.href.replace(/\/duplicate.*/, '')
        document.location = url
      }
    }).fail((jqXHR) => {
      toastMessage(`${_t('ERROR')} ${jqXHR.status}`, 3000, 'alert alert-danger')
    })
    return false
  })

  $('.btn-verify-tag').on('click', () => {
    checkPageExistence(`${shortUrl}/?api/pages/${$('#newTag').val()}`)
  })

  $('.btn-verify-wiki').on('click', () => {
    let url = $('.duplication-wiki-form').find('#url-wiki').val()

    if (isValidUrl(url)) {
      let taburl = []
      if (url.search('wakka.php') > -1) {
        taburl = url.split('wakka.php')
      } else {
        taburl = url.split('?')
      }
      shortUrl = taburl[0].replace(/\/+$/g, '')
      $('#base-url').text(`${shortUrl}/?`)
      url = `${shortUrl}/?api/auth/me`
      $.ajax({
        method: 'GET',
        url
      }).done((data) => {
        handleLoginResponse(data)

        // if case of entry, we need to check if form id is available and compatible
        // or propose another id
        const formId = $('#form-id').val()
        if (typeof formId !== 'undefined') {
          const formUrl = `${shortUrl}/?api/forms/${formId}`
          $.ajax({
            method: 'GET',
            url: formUrl
          }).done((form) => {
            const requiredFields = form.prepared.filter((field) => field.required === true)
            // we check if the found formId is compatible
            if (arrayIncludesAllRequiredFields(window.sourceForm.prepared, requiredFields)) {
              $('#form-message').removeClass('has-error').addClass('has-success').find('.help-block')
                .html(_t('FORM_ID_IS_COMPATIBLE', { id: formId }))
            } else {
              $('#form-message').removeClass('has-success').addClass('has-error').find('.help-block')
                .html(_t('FORM_ID_NOT_AVAILABLE', { id: formId }))
            }
          }).fail((jqXHR) => {
            if (jqXHR.status === 404) {
              // the formId is available
              $('#form-message').removeClass('has-error').addClass('has-success').find('.help-block')
                .html(_t('FORM_ID_AVAILABLE', { id: formId }))
            }
          })
        }
      }).fail((jqXHR) => {
        if (jqXHR.status === 401) {
          $('#login-message').html(`<div class="text-danger">${_t('NOT_CONNECTED')}</div>`)
          $('.login-fields').removeClass('hide')
        } else {
          toastMessage(_t('NOT_WIKI_OR_OLD_WIKI', { url }), 3000, 'alert alert-danger')
        }
      })
    } else {
      toastMessage(_t('NOT_VALID_URL', { url }), 3000, 'alert alert-danger')
    }
  })
})
