const DATATABLE_OPTIONS = {
  // responsive: true,
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
      sLast: _t('LAST')
    },
    oAria: {
      sSortAscending: _t('DATATABLES_SORTASCENDING'),
      sSortDescending: _t('DATATABLES_SORTDESCENDING')
    }
  },
  fixedHeader: {
    header: true,
    footer: false
  },
  dom:
    "<'row'<'col-sm-6'l><'col-sm-6'f>>"
    + "<'row'<'col-sm-12'tr>>"
    + "<'row'<'col-sm-6'i><'col-sm-6'<'pull-right'B>>>",
  buttons: [
    {
      extend: 'copy',
      className: 'btn btn-default',
      text: `<i class="far fa-copy"></i> ${_t('COPY')}`
    },
    {
      extend: 'csv',
      className: 'btn btn-default',
      text: '<i class="fas fa-file-csv"></i> CSV'
    },
    {
      extend: 'print',
      className: 'btn btn-default',
      text: `<i class="fas fa-print"></i> ${_t('PRINT')}`
    }
    // {
    //   extend: 'colvis',
    //   text: _t('DATATABLES_COLS_TO_DISPLAY')
    // },
  ]
}

function toastMessage(
  message,
  duration = 3000,
  toastClass = 'alert alert-secondary-1'
) {
  const $toast = $(
    `<div class="toast-message"><div class="${toastClass}">${message}</div></div>`
  )
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
// polyfill placeholder
(function($) {
  // gestion des classes actives pour les menus
  $('a.active-link')
    .parent()
    .addClass('active-list')
    .parents('ul')
    .prev('a')
    .addClass('active-parent-link')
    .parent()
    .addClass('active-list')

  // fenetres modales
  function openModal(e) {
    e.stopPropagation()
    e.preventDefault()
    const $this = $(this)
    let text = $this.attr('title') || ''
    const size = ` ${$this.data('size')}`
    const iframe = $this.data('iframe')
    if (text.length > 0) {
      text = `<h3>${$.trim(text)}</h3>`
    } else {
      text = '<h3></h3>'
    }

    let $modal = $('#YesWikiModal')
    const yesWikiModalHtml = `
      <div class="modal-dialog${size} ${$this.data('header') === false ? 'no-header' : ''}">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            ${text}
          </div>
          <div class="modal-body"></div>
          <button type="button" class="no-header-btn-close" data-dismiss="modal">&times;</button>
        </div>
      </div>`

    if ($modal.length === 0) {
      $('body').append(
        `<div class="modal fade" id="YesWikiModal">${yesWikiModalHtml}</div>`
      )
      $modal = $('#YesWikiModal')
    } else {
      $modal.html(yesWikiModalHtml)
    }

    let link = $this.attr('href')
    if (/\.(gif|jpg|jpeg|tiff|png)$/i.test(link)) {
      $modal
        .find('.modal-body')
        .html(
          `<img loading="lazy" class="center-block img-responsive" src="${link}" alt="" />`
        )
    } else if (iframe === 1) {
      const modalTitle = $modal.find('.modal-header h3')
      if (modalTitle.length > 0) {
        if (modalTitle[0].innerText === 0) {
          modalTitle[0].innerHTML = `<a href="${link}">${link.substr(0, 128)}</a>`
        } else {
          modalTitle[0].innerHTML = `<a href="${link}">${modalTitle[0].innerText}</a>`
        }
      }
      $modal
        .find('.modal-body')
        .html(
          '<span id="yw-modal-loading" class="throbber"></span>'
          + `<iframe id="yw-modal-iframe" src="${link}" referrerpolicy="no-referrer"></iframe>`
        )
      $('#yw-modal-iframe').on('load', () => {
        $('#yw-modal-loading').hide()
      })
    } else {
      // incomingurl can be usefull (per example for deletepage handler)
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
          window.location.toString()
        )}`
      } catch (er) {
        console.error(er)
      }
      // AJAX Request for javascripts
      const xhttp = new XMLHttpRequest()
      xhttp.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
          const xmlString = this.responseText
          const doc = new DOMParser().parseFromString(xmlString, 'text/html')
          // find scripts
          const res = doc.scripts
          const l = res.length
          let i
          for (i = 0; i < l; i++) {
            const src = res[i].getAttribute('src')
            if (src) {
              var selection = document.querySelectorAll(`script[src="${src}"]`)
              if (!selection || selection.length == 0) {
                // append script and load it only if not present
                document.body.appendChild(document.importNode(res[i]))
                $.getScript(src)
              }
            } else {
              const script = res[i].innerHTML
              // select all script of current page without src
              var selection = document.scripts
              const selLenght = selection.length
              var j
              for (j = 0; j < selLenght; j++) {
                if (
                  !selection[j].hasAttribute('src')
                  && script != selection[j].innerHTML
                ) {
                  const newScript = document.importNode(res[i])
                  document.body.appendChild(newScript)
                }
              }
            }
          }
          // find css
          const importedCSS = doc.querySelectorAll('link[rel="stylesheet"]')
          const le = importedCSS.length
          for (i = 0; i < le; i++) {
            const href = importedCSS[i].getAttribute('href')
            if (href) {
              var selection = document.querySelector(`link[href="${href}"]`)
              if (!selection || selection.length === 0) {
                // append link
                document.body.appendChild(document.importNode(importedCSS[i]))
              }
            }
          }
          // AJAX Request for content
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

  $(document).on('click', 'a.newtab', function(e) {
    e.preventDefault()
    window.open($(this).attr('href'), '_blank')
  })

  // on change l'icone de l'accordeon
  $('.accordion-trigger').on('click', function() {
    if ($(this).next().find('.collapse').hasClass('in')) {
      $(this).find('.arrow').html('&#9658;')
    } else {
      $(this).find('.arrow').html('&#9660;')
    }
  })

  // on enleve la fonction doubleclic dans des cas ou cela pourrait etre indesirable
  $('.no-dblclick, form, .page a, button, .dropdown-menu').on(
    'dblclick',
    (e) => false
  )

  // deplacer les fenetres modales en bas de body pour eviter que des styles s'appliquent
  $('.modal').appendTo(document.body)

  // Remove hidden div by ACL
  $('.remove-this-div-on-page-load').remove()

  /* tooltips */
  $("[data-toggle='tooltip']").tooltip()
  $("[data-tooltip='tooltip']").tooltip()

  // moteur de recherche utilisé dans un template
  $('a[href="#search"]').on('click', function(e) {
    e.preventDefault()
    $(this).siblings('#search').addClass('open')
    $(this).siblings('#search').find('.search-query').focus()
  })

  $('#search, #search button.close-search').on('click keyup', function(e) {
    if (
      e.target == this
      || $(e.target).hasClass('close-search')
      || e.keyCode == 27
    ) {
      $(this).removeClass('open')
    }
  })

  // se souvenir des tabs navigués
  $.fn.historyTabs = function() {
    const that = this
    window.addEventListener('popstate', (event) => {
      if (event.state) {
        $(that).filter(`[href="${event.state.url}"]`).tab('show')
      }
    })
    return this.each(function(index, element) {
      $(element).on('show.bs.tab', function() {
        const stateObject = { url: $(this).attr('href') }

        if (window.location.hash && stateObject.url !== window.location.hash) {
          window.history.pushState(
            stateObject,
            document.title,
            window.location.pathname
            + window.location.search
            + $(this).attr('href')
          )
        } else {
          window.history.replaceState(
            stateObject,
            document.title,
            window.location.pathname
            + window.location.search
            + $(this).attr('href')
          )
        }
      })
      if (!window.location.hash && $(element).is('.active')) {
        // Shows the first element if there are no query parameters.
        $(element).tab('show')
      } else if ($(this).attr('href') === window.location.hash) {
        $(element).tab('show')
      }
    })
  }
  $('a[data-toggle="tab"]').historyTabs()

  // double clic
  $('.navbar').on('dblclick', function(e) {
    e.stopPropagation()
    $('body').append(
      '<div class="modal fade" id="YesWikiModal">'
      + '<div class="modal-dialog">'
      + '<div class="modal-content">'
      + '<div class="modal-header">'
      + '<button type="button" class="close" data-dismiss="modal">&times;</button>'
      + `<h3>${_t('NAVBAR_EDIT_MESSAGE')}</h3>`
      + '</div>'
      + '<div class="modal-body">'
      + '</div>'
      + '</div>'
      + '</div>'
      + '</div>'
    )

    const $editmodal = $('#YesWikiModal')
    $(this)
      .find('.include')
      .each(function() {
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
            `<a href="${href}" class="btn btn-default btn-block">`
            + `<i class="fa fa-pencil-alt"></i> ${_t(
              'YESWIKIMODAL_EDIT_MSG'
            )} ${pagewiki}</a>`
          )
      })

    $editmodal
      .find('.modal-body')
      .append(
        `<a href="#" data-dismiss="modal" class="btn btn-warning btn-xs btn-block">${+_t(
          'EDIT_OUPS_MSG'
        )}</a>`
      )

    $editmodal
      .modal({ keyboard: true })
      .modal('show')
      .on('hidden hidden.bs.modal', () => {
        $editmodal.remove()
      })

    return false
  })

  // AUTO RESIZE IFRAME
  const iframes = $('iframe.auto-resize')
  if (iframes.length > 0) {
    $.getScript('javascripts/vendor/iframe-resizer/iframeResizer.min.js')
      .done((script, textStatus) => {
        iframes.iFrameResize()
      })
      .fail((jqxhr, settings, exception) => {
        console.log(
          'Error getting script javascripts/vendor/iframe-resizer/iframeResizer.min.js',
          exception
        )
      })
  }

  // get the html from a yeswiki page
  function getText(url, link) {
    let html
    $.get(url, (data) => {
      html = data
    }).done(() => {
      link.attr('data-content', html)
    })
  }

  $('.modalbox-hover').each(function() {
    getText(`${$(this).attr('href')}/html`, $(this))
  })
  $('.modalbox-hover').popover({
    trigger: 'hover',
    html: true, // permet d'utiliser du html
    placement: 'right' // position de la popover (top ou bottom ou left ou right)
  })

  // ouvrir les liens dans une nouvelle fenetre
  $('.new-window').attr('target', '_blank')
  $(document).on('yw-modal-open', () => {
    $('.new-window:not([target])').attr('target', '_blank')
  })

  // acl switch
  $('#acl-switch-mode')
    .change(function() {
      if ($(this).prop('checked')) {
        // show advanced
        $('.acl-simple').hide().val(null)
        $('.acl-advanced').slideDown()
      } else {
        $('.acl-single-container label').each(function() {
          $(this).after($(`select[name=${$(this).data('input')}]`))
        })
        $('.acl-simple').show()
        $('.acl-advanced').hide().val(null)
      }
    })
    .trigger('change')

  // tables
  if (typeof $('.table').DataTable === 'function') {
    $('.table:not(.prevent-auto-init)').DataTable(DATATABLE_OPTIONS)
  }

  /** comments */
  const $comments = $('.yeswiki-page-comments, #post-comment')

  function resetCommentForm(form) {
    form
      .attr('id', 'post-comment').attr('class', '')
      .attr('action', form.attr('action').replace(/api\/comments(\/.*)/gm, 'api/comments'))
      .appendTo($('.yeswiki-page-comments').parent())
      .find('label')
      .removeClass('hide')
    $('.btn-cancel-comment').remove()
    $('#post-comment').find('.btn-post-comment').text(_t('SAVE'))
    window['aceditor-body'].editor.setValue('')
  }

  // ajax post comment
  $comments.on('click', '.btn-post-comment', function(e) {
    e.preventDefault()
    const form = $(this).parent('form')
    const urlpost = form.attr('action')
    $.ajax({
      type: 'POST',
      url: urlpost,
      data: form.serialize(),
      dataType: 'json',
      success(e) {
        form.trigger('reset')
        window['aceditor-body'].editor.setValue('')
        toastMessage(e.success, 3000, 'alert alert-success')
        form.parents('.yw-comment').find('.comment-links').removeClass('hide')
        // we place the new comment in different places if its an answer, a modification or a new comment
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
        toastMessage(e.responseJSON.error, 3000, 'alert alert-danger')
      }
    })
    return false
  })

  // ajax answer comment
  $comments.on('click', '.btn-answer-comment', function(e) {
    e.preventDefault()
    const com = $(this).parent().parent()
    $('.temporary-form').parents('.yw-comment').find('.comment-html:first').removeClass('hide')
    $('.temporary-form').parents('.yw-comment').find('.comment-links:first').removeClass('hide')
    // a comment-form is already opened
    if ($('.temporary-form').length > 0) {
      resetCommentForm($('.temporary-form'))
    }

    // clone comment form and change some options
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
        `<button class="btn-cancel-comment btn btn-sm btn-default">${_t('CANCEL')}</button>`
      )
    com.find('.comment-links').addClass('hide')
    com.find('label').addClass('hide')

    return false
  })

  // ajax edit comment
  $comments.on('click', '.btn-edit-comment', function(e) {
    e.preventDefault()
    const com = $(this).parent().parent()

    // hide comment and comment links while editor is open
    com.find('.comment-html:first').addClass('hide')
    com.find('.comment-links:first').addClass('hide')

    $('.temporary-form').parents('.yw-comment').find('.comment-html:first').removeClass('hide')
    $('.temporary-form').parents('.yw-comment').find('.comment-links:first').removeClass('hide')
    // a comment-form is already opened
    if ($('.temporary-form').length > 0) {
      resetCommentForm($('.temporary-form'))
    }

    // clone comment form and change some options
    const formcom = com.find('.form-comment:first')
    $('#post-comment').appendTo(formcom)
    formcom
      .find('form')
      .attr('id', `form-comment-${com.data('tag')}`)
      .attr(
        'action',
        `${formcom.find('form').attr('action')}/${com.data('tag')}`
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
        `<button class="btn-cancel-comment btn btn-sm btn-default">${_t('CANCEL')}</button>`
      )
    com.parents('.yw-comment').find('.comment-links').addClass('hide')

    return false
  })

  // cancel comment edit
  $comments.on('click', '.btn-cancel-comment', function(e) {
    e.preventDefault()
    const com = $(this).parent().parent().parent()
    // restore html comment and links
    com.find('.comment-html:first').removeClass('hide')
    com.find('.comment-links:first').removeClass('hide')
    com.parents('.yw-comment').find('.comment-links').removeClass('hide')
    resetCommentForm($(`#form-comment-${com.data('tag')}`))
    return false
  })

  // ajax delete comment
  $comments.on('click', '.btn-delete-comment', function(e) {
    if (confirm(_t('DELETE_COMMENT_AND_ANSWERS'))) {
      e.preventDefault()
      const link = $(this)
      $.ajax({
        type: 'POST',
        url: link.attr('href'),
        dataType: 'json',
        success(e) {
          link.closest('.yw-comment').slideUp(250, function() {
            $(this).remove()
          })
          toastMessage(e.success, 3000, 'alert alert-success')
        },
        error(e) {
          toastMessage(e.responseJSON.error, 3000, 'alert alert-danger')
        }
      })
    }
    return false
  })
  // reaction

  // init user reaction count
  $('.reactions-container').each((i, val) => {
    const userReaction = $(val).find('.user-reaction').length
    const nbReactionLeft = $(val).find('.max-reaction').text()
    $(val)
      .find('.max-reaction')
      .text(nbReactionLeft - userReaction)
  })

  // Reaction Management Helper
  const reactionManagementHelper = {
    renderAjaxError(translation, jqXHR, textStatus, errorThrown) {
      const message = _t(translation, { error: `${textStatus} / ${errorThrown}${jqXHR.responseJSON.error != undefined ? `:${jqXHR.responseJSON.error}` : ''}` })
      if (typeof toastMessage == 'function') {
        toastMessage(message, 3000, 'alert alert-danger')
      } else {
        alert(message)
      }
      if (jqXHR.responseJSON.exceptionMessage != undefined) {
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
            errorThrown
          )
        }
      })
    },
    deleteTags(headElem) {
      const table = $(headElem).closest('table')
      if (table.length != 0) {
        $(table)
          .find('.btn-delete-reaction:not(.btn-delete-all)')
          .each(function() {
            reactionManagementHelper.deleteATag($(this))
          })
      }
    }
  }

  // handler reaction click
  $('.link-reaction').click(function(event) {
    event.preventDefault()
    event.stopPropagation()
    const extractData = (item) => {
      const nb = $(item).find('.reaction-numbers')
      return {
        url: $(item).attr('href'),
        data: $(item).data(),
        nb,
        nbInit: parseInt(nb.text())
      }
    }
    const { url, data, nb, nbInit } = extractData(this)
    const deleteUserReaction = async(url, data, nb, nbInit, link) => {
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
                .text()
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
              errorThrown
            )
            reject()
          }
        })
      })
      return await p.then((...args) => Promise.resolve(...args))
    }
    if (url !== '#') {
      if ($(this).hasClass('user-reaction')) {
        // on supprime la reaction
        if (typeof blockReactionRemove !== 'undefined' && blockReactionRemove) {
          if (blockReactionRemoveMessage) {
            if (typeof toastMessage == 'function') {
              toastMessage(
                blockReactionRemoveMessage,
                3000,
                'alert alert-warning'
              )
            } else {
              alert(blockReactionRemoveMessage)
            }
          }
          return false
        }
        const link = $(this)
        deleteUserReaction(url, data, nb, nbInit, link).catch((e) => {
          /* do nothing */
        })
        return false
      }
      // on ajoute la reaction si le max n'est pas dépassé
      const nbReactionLeft = parseFloat(
        $(this).parents('.reactions-container').find('.max-reaction').text()
      )
      if (
        url !== '#'
        && nbReactionLeft == 0
        && typeof blockReactionRemove !== 'undefined'
        && blockReactionRemove === true
      ) {
        const previous = $(this)
          .closest('.reactions-flex')
          .find('.user-reaction')
          .first()
        if (
          typeof previous === 'object'
          && 'length' in previous
          && previous.length > 0
        ) {
          const {
            url: previousUrl,
            data: previousData,
            nb: previousNb,
            nbInit: previousNbInit
          } = extractData(previous)
          if (previousUrl !== '#') {
            deleteUserReaction(
              previousUrl,
              previousData,
              previousNb,
              previousNbInit,
              $(previous)
            )
              .then(() => {
                $(this).click()
              })
              .catch((e) => {
                /* do nothing */
              })
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
              errorThrown
            )
          }
        })
      } else {
        const message = "Vous n'avez plus de choix possibles, vous pouvez retirer un choix existant pour changer"
        if (typeof toastMessage == 'function') {
          toastMessage(message, 3000, 'alert alert-warning')
        } else {
          alert(message)
        }
      }
      return false
    }
  })

  $('.btn-delete-reaction').on('click', function(e) {
    e.preventDefault()
    if (!$(this).hasClass('btn-delete-all')) {
      if (confirm(_t('REACTION_CONFIRM_DELETE'))) {
        reactionManagementHelper.deleteATag($(this))
      }
    } else if (confirm(_t('REACTION_CONFIRM_DELETE_ALL'))) {
      reactionManagementHelper.deleteTags($(this))
    }
  })
}(jQuery))

// fot comments table
$('#commentsTableDeleteModal.modal').on('shown.bs.modal', function(event) {
  multiDeleteService.initProgressBar($(this))
  $(this).find('.modal-body .multi-delete-results').html('')
  const deleteButton = $(this).find('button.start-btn-delete-comment')
  $(deleteButton).removeAttr('disabled')
  const button = $(event.relatedTarget) // Button that triggered the modal
  const name = $(button).data('name')
  const csrfToken = $(button)
    .closest('tr')
    .find(`td > label > input[data-itemId="${name}"][data-csrfToken]`)
    .first()
    .data('csrftoken')
  $(this).find('#commentToDelete').text(name)
  $(deleteButton).data('name', name)
  $(deleteButton).data('targetNode', button)
  $(deleteButton).data('modal', this)
  if (!$(deleteButton).hasClass('eventSet')) {
    $(deleteButton).addClass('eventSet')
    $(deleteButton).on('click', function() {
      $(this).attr('disabled', 'disabled')
      $(this).tooltip('hide')
      const name = $(this).data('name')
      const targetNode = $(this).data('targetNode')
      const modal = $(this).data('modal')

      $.ajax({
        method: 'post',
        url: wiki.url(`api/comments/${name}/delete`),
        timeout: 30000, // 30 seconds
        error(e) {
          multiDeleteService.addErrorMessage(
            $(modal),
            `${_t('COMMENT_NOT_DELETED', { comment: name })} : ${e.responseJSON && e.responseJSON.error ? e.responseJSON.error : ''
            }`
          )
        },
        success() {
          multiDeleteService.removeLine(
            $(targetNode).closest('.dataTables_wrapper').prop('id'),
            name
          )
          $(modal)
            .find('.modal-body .multi-delete-results')
            .first()
            .append($('<div>').text(_t('COMMENT_DELETED')))
        },
        complete() {
          multiDeleteService.updateProgressBar($(modal), ['test'], 0)
        }
      })
    })
  }
})

// function to check all checkbox in page.
function checkAll(state) {
  const checkboxes = document.querySelectorAll('input.selectpage')
  const newState = [true, 'true', 1, '1'].includes(state)
  checkboxes.forEach((checkbox) => {
    if (checkbox.type == 'checkbox') {
      checkbox.checked = newState
    }
  })
}

// tabs
// hack for next and previous buttons
$('.tab-content [data-toggle="tab"]').on('click', function() {
  const base = $(this).closest('.tab-content').prev()
  $(base).find('.active').removeClass('active')
  $(base)
    .find(`[href="${$(this).attr('href')}"]`)
    .parent()
    .addClass('active')
  $(base)
    .find(`a[href="${$(this).attr('href')}"]`)
    .tab('show')
  // manage iframe case
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

// a11y
$('#yw-a11y-jump-content').click(() => {
  setTimeout(() => {
    $('#yw-topnav').removeClass('nav-down').addClass('nav-up')
  }, 300)
})