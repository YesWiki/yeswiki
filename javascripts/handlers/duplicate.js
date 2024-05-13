// Define a function that takes a string and returns true if it is a valid URL, false otherwise
function isValidUrl(string) {
  try {
    const url = new URL(string)
    return url
  } catch (error) {
    return false
  }
}

function handleLoginResponse(data) {
  if (data.isAdmin === true) {
    $('#login-message').html(`<div class="text-info">
            ${_t('CONNECTED_AS_ADMIN', { user: data.user })}
          </div>`)
    $('.login-fields').addClass('hide')
    $('.duplication-fields').removeClass('hide')
  } else {
    $('#login-message').html(`<div class="text-danger">
            ${_t('CONNECTED_BUT_NOT_ADMIN', { user: data.user })}
          </div>`)
    $('.login-fields').removeClass('hide')
  }
}

document.addEventListener('DOMContentLoaded', () => {
  let shortUrl = ''
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
