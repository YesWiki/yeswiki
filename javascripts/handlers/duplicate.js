let shortUrl = ''

function isValidUrl(string) {
  try {
    const url = new URL(string)
    return url
  } catch (error) {
    return false
  }
}

function blockDuplicationName(tag) {
  $('[name=duplicate-action]').attr('disabled', 'disabled').addClass('disabled')
  $('#pageTag').parents('.form-group').removeClass('has-success').addClass('has-error')
  $('#pagetag-message').html(_t('PAGE_NOT_AVAILABLE', { tag }))
}

function validateDuplicationName(tag) {
  $('[name=duplicate-action]').removeAttr('disabled').removeClass('disabled')
  $('#pageTag').parents('.form-group').removeClass('has-error').addClass('has-success')
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
    checkPageExistence(`${shortUrl}/?api/pages/${$('#pageTag').val()}`)
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
  $('#urlWiki').on('change', () => {
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
    var btnAction = e.currentTarget.value
    $.ajax({
      method: 'POST',
      url: `${shortUrl}/?api/pages/${$('#pageTag').val()}/duplicate`,
      data: $('#form-duplication').serialize()
    }).done((data) => {
      if (btnAction == 'open') {
        location = `${shortUrl}/?${data.pageTag}`
      } else if (btnAction == 'edit') {
        location = `${shortUrl}/?${data.pageTag}/edit`
      } else {
        let url = location.href.replace(/\/duplicate.*/, '')
        location = url
      }
    }).fail((jqXHR) => {
      toastMessage(`${_t('ERROR')} ${jqXHR.status}`, 3000, 'alert alert-danger')
    })
    return false
  })

  $('.btn-verify-tag').on('click', () => {
    checkPageExistence(`${shortUrl}/?api/pages/${$('#pageTag').val()}`)
  })

  $('.btn-verify-wiki').on('click', () => {
    let url = $('#urlWiki').val()

    if (isValidUrl(url)) {
      let taburl = []
      if (url.search('wakka.php') > -1) {
        taburl = url.split('wakka.php')
      } else {
        taburl = url.split('?')
      }
      shortUrl = taburl[0].replace(/\/+$/g, '')
      $('#baseUrl').text(`${shortUrl}/?`)
      url = `${shortUrl}/?api/auth/me`
      $.ajax({
        method: 'GET',
        url
      }).done((data) => {
        handleLoginResponse(data)
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