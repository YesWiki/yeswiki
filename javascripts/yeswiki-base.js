const DATATABLE_OPTIONS = {
  paging: false,
  language: {
    sProcessing: _t('DATATABLES_PROCESSING'),
    sSearch: _t('DATATABLES_SEARCH'),
    sLengthMenu: _t('DATATABLES_LENGTHMENU'),
    sInfo: _t('DATATABLES_INFO'),
    sInfoEmpty: _t('DATATABLES_INFOEMPTY'),
    sInfoFiltered: _t('DATATABLES_INFOFILTERED'),
    sInfoPostFix: '',
    sLoadingRecords: _t('DATATABLES_LOADINGRECORDS'),
    sZeroRecords: _t('DATATABLES_ZERORECORD'),
    sEmptyTable: _t('DATATABLES_EMPTYTABLE'),
    oPaginate: {
      sFirst: _t('FIRST'),
      sPrevious: _t('PREVIOUS'),
      sNext: _t('NEXT'),
      sLast: _t('LAST'),
    },
    oAria: {
      sSortAscending: _t('DATATABLES_SORTASCENDING'),
      sSortDescending: _t('DATATABLES_SORTDESCENDING'),
    },
  },
  fixedHeader: {
    header: true,
    footer: false,
  },
  dom:
    "<'row'<'col-sm-6'l><'col-sm-6'f>>" +
    "<'row'<'col-sm-12'tr>>" +
    "<'row'<'col-sm-6'i><'col-sm-6'<'pull-right'B>>>",
  buttons: [
    {
      extend: 'copy',
      className: 'btn btn-default',
      text: `<i class="far fa-copy"></i> ${_t('COPY')}`,
    },
    {
      extend: 'csv',
      className: 'btn btn-default',
      text: '<i class="fas fa-file-csv"></i> CSV',
    },
    {
      extend: 'print',
      className: 'btn btn-default',
      text: `<i class="fas fa-print"></i> ${_t('PRINT')}`,
    },
  ],
}

function toastMessage(
  message,
  duration = 3000,
  toastClass = 'alert alert-secondary-1',
) {
  const innerEl = document.createElement('div')
  innerEl.className = toastClass
  innerEl.textContent = message
  const toastEl = document.createElement('div')
  toastEl.className = 'toast-message'
  toastEl.appendChild(innerEl)
  const $toast = $(toastEl)
  $('body').after($toast)
  $toast.css('top', `${$('#yw-topnav').outerHeight(true) + 20}px`)
  $toast.css('opacity', 1)
  setTimeout(() => {
    $toast.css('opacity', 0)
  }, duration)
  setTimeout(() => {
    $toast.remove()
  }, duration + 300)
  $toast.addClass('visible')
}
;(function ($) {
  $('input[type=password]').each(function () {
    const vMe = $(this)

    $('<div>')
      .addClass('far fa-eye')
      .attr('title', _t('SHOW_PASSWORD'))
      .css({
        position: 'absolute',
        right: '0%',
        top: '50%',
        transform: 'translate(0px, -50%)',
        paddingRight: '1em',
        fontSize: '1em',
      })
      .on('click', function () {
        if (vMe.attr('type') == 'password') {
          vMe.attr('type', 'text')
          $(this)
            .removeClass('fa-eye')
            .attr('title', _t('HIDE_PASSWORD'))
            .addClass('fa-eye-slash')
        } else {
          vMe.attr('type', 'password')
          $(this)
            .addClass('fa-eye')
            .removeClass('fa-eye-slash')
            .attr('title', _t('SHOW_PASSWORD'))
        }
      })
      .insertAfter($(this))
  })

  $('a.active-link')
    .parent()
    .addClass('active-list')
    .parents('ul')
    .prev('a')
    .addClass('active-parent-link')
    .parent()
    .addClass('active-list')

  function addIframeHandlerTo(url) {
    const regexHasHandler = new RegExp(/\??.*\/(edit)?iframe(&.*)?/g)
    const regexOnDomain = new RegExp(`^${wiki.baseUrl}`)
    if (regexHasHandler.test(url)) {
      return url
    }
    if (regexOnDomain.test(url)) {
      return `${url}/iframe`
    }
    return url
  }

  function openModal(e) {
    e.stopPropagation()
    e.preventDefault()
    const $this = $(this)
    const titleText = $this.attr('title') || ''
    const size = ` ${$this.data('size')}`
    const iframe = $this.data('iframe')

    let $modal = $('#YesWikiModal')
    const yesWikiModalHtml = `
      <div class="modal-dialog${size} ${$this.data('header') === false ? 'no-header' : ''}">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h3></h3>
          </div>
          <div class="modal-body"></div>
          <button type="button" class="no-header-btn-close" data-dismiss="modal">&times;</button>
        </div>
      </div>`

    if ($modal.length === 0) {
      $('body').append(
        `<div class="modal fade" id="YesWikiModal">${yesWikiModalHtml}</div>`,
      )
      $modal = $('#YesWikiModal')
    } else {
      $modal.html(yesWikiModalHtml)
    }
    if (titleText.length > 0) {
      $modal.find('.modal-header h3').text($.trim(titleText))
    }

    let link = $this.attr('href')
    if (/\.(gif|jpg|jpeg|tiff|png)$/i.test(link)) {
      $modal
        .find('.modal-body')
        .html(
          `<img loading="lazy" class="center-block img-responsive" src="${link}" alt="" />`,
        )
    } else if (iframe === 1) {
      const modalTitle = $modal.find('.modal-header h3')
      if (modalTitle.length > 0) {
        const anchorText =
          modalTitle[0].innerText === 0
            ? link.substr(0, 128)
            : modalTitle[0].innerText
        $(modalTitle[0])
          .empty()
          .append($('<a>').attr('href', link).text(anchorText))
      }
      link = addIframeHandlerTo(link)
      $modal
        .find('.modal-body')
        .html(
          '<span id="yw-modal-loading" class="throbber"></span>' +
            `<iframe id="yw-modal-iframe" src="${link}" referrerpolicy="no-referrer"></iframe>`,
        )
      $('#yw-modal-iframe').on('load', () => {
        $('#yw-modal-loading').hide()
      })
    } else {
      try {
        const url = document.createElement('a')
        url.href = link
        const queryString = url.search
        let separator
        if (!queryString || queryString.length === 0) {
          separator = '?'
        } else {
          separator = '&'
        }
        link += `${separator}incomingurl=${encodeURIComponent(
          window.location.toString(),
        )}`
      } catch (er) {
        console.error(er)
      }
      const xhttp = new XMLHttpRequest()
      xhttp.onreadystatechange = function () {
        if (this.readyState === 4 && this.status === 200) {
          const xmlString = this.responseText
          const doc = new DOMParser().parseFromString(xmlString, 'text/html')
          const res = doc.scripts
          const l = res.length
          let i
          for (i = 0; i < l; i++) {
            const src = res[i].getAttribute('src')
            if (src) {
              var selection = document.querySelectorAll(`script[src="${src}"]`)
              if (!selection || selection.length == 0) {
                if (res[i].type === 'module') {
                  const newScript = document.createElement('script')
                  newScript.type = 'module'
                  newScript.src = src
                  document.body.appendChild(newScript)
                } else {
                  document.body.appendChild(document.importNode(res[i]))
                  $.getScript(src)
                }
              }
            } else {
              const script = res[i].innerHTML
              const pageScripts = document.scripts
              const selLenght = pageScripts.length
              var j
              for (j = 0; j < selLenght; j++) {
                if (
                  !pageScripts[j].hasAttribute('src') &&
                  script != pageScripts[j].innerHTML
                ) {
                  const newScript = document.importNode(res[i])
                  document.body.appendChild(newScript)
                }
              }
            }
          }
          const importedCSS = doc.querySelectorAll('link[rel="stylesheet"]')
          const le = importedCSS.length
          for (i = 0; i < le; i++) {
            const href = importedCSS[i].getAttribute('href')
            if (href) {
              const existingLink = document.querySelector(
                `link[href="${href}"]`,
              )
              if (!existingLink || existingLink.length === 0) {
                document.body.appendChild(document.importNode(importedCSS[i]))
              }
            }
          }
          $modal.find('.modal-body').load(`${link} .page`, () => {
            $(document).trigger('yw-modal-open')
            return false
          })
        }
      }
      xhttp.open('GET', link, true)
      xhttp.send()
    }
    $modal
      .modal({ keyboard: false })
      .modal('show')
      .on('hidden hidden.bs.modal', () => {
        $modal.remove()
      })

    return false
  }
  $(document).on('click', 'a.modalbox, a.modal, .modalbox a', openModal)

  $(document).on('click', 'a.newtab', function (e) {
    e.preventDefault()
    window.open($(this).attr('href'), '_blank')
  })

  $('.accordion-trigger').on('click', function () {
    if ($(this).next().find('.collapse').hasClass('in')) {
      $(this).find('.arrow').html('&#9658;')
    } else {
      $(this).find('.arrow').html('&#9660;')
    }
  })

  $('.no-dblclick, form, .page a, button, .dropdown-menu').on(
    'dblclick',
    (_e) => false,
  )

  $('.modal').appendTo(document.body)

  $('.remove-this-div-on-page-load').remove()

  $("[data-toggle='tooltip']").tooltip()
  $("[data-tooltip='tooltip']").tooltip()

  $('a[href="#search"]').on('click', function (e) {
    e.preventDefault()
    $(this).siblings('#search').addClass('open')
    $(this).siblings('#search').find('.search-query').focus()
  })

  $('#search, #search button.close-search').on('click keyup', function (e) {
    if (
      e.target == this ||
      $(e.target).hasClass('close-search') ||
      e.keyCode == 27
    ) {
      $(this).removeClass('open')
    }
  })

  $.fn.historyTabs = function () {
    const that = this
    window.addEventListener('popstate', (event) => {
      if (event.state) {
        $(that).filter(`[href="${event.state.url}"]`).tab('show')
      }
    })
    return this.each(function (index, element) {
      $(element).on('show.bs.tab', function () {
        const stateObject = { url: $(this).attr('href') }

        if (window.location.hash && stateObject.url !== window.location.hash) {
          window.history.pushState(
            stateObject,
            document.title,
            window.location.pathname +
              window.location.search +
              $(this).attr('href'),
          )
        } else {
          window.history.replaceState(
            stateObject,
            document.title,
            window.location.pathname +
              window.location.search +
              $(this).attr('href'),
          )
        }
      })
      if (!window.location.hash && $(element).is('.active')) {
        $(element).tab('show')
      } else if ($(this).attr('href') === window.location.hash) {
        $(element).tab('show')
      }
    })
  }
  $('a[data-toggle="tab"]').historyTabs()

  $('.navbar').on('dblclick', function (e) {
    e.stopPropagation()
    $('body').append(
      '<div class="modal fade" id="YesWikiModal">' +
        '<div class="modal-dialog">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
        `<h3>${_t('NAVBAR_EDIT_MESSAGE')}</h3>` +
        '</div>' +
        '<div class="modal-body">' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>',
    )

    const $editmodal = $('#YesWikiModal')
    $(this)
      .find('.include')
      .each(function () {
        const href = $(this)
          .attr('ondblclick')
          .replace("document.location='", '')
          .replace("';", '')
        const pagewiki = href
          .replace('/edit', '')
          .replace('http://yeswiki.dev/wakka.php?wiki=', '')
        $editmodal
          .find('.modal-body')
          .append(
            `<a href="${href}" class="btn btn-default btn-block">` +
              `<i class="fa fa-pencil-alt"></i> ${_t(
                'YESWIKIMODAL_EDIT_MSG',
              )} ${pagewiki}</a>`,
          )
      })

    $editmodal
      .find('.modal-body')
      .append(
        `<a href="#" data-dismiss="modal" class="btn btn-warning btn-xs btn-block">${+_t(
          'EDIT_OUPS_MSG',
        )}</a>`,
      )

    $editmodal
      .modal({ keyboard: true })
      .modal('show')
      .on('hidden hidden.bs.modal', () => {
        $editmodal.remove()
      })

    return false
  })

  const iframes = $('iframe.auto-resize')
  if (iframes.length > 0) {
    $.getScript('javascripts/vendor/iframe-resizer/iframeResizer.min.js')
      .done((_script, _textStatus) => {
        iframes.iFrameResize()
      })
      .fail((jqxhr, settings, exception) => {
        console.log(
          'Error getting script javascripts/vendor/iframe-resizer/iframeResizer.min.js',
          exception,
        )
      })
  }

  function getText(url, link) {
    let html
    $.get(url, (data) => {
      html = data
    }).done(() => {
      link.attr('data-content', html)
    })
  }

  $('.modalbox-hover').each(function () {
    getText(`${$(this).attr('href')}/html`, $(this))
  })
  $('.modalbox-hover').popover({
    trigger: 'hover',
    html: true,
    placement: 'right',
  })

  $('.new-window').attr('target', '_blank')
  $(document).on('yw-modal-open', () => {
    $('.new-window:not([target])').attr('target', '_blank')
  })

  $('#acl-switch-mode')
    .change(function () {
      if ($(this).prop('checked')) {
        $('.acl-simple').hide().val(null)
        $('.acl-advanced').slideDown()
      } else {
        $('.acl-single-container label').each(function () {
          $(this).after($(`select[name=${$(this).data('input')}]`))
        })
        $('.acl-simple').show()
        $('.acl-advanced').hide().val(null)
      }
    })
    .trigger('change')

  if (typeof $('.table').DataTable === 'function') {
    $('.table:not(.prevent-auto-init)').DataTable(DATATABLE_OPTIONS)
  }

  const $comments = $('.yeswiki-page-comments, #post-comment')

  function resetCommentForm(form) {
    form
      .attr('id', 'post-comment')
      .attr('class', '')
      .attr(
        'action',
        form.attr('action').replace(/api\/comments(\/.*)/gm, 'api/comments'),
      )
      .appendTo($('.yeswiki-page-comments').parent())
      .find('label')
      .removeClass('hide')
    $('.btn-cancel-comment').remove()
    $('#post-comment').find('.btn-post-comment').text(_t('SAVE'))
    window['aceditor-body'].editor.setValue('')
  }

  function replaceGuardFields(form, response) {
    const fresh = $('<div>').html(response?.botGuard || '')
    if (fresh.find('.yw-bot-guard-fields').length > 0) {
      form.find('.yw-bot-guard-fields').replaceWith(fresh.children())
    }
  }

  $comments.on('click', '.btn-post-comment', async function (e) {
    e.preventDefault()
    e.stopPropagation()
    const form = $(this).parent('form')
    const urlpost = form.attr('action')
    const widget = form.find('altcha-widget')[0]
    if (widget && widget.verify && widget.getState() !== 'verified') {
      await widget.verify()
    }
    $.ajax({
      type: 'POST',
      url: urlpost,
      data: form.serialize(),
      dataType: 'json',
      success(e) {
        replaceGuardFields(form, e)
        form.trigger('reset')
        window['aceditor-body'].editor.setValue('')
        toastMessage(e.success, 3000, 'alert alert-success')
        form.parents('.yw-comment').find('.comment-links').removeClass('hide')
        if (form.hasClass('comment-modify')) {
          form
            .closest('.yw-comment')
            .html($('<div>').html(e.html).find('.yw-comment').html())
          resetCommentForm(form)
        } else if (form.parent().hasClass('comment-reponses')) {
          form.parent().append(e.html)
          resetCommentForm(form)
        } else {
          $('.yeswiki-page-comments').append(e.html)
        }
      },
      error(e) {
        replaceGuardFields(form, e.responseJSON)
        toastMessage(e.responseJSON.error, 3000, 'alert alert-danger')
      },
    })
    return false
  })

  $comments.on('click', '.btn-answer-comment', function (e) {
    e.preventDefault()
    const com = $(this).parent().parent()
    $('.temporary-form')
      .parents('.yw-comment')
      .find('.comment-html:first')
      .removeClass('hide')
    $('.temporary-form')
      .parents('.yw-comment')
      .find('.comment-links:first')
      .removeClass('hide')
    if ($('.temporary-form').length > 0) {
      resetCommentForm($('.temporary-form'))
    }

    const formAnswer = com.find('.comment-reponses:first')
    $('#post-comment').appendTo(formAnswer)
    formAnswer
      .find('form')
      .attr('id', `form-comment-${com.data('tag')}`)
      .removeClass('hide')
      .addClass('temporary-form')
    formAnswer.find('[name="pagetag"]').val(com.data('tag'))
    formAnswer
      .find('form')
      .append(
        `<button class="btn-cancel-comment btn btn-sm btn-default">${_t('CANCEL')}</button>`,
      )
    com.find('.comment-links').addClass('hide')
    com.find('label').addClass('hide')

    return false
  })

  $comments.on('click', '.btn-edit-comment', function (e) {
    e.preventDefault()
    const com = $(this).parent().parent()

    com.find('.comment-html:first').addClass('hide')
    com.find('.comment-links:first').addClass('hide')

    $('.temporary-form')
      .parents('.yw-comment')
      .find('.comment-html:first')
      .removeClass('hide')
    $('.temporary-form')
      .parents('.yw-comment')
      .find('.comment-links:first')
      .removeClass('hide')
    if ($('.temporary-form').length > 0) {
      resetCommentForm($('.temporary-form'))
    }

    const formcom = com.find('.form-comment:first')
    $('#post-comment').appendTo(formcom)
    formcom
      .find('form')
      .attr('id', `form-comment-${com.data('tag')}`)
      .attr(
        'action',
        `${formcom.find('form').attr('action')}/${com.data('tag')}`,
      )
      .removeClass('hide')
      .addClass('temporary-form')
      .addClass('comment-modify')
    formcom.find('label').addClass('hide')
    window['aceditor-body'].editor.setValue(com.find('.comment-body').val())
    formcom.find('[name="pagetag"]').val(com.data('commenton'))
    formcom.find('.btn-post-comment').text(_t('MODIFY'))
    formcom
      .find('form')
      .append(
        `<button class="btn-cancel-comment btn btn-sm btn-default">${_t('CANCEL')}</button>`,
      )
    com.parents('.yw-comment').find('.comment-links').addClass('hide')

    return false
  })

  $comments.on('click', '.btn-cancel-comment', function (e) {
    e.preventDefault()
    const com = $(this).parent().parent().parent()
    com.find('.comment-html:first').removeClass('hide')
    com.find('.comment-links:first').removeClass('hide')
    com.parents('.yw-comment').find('.comment-links').removeClass('hide')
    resetCommentForm($(`#form-comment-${com.data('tag')}`))
    return false
  })

  $comments.on('click', '.btn-delete-comment', function (e) {
    if (confirm(_t('DELETE_COMMENT_AND_ANSWERS'))) {
      e.preventDefault()
      const link = $(this)
      $.ajax({
        type: 'POST',
        url: link.attr('href'),
        dataType: 'json',
        success(e) {
          link.closest('.yw-comment').slideUp(250, function () {
            $(this).remove()
          })
          toastMessage(e.success, 3000, 'alert alert-success')
        },
        error(e) {
          toastMessage(e.responseJSON.error, 3000, 'alert alert-danger')
        },
      })
    }
    return false
  })

  $('.reactions-container').each((i, val) => {
    const userReaction = $(val).find('.user-reaction').length
    const nbReactionLeft = $(val).find('.max-reaction').text()
    $(val)
      .find('.max-reaction')
      .text(nbReactionLeft - userReaction)
  })

  const reactionManagementHelper = {
    renderAjaxError(translation, jqXHR, textStatus, errorThrown) {
      const message = _t(translation, {
        error: `${textStatus} / ${errorThrown}${jqXHR.responseJSON.error != null ? `:${jqXHR.responseJSON.error}` : ''}`,
      })
      if (typeof toastMessage == 'function') {
        toastMessage(message, 3000, 'alert alert-danger')
      } else {
        alert(message)
      }
      if (jqXHR.responseJSON.exceptionMessage != null) {
        console.warn(jqXHR.responseJSON.exceptionMessage)
      }
    },
    deleteATag(elem) {
      const url = $(elem).attr('href')
      $.ajax({
        method: 'GET',
        url,
        success() {
          const table = $(elem).closest('table')
          if (table.length != 0) {
            console.log(table.DataTable())
            table.DataTable().row($(elem).closest('tr')).remove().draw()
          }
        },
        error(jqXHR, textStatus, errorThrown) {
          reactionManagementHelper.renderAjaxError(
            'REACTION_NOT_POSSIBLE_TO_DELETE_REACTION',
            jqXHR,
            textStatus,
            errorThrown,
          )
        },
      })
    },
    deleteTags(headElem) {
      const table = $(headElem).closest('table')
      if (table.length != 0) {
        $(table)
          .find('.btn-delete-reaction:not(.btn-delete-all)')
          .each(function () {
            reactionManagementHelper.deleteATag($(this))
          })
      }
    },
  }

  $('.link-reaction').click(function (event) {
    event.preventDefault()
    event.stopPropagation()
    const extractData = (item) => {
      const nb = $(item).find('.reaction-numbers')
      return {
        url: $(item).attr('href'),
        data: $(item).data(),
        nb,
        nbInit: parseInt(nb.text()),
      }
    }
    const { url, data, nb, nbInit } = extractData(this)
    const deleteUserReaction = async (url, data, nb, nbInit, link) => {
      const p = new Promise((resolve, reject) => {
        let currentReactionId = data.reactionid
        if ('oldId' in data && (data.oldId === true || data.oldId === 'true')) {
          currentReactionId = 'reactionField'
        }
        $.ajax({
          method: 'GET',
          url: `${url}/${currentReactionId}/${data.id}/${data.pagetag}/${data.username}/delete`,
          success() {
            nb.text(nbInit - 1)
            $(link).removeClass('user-reaction')
            const nbReactionLeft = parseFloat(
              $(link)
                .parents('.reactions-container')
                .find('.max-reaction')
                .text(),
            )
            $(link)
              .parents('.reactions-container')
              .find('.max-reaction')
              .text(nbReactionLeft + 1)
            resolve()
          },
          error(jqXHR, textStatus, errorThrown) {
            reactionManagementHelper.renderAjaxError(
              'REACTION_NOT_POSSIBLE_TO_DELETE_REACTION',
              jqXHR,
              textStatus,
              errorThrown,
            )
            reject()
          },
        })
      })
      return await p.then((...args) => Promise.resolve(...args))
    }
    if (url !== '#') {
      if ($(this).hasClass('user-reaction')) {
        if (typeof blockReactionRemove !== 'undefined' && blockReactionRemove) {
          if (blockReactionRemoveMessage) {
            if (typeof toastMessage == 'function') {
              toastMessage(
                blockReactionRemoveMessage,
                3000,
                'alert alert-warning',
              )
            } else {
              alert(blockReactionRemoveMessage)
            }
          }
          return false
        }
        const link = $(this)
        deleteUserReaction(url, data, nb, nbInit, link).catch((_e) => {})
        return false
      }
      const nbReactionLeft = parseFloat(
        $(this).parents('.reactions-container').find('.max-reaction').text(),
      )
      if (
        url !== '#' &&
        nbReactionLeft == 0 &&
        typeof blockReactionRemove !== 'undefined' &&
        blockReactionRemove === true
      ) {
        const previous = $(this)
          .closest('.reactions-flex')
          .find('.user-reaction')
          .first()
        if (
          typeof previous === 'object' &&
          'length' in previous &&
          previous.length > 0
        ) {
          const {
            url: previousUrl,
            data: previousData,
            nb: previousNb,
            nbInit: previousNbInit,
          } = extractData(previous)
          if (previousUrl !== '#') {
            deleteUserReaction(
              previousUrl,
              previousData,
              previousNb,
              previousNbInit,
              $(previous),
            )
              .then(() => {
                $(this).click()
              })
              .catch((_e) => {})
            return false
          }
        }
      }
      if (nbReactionLeft > 0) {
        const link = $(this)
        $.ajax({
          method: 'POST',
          url,
          data,
          success() {
            $(link)
              .find('.reaction-numbers')
              .text(nbReactionLeft - 1)

            nb.text(nbInit + 1)
            $(link).addClass('user-reaction')
            $(link)
              .parents('.reactions-container')
              .find('.max-reaction')
              .text(nbReactionLeft - 1)
          },
          error(jqXHR, textStatus, errorThrown) {
            reactionManagementHelper.renderAjaxError(
              'REACTION_NOT_POSSIBLE_TO_ADD_REACTION',
              jqXHR,
              textStatus,
              errorThrown,
            )
          },
        })
      } else {
        const message =
          "Vous n'avez plus de choix possibles, vous pouvez retirer un choix existant pour changer"
        if (typeof toastMessage == 'function') {
          toastMessage(message, 3000, 'alert alert-warning')
        } else {
          alert(message)
        }
      }
      return false
    }
  })

  $('.btn-delete-reaction').on('click', function (e) {
    e.preventDefault()
    if (!$(this).hasClass('btn-delete-all')) {
      if (confirm(_t('REACTION_CONFIRM_DELETE'))) {
        reactionManagementHelper.deleteATag($(this))
      }
    } else if (confirm(_t('REACTION_CONFIRM_DELETE_ALL'))) {
      reactionManagementHelper.deleteTags($(this))
    }
  })
})(jQuery)

$('#commentsTableDeleteModal.modal').on('shown.bs.modal', function (event) {
  multiDeleteService.initProgressBar($(this))
  $(this).find('.modal-body .multi-delete-results').html('')
  const deleteButton = $(this).find('button.start-btn-delete-comment')
  $(deleteButton).removeAttr('disabled')
  const button = $(event.relatedTarget)
  const name = $(button).data('name')
  $(this).find('#commentToDelete').text(name)
  $(deleteButton).data('name', name)
  $(deleteButton).data('targetNode', button)
  $(deleteButton).data('modal', this)
  if (!$(deleteButton).hasClass('eventSet')) {
    $(deleteButton).addClass('eventSet')
    $(deleteButton).on('click', function () {
      $(this).attr('disabled', 'disabled')
      $(this).tooltip('hide')
      const name = $(this).data('name')
      const targetNode = $(this).data('targetNode')
      const modal = $(this).data('modal')

      $.ajax({
        method: 'post',
        url: wiki.url(`api/comments/${name}/delete`),
        timeout: 30000,
        error(e) {
          multiDeleteService.addErrorMessage(
            $(modal),
            `${_t('COMMENT_NOT_DELETED', { comment: name })} : ${
              e.responseJSON && e.responseJSON.error ? e.responseJSON.error : ''
            }`,
          )
        },
        success() {
          multiDeleteService.removeLine(
            $(targetNode).closest('.dataTables_wrapper').prop('id'),
            name,
          )
          $(modal)
            .find('.modal-body .multi-delete-results')
            .first()
            .append($('<div>').text(_t('COMMENT_DELETED')))
        },
        complete() {
          multiDeleteService.updateProgressBar($(modal), ['test'], 0)
        },
      })
    })
  }
})

function checkAll(state) {
  const checkboxes = document.querySelectorAll('input.selectpage')
  const newState = [true, 'true', 1, '1'].includes(state)
  checkboxes.forEach((checkbox) => {
    if (checkbox.type == 'checkbox') {
      checkbox.checked = newState
    }
  })
}

$('.tab-content [data-toggle="tab"]').on('click', function () {
  const base = $(this).closest('.tab-content').prev()
  $(base).find('.active').removeClass('active')
  $(base)
    .find(`[href="${$(this).attr('href')}"]`)
    .parent()
    .addClass('active')
  $(base)
    .find(`a[href="${$(this).attr('href')}"]`)
    .tab('show')
  if (window.location != parent.parent.location) {
    $('html, body').animate({ scrollTop: $(base).offset().top }, 500)
    try {
      $(base).get(0).scrollIntoView()
      window.parent.scrollBy(0, -80)
    } catch (error) {
      console.error(error)
    }
  } else {
    $('html, body').animate({ scrollTop: $(base).offset().top - 80 }, 500)
  }
})

$('#yw-a11y-jump-content').click(() => {
  setTimeout(() => {
    $('#yw-topnav').removeClass('nav-down').addClass('nav-up')
    $('body').removeClass('nav-down').addClass('nav-up')
  }, 300)
})

document.addEventListener(
  'statechange',
  (event) => {
    const widget = event.target
    const fields = widget.closest?.('.yw-bot-guard-fields')
    if (!fields || widget.tagName !== 'ALTCHA-WIDGET') return
    const verified = event.detail?.state === 'verified'
    widget.style.display = verified ? 'none' : ''
    fields.querySelector('.yw-bot-guard-active').hidden = !verified
  },
  true,
)

window.checkAll = checkAll
