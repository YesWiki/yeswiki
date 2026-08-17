// yeswiki-base.js — core page behavior (ticket 16: vanilla JS, no jQuery/Bootstrap).
// Modal/dropdown/collapse/tab/tooltip primitives live in yw-core.js and yw-core.css;
// this file keeps the page-level glue: comments, reactions, search widget, misc UI.

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
  document.body.insertAdjacentElement('afterend', toastEl)
  const topnav = document.getElementById('yw-topnav')
  if (topnav) {
    const styles = window.getComputedStyle(topnav)
    const outerHeight =
      topnav.offsetHeight +
      parseFloat(styles.marginTop) +
      parseFloat(styles.marginBottom)
    toastEl.style.top = `${outerHeight + 20}px`
  }
  toastEl.style.opacity = 1
  setTimeout(() => {
    toastEl.style.opacity = 0
  }, duration)
  setTimeout(() => {
    toastEl.remove()
  }, duration + 300)
  toastEl.classList.add('visible')
}

// function to check all checkbox in page (called from template onclick attributes).
// eslint-disable-next-line no-unused-vars
function checkAll(state) {
  const checkboxes = document.querySelectorAll('input.selectpage')
  const newState = [true, 'true', 1, '1'].includes(state)
  checkboxes.forEach((checkboxParam) => {
    const checkbox = checkboxParam
    if (checkbox.type === 'checkbox') {
      checkbox.checked = newState
    }
  })
}

;(function () {
  // Every ancestor of `el` (closest first) matching `selector`, like jQuery .parents()
  function ancestorsOf(el, selector) {
    const found = []
    let current = el.parentElement ? el.parentElement.closest(selector) : null
    while (current) {
      found.push(current)
      current = current.parentElement
        ? current.parentElement.closest(selector)
        : null
    }
    return found
  }

  function requestFailureMessage(payload) {
    return payload && payload.error ? payload.error : _t('ERROR')
  }

  // POSTs a form (or a plain key/value object) urlencoded, resolving with the
  // parsed JSON body and rejecting with the parsed JSON error body — the same
  // contract the previous jQuery $.ajax(dataType: 'json') calls relied on
  function postForm(url, data) {
    const body =
      data instanceof HTMLFormElement
        ? new URLSearchParams(new FormData(data))
        : new URLSearchParams(data)
    // X-Requested-With is what lets the server tell this apart from a plain form
    // submission of the same form to the same url: routes that have to serve both answer
    // JSON here and redirect-with-a-flash there (ticket 35). fetch sends no such header
    // of its own, and `Accept` is `*/*` either way, so it has to be explicit.
    return fetch(url, {
      method: 'POST',
      body,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).then((response) =>
      response
        .json()
        .then((payload) => (response.ok ? payload : Promise.reject(payload))),
    )
  }

  // ---- password fields: show/hide toggle ----
  document.querySelectorAll('input[type=password]').forEach((field) => {
    const eye = document.createElement('div')
    eye.className = 'far fa-eye'
    eye.setAttribute('title', _t('SHOW_PASSWORD'))
    eye.style.cssText =
      'position: absolute; right: 0%; top: 50%;' +
      ' transform: translate(0px, -50%); padding-right: 1em; font-size: 1em;'
    eye.addEventListener('click', () => {
      if (field.getAttribute('type') === 'password') {
        field.setAttribute('type', 'text')
        eye.classList.replace('fa-eye', 'fa-eye-slash')
        eye.setAttribute('title', _t('HIDE_PASSWORD'))
      } else {
        field.setAttribute('type', 'password')
        eye.classList.replace('fa-eye-slash', 'fa-eye')
        eye.setAttribute('title', _t('SHOW_PASSWORD'))
      }
    })
    field.insertAdjacentElement('afterend', eye)
  })

  // ---- active classes for menus ----
  document.querySelectorAll('a.active-link').forEach((link) => {
    if (link.parentElement) link.parentElement.classList.add('active-list')
    ancestorsOf(link, 'ul').forEach((list) => {
      const previous = list.previousElementSibling
      if (previous && previous.matches('a')) {
        previous.classList.add('active-parent-link')
        if (previous.parentElement)
          previous.parentElement.classList.add('active-list')
      }
    })
  })

  // Modal-opening ("a.modalbox"/"a.modal"/".modalbox a") links are now handled by
  // javascripts/yw-core.js's openRemoteModal, matching this exact same markup contract
  // (href/data-size/data-iframe/data-header/title) so no template needed to change.

  document.addEventListener('click', (e) => {
    const newTabLink = e.target.closest('a.newtab')
    if (newTabLink) {
      e.preventDefault()
      window.open(newTabLink.getAttribute('href'), '_blank')
    }
  })

  // on enleve la fonction doubleclic dans des cas ou cela pourrait etre indesirable
  document.addEventListener(
    'dblclick',
    (e) => {
      if (
        e.target.closest(
          '.no-dblclick, form, .page a, button, .yw-dropdown__menu',
        )
      ) {
        e.preventDefault()
        e.stopPropagation()
      }
    },
    true,
  )

  // deplacer les fenetres modales en bas de body pour eviter que des styles s'appliquent
  document.querySelectorAll('.modal, .yw-modal').forEach((modal) => {
    document.body.appendChild(modal)
  })

  // Remove hidden div by ACL
  document
    .querySelectorAll('.remove-this-div-on-page-load')
    .forEach((el) => el.remove())

  // Tooltips ([data-toggle="tooltip"]/[data-tooltip="tooltip"] + title) are pure
  // CSS now (yw-core.css) — no init call needed.

  // ---- moteur de recherche utilisé dans un template ----
  document.querySelectorAll('a[href="#search"]').forEach((trigger) => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault()
      const search = trigger.parentElement.querySelector('#search')
      if (search) {
        search.classList.add('open')
        const query = search.querySelector('.search-query')
        if (query) query.focus()
      }
    })
  })

  const searchWidget = document.getElementById('search')
  if (searchWidget) {
    const closeSearchIfAsked = (e) => {
      if (
        e.target === searchWidget ||
        e.target.closest('.close-search') ||
        e.key === 'Escape'
      ) {
        searchWidget.classList.remove('open')
      }
    }
    searchWidget.addEventListener('click', closeSearchIfAsked)
    searchWidget.addEventListener('keyup', closeSearchIfAsked)
  }

  // Tab switching + history (formerly jQuery historyTabs + the next/previous
  // buttons hack) is handled by yw-core.js's tabs primitive.

  // ---- double clic on the navbar: modal listing its editable pages ----
  document.querySelectorAll('.navbar, .yw-topnav').forEach((navbar) => {
    navbar.addEventListener('dblclick', (e) => {
      e.preventDefault()
      e.stopPropagation()
      const modal = document.createElement('div')
      modal.className = 'yw-modal'
      modal.id = 'YesWikiModal'
      modal.innerHTML = `
        <div class="yw-modal__dialog">
          <div class="yw-modal__content">
            <div class="yw-modal__header">
              <h3 class="yw-modal__title"></h3>
              <button type="button" class="yw-close" data-yw-dismiss="modal"
                aria-label="close">&times;</button>
            </div>
            <div class="yw-modal__body"></div>
          </div>
        </div>`
      modal.querySelector('.yw-modal__title').textContent = _t(
        'NAVBAR_EDIT_MESSAGE',
      )
      const body = modal.querySelector('.yw-modal__body')

      navbar.querySelectorAll('.include').forEach((include) => {
        const href = (include.getAttribute('ondblclick') || '')
          .replace("document.location='", '')
          .replace("';", '')
        if (!href) return
        const pagewiki = href
          .replace('/edit', '')
          .replace('http://yeswiki.dev/wakka.php?wiki=', '')
        const link = document.createElement('a')
        link.href = href
        link.className = 'yw-btn yw-btn--block'
        link.innerHTML =
          '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#pencil"/></svg> '
        link.appendChild(
          document.createTextNode(`${_t('YESWIKIMODAL_EDIT_MSG')} ${pagewiki}`),
        )
        body.appendChild(link)
      })

      const cancel = document.createElement('a')
      cancel.href = '#'
      cancel.setAttribute('data-yw-dismiss', 'modal')
      cancel.className = 'yw-btn yw-btn--warning yw-btn--sm yw-btn--block'
      cancel.textContent = _t('EDIT_OUPS_MSG')
      body.appendChild(cancel)

      document.body.appendChild(modal)
      modal.classList.add('yw-modal--open')
      // single-use modal: remove it from the DOM as soon as it gets closed
      const cleanup = new MutationObserver(() => {
        if (!modal.classList.contains('yw-modal--open')) {
          cleanup.disconnect()
          modal.remove()
        }
      })
      cleanup.observe(modal, { attributes: true, attributeFilter: ['class'] })
    })
  })

  // ---- AUTO RESIZE IFRAME ----
  // the vendored iframe-resizer build exposes a plain window.iFrameResize()
  // (its jQuery plugin registration is optional) — load it on demand
  if (document.querySelector('iframe.auto-resize')) {
    const script = document.createElement('script')
    script.src = 'javascripts/vendor/iframe-resizer/iframeResizer.min.js'
    script.onload = () => {
      window.iFrameResize({}, 'iframe.auto-resize')
    }
    script.onerror = () => {
      console.log(
        'Error getting script javascripts/vendor/iframe-resizer/iframeResizer.min.js',
      )
    }
    document.body.appendChild(script)
  }

  // ouvrir les liens dans une nouvelle fenetre
  const openInNewWindow = () => {
    document.querySelectorAll('.new-window:not([target])').forEach((link) => {
      link.setAttribute('target', '_blank')
    })
  }
  openInNewWindow()
  document.addEventListener('yw-modal-open', openInNewWindow)

  // ---- acl switch ----
  const aclSwitch = document.getElementById('acl-switch-mode')
  if (aclSwitch) {
    const clearValue = (el) => {
      if (el instanceof HTMLSelectElement) {
        el.selectedIndex = -1
      } else if ('value' in el) {
        el.value = ''
      }
    }
    const applyAclMode = () => {
      if (aclSwitch.checked) {
        // show advanced
        document.querySelectorAll('.acl-simple').forEach((el) => {
          el.style.display = 'none'
          clearValue(el)
        })
        document.querySelectorAll('.acl-advanced').forEach((el) => {
          el.style.display = ''
        })
      } else {
        document
          .querySelectorAll('.acl-single-container label')
          .forEach((label) => {
            const select = document.querySelector(
              `select[name=${label.dataset.input}]`,
            )
            if (select) label.insertAdjacentElement('afterend', select)
          })
        document.querySelectorAll('.acl-simple').forEach((el) => {
          el.style.display = ''
        })
        document.querySelectorAll('.acl-advanced').forEach((el) => {
          el.style.display = 'none'
          clearValue(el)
        })
      }
    }
    aclSwitch.addEventListener('change', applyAclMode)
    applyAclMode()
  }

  // Tables: sort/search/pagination is yw-datatable.js's job now, self-initialized
  // on any table[data-yw-datatable] — no per-page init call needed here.

  /** comments */

  /** The comment box, whichever editor is drawing it. */
  function commentEditor() {
    return window.ywEditors?.body
  }

  function resetCommentForm(form) {
    if (!form) return
    form.setAttribute('id', 'post-comment')
    form.setAttribute('class', '')
    form.setAttribute(
      'action',
      form
        .getAttribute('action')
        .replace(/api\/comments(\/.*)/gm, 'api/comments'),
    )
    const comments = document.querySelector('.yeswiki-page-comments')
    if (comments && comments.parentElement) {
      comments.parentElement.appendChild(form)
    }
    form
      .querySelectorAll('label')
      .forEach((label) => label.classList.remove('hide'))
    document
      .querySelectorAll('.btn-cancel-comment')
      .forEach((btn) => btn.remove())
    const postButton = form.querySelector('.btn-post-comment')
    if (postButton) postButton.textContent = _t('SAVE')
    // through the handle every editor publishes, not the ACeditor's own instance: the
    // comment box is `{{aceditor name="body"}}`, and which editor that renders is the
    // reader's choice (editor-handles.js)
    commentEditor()?.setValue('')
  }

  function appendCancelButton(form) {
    const cancel = document.createElement('button')
    cancel.className = 'btn-cancel-comment yw-btn yw-btn--sm'
    cancel.textContent = _t('CANCEL')
    form.appendChild(cancel)
  }

  // ajax post comment
  document.addEventListener('click', (e) => {
    const button = e.target.closest('.btn-post-comment')
    if (
      !button ||
      !button.closest('.yeswiki-page-comments, #post-comment, form')
    )
      return
    e.preventDefault()
    const form = button.closest('form')
    if (!form) return
    postForm(form.getAttribute('action'), form)
      .then((payload) => {
        form.reset()
        commentEditor()?.setValue('')
        toastMessage(payload.success, 3000, 'alert alert-success')
        ancestorsOf(form, '.yw-comment').forEach((comment) => {
          comment.querySelectorAll('.comment-links').forEach((links) => {
            links.classList.remove('hide')
          })
        })
        // we place the new comment in different places if its an answer,
        // a modification or a new comment
        if (form.classList.contains('comment-modify')) {
          const holder = document.createElement('div')
          holder.innerHTML = payload.html
          const fresh = holder.querySelector('.yw-comment')
          const current = form.closest('.yw-comment')
          if (fresh && current) current.innerHTML = fresh.innerHTML
          resetCommentForm(document.getElementById('post-comment') || form)
        } else if (form.parentElement.classList.contains('comment-reponses')) {
          form.parentElement.insertAdjacentHTML('beforeend', payload.html)
          resetCommentForm(form)
        } else {
          const comments = document.querySelector('.yeswiki-page-comments')
          if (comments) comments.insertAdjacentHTML('beforeend', payload.html)
        }
      })
      .catch((payload) => {
        toastMessage(requestFailureMessage(payload), 3000, 'alert alert-danger')
      })
  })

  // when a comment-form is already opened somewhere, close it and restore that comment
  function closeOpenedCommentForm() {
    const opened = document.querySelector('.temporary-form')
    if (!opened) return
    ancestorsOf(opened, '.yw-comment').forEach((comment) => {
      const html = comment.querySelector('.comment-html')
      if (html) html.classList.remove('hide')
      const links = comment.querySelector('.comment-links')
      if (links) links.classList.remove('hide')
    })
    resetCommentForm(opened)
  }

  // ajax answer comment
  document.addEventListener('click', (e) => {
    const button = e.target.closest('.btn-answer-comment')
    if (!button) return
    e.preventDefault()
    const com = button.parentElement.parentElement
    closeOpenedCommentForm()

    // move the comment form here and change some options
    const formAnswer = com.querySelector('.comment-reponses')
    if (!formAnswer) return
    const postComment = document.getElementById('post-comment')
    if (postComment) formAnswer.appendChild(postComment)
    const form = formAnswer.querySelector('form')
    if (!form) return
    form.setAttribute('id', `form-comment-${com.dataset.tag}`)
    form.classList.remove('hide')
    form.classList.add('temporary-form')
    formAnswer.querySelectorAll('[name="pagetag"]').forEach((input) => {
      input.value = com.dataset.tag
    })
    appendCancelButton(form)
    com
      .querySelectorAll('.comment-links')
      .forEach((links) => links.classList.add('hide'))
    com
      .querySelectorAll('label')
      .forEach((label) => label.classList.add('hide'))
  })

  // ajax edit comment
  document.addEventListener('click', (e) => {
    const button = e.target.closest('.btn-edit-comment')
    if (!button) return
    e.preventDefault()
    const com = button.parentElement.parentElement

    // hide comment and comment links while editor is open
    const commentHtml = com.querySelector('.comment-html')
    if (commentHtml) commentHtml.classList.add('hide')
    const commentLinks = com.querySelector('.comment-links')
    if (commentLinks) commentLinks.classList.add('hide')

    closeOpenedCommentForm()

    // move the comment form here and change some options
    const formcom = com.querySelector('.form-comment')
    if (!formcom) return
    const postComment = document.getElementById('post-comment')
    if (postComment) formcom.appendChild(postComment)
    const form = formcom.querySelector('form')
    if (!form) return
    form.setAttribute('id', `form-comment-${com.dataset.tag}`)
    form.setAttribute(
      'action',
      `${form.getAttribute('action')}/${com.dataset.tag}`,
    )
    form.classList.remove('hide')
    form.classList.add('temporary-form')
    form.classList.add('comment-modify')
    formcom
      .querySelectorAll('label')
      .forEach((label) => label.classList.add('hide'))
    const commentBody = com.querySelector('.comment-body')
    commentEditor()?.setValue(commentBody ? commentBody.value : '')
    formcom.querySelectorAll('[name="pagetag"]').forEach((input) => {
      input.value = com.dataset.commenton
    })
    const postButton = formcom.querySelector('.btn-post-comment')
    if (postButton) postButton.textContent = _t('MODIFY')
    appendCancelButton(form)
    ancestorsOf(com, '.yw-comment').forEach((comment) => {
      comment.querySelectorAll('.comment-links').forEach((links) => {
        links.classList.add('hide')
      })
    })
  })

  // cancel comment edit
  document.addEventListener('click', (e) => {
    const button = e.target.closest('.btn-cancel-comment')
    if (!button) return
    e.preventDefault()
    const com = button.parentElement.parentElement.parentElement
    // restore html comment and links
    const commentHtml = com.querySelector('.comment-html')
    if (commentHtml) commentHtml.classList.remove('hide')
    const commentLinks = com.querySelector('.comment-links')
    if (commentLinks) commentLinks.classList.remove('hide')
    ancestorsOf(com, '.yw-comment').forEach((comment) => {
      comment.querySelectorAll('.comment-links').forEach((links) => {
        links.classList.remove('hide')
      })
    })
    resetCommentForm(document.getElementById(`form-comment-${com.dataset.tag}`))
  })

  // ajax delete comment
  document.addEventListener('click', (e) => {
    const link = e.target.closest('.btn-delete-comment')
    if (!link) return
    e.preventDefault()
    if (!window.confirm(_t('DELETE_COMMENT_AND_ANSWERS'))) return
    fetch(link.getAttribute('href'), { method: 'POST' })
      .then((response) =>
        response
          .json()
          .then((payload) => (response.ok ? payload : Promise.reject(payload))),
      )
      .then((payload) => {
        const comment = link.closest('.yw-comment')
        if (comment) comment.remove()
        toastMessage(payload.success, 3000, 'alert alert-success')
      })
      .catch((payload) => {
        toastMessage(requestFailureMessage(payload), 3000, 'alert alert-danger')
      })
  })

  /** reactions */

  // init user reaction count
  document.querySelectorAll('.reactions-container').forEach((container) => {
    const userReaction = container.querySelectorAll('.user-reaction').length
    const maxReaction = container.querySelector('.max-reaction')
    if (maxReaction) {
      maxReaction.textContent -= userReaction
    }
  })

  // Reaction Management Helper
  const reactionManagementHelper = {
    renderAjaxError(translation, payload) {
      const details = payload && payload.error ? payload.error : ''
      const message = _t(translation, { error: details })
      toastMessage(message, 3000, 'alert alert-danger')
      if (payload && payload.exceptionMessage !== undefined) {
        console.warn(payload.exceptionMessage)
      }
    },
    deleteATag(elem) {
      fetch(elem.getAttribute('href'))
        .then((response) =>
          response.ok
            ? response
            : response.json().then((payload) => Promise.reject(payload)),
        )
        .then(() => {
          const row = elem.closest('tr')
          if (row) row.remove()
        })
        .catch((payload) => {
          reactionManagementHelper.renderAjaxError(
            'REACTION_NOT_POSSIBLE_TO_DELETE_REACTION',
            payload,
          )
        })
    },
    deleteTags(headElem) {
      const table = headElem.closest('table')
      if (table) {
        table
          .querySelectorAll('.btn-delete-reaction:not(.btn-delete-all)')
          .forEach((el) => {
            reactionManagementHelper.deleteATag(el)
          })
      }
    },
  }

  function maxReactionOf(link) {
    const container = link.closest('.reactions-container')
    return container ? container.querySelector('.max-reaction') : null
  }

  const extractReactionData = (item) => {
    const nb = item.querySelector('.reaction-numbers')
    return {
      url: item.getAttribute('href'),
      data: { ...item.dataset },
      nb,
      nbInit: parseInt(nb.textContent, 10),
    }
  }

  const deleteUserReaction = (url, data, nb, nbInit, link) => {
    let currentReactionId = data.reactionid
    if ('oldId' in data && (data.oldId === true || data.oldId === 'true')) {
      currentReactionId = 'reactionField'
    }
    const deleteUrl =
      `${url}/${currentReactionId}/${data.id}/${data.pagetag}/` +
      `${data.username}/delete`
    return fetch(deleteUrl, { method: 'POST' })
      .then((response) =>
        response.ok
          ? response
          : response.json().then((payload) => Promise.reject(payload)),
      )
      .then(() => {
        nb.textContent = nbInit - 1
        link.classList.remove('user-reaction')
        const maxReaction = maxReactionOf(link)
        if (maxReaction) {
          maxReaction.textContent = parseFloat(maxReaction.textContent) + 1
        }
      })
      .catch((payload) => {
        reactionManagementHelper.renderAjaxError(
          'REACTION_NOT_POSSIBLE_TO_DELETE_REACTION',
          payload,
        )
        return Promise.reject(payload)
      })
  }

  // handler reaction click
  document.addEventListener('click', (e) => {
    const link = e.target.closest('.link-reaction')
    if (!link) return
    e.preventDefault()
    e.stopPropagation()
    const { url, data, nb, nbInit } = extractReactionData(link)
    if (url === '#') return

    if (link.classList.contains('user-reaction')) {
      // on supprime la reaction
      if (typeof blockReactionRemove !== 'undefined' && blockReactionRemove) {
        if (blockReactionRemoveMessage) {
          toastMessage(blockReactionRemoveMessage, 3000, 'alert alert-warning')
        }
        return
      }
      deleteUserReaction(url, data, nb, nbInit, link).catch(() => {
        /* do nothing */
      })
      return
    }
    // on ajoute la reaction si le max n'est pas dépassé
    const maxReaction = maxReactionOf(link)
    const nbReactionLeft = maxReaction ? parseFloat(maxReaction.textContent) : 0
    if (
      nbReactionLeft === 0 &&
      typeof blockReactionRemove !== 'undefined' &&
      blockReactionRemove === true
    ) {
      const flex = link.closest('.reactions-flex')
      const previous = flex ? flex.querySelector('.user-reaction') : null
      if (previous) {
        const {
          url: previousUrl,
          data: previousData,
          nb: previousNb,
          nbInit: previousNbInit,
        } = extractReactionData(previous)
        if (previousUrl !== '#') {
          deleteUserReaction(
            previousUrl,
            previousData,
            previousNb,
            previousNbInit,
            previous,
          )
            .then(() => {
              link.click()
            })
            .catch(() => {
              /* do nothing */
            })
          return
        }
      }
    }
    if (nbReactionLeft > 0) {
      postForm(url, data)
        .then(() => {
          const ownNumbers = link.querySelector('.reaction-numbers')
          if (ownNumbers) ownNumbers.textContent = nbReactionLeft - 1
          nb.textContent = nbInit + 1
          link.classList.add('user-reaction')
          if (maxReaction) maxReaction.textContent = nbReactionLeft - 1
        })
        .catch((payload) => {
          reactionManagementHelper.renderAjaxError(
            'REACTION_NOT_POSSIBLE_TO_ADD_REACTION',
            payload,
          )
        })
    } else {
      const message =
        "Vous n'avez plus de choix possibles," +
        ' vous pouvez retirer un choix existant pour changer'
      toastMessage(message, 3000, 'alert alert-warning')
    }
  })

  document.addEventListener('click', (e) => {
    const button = e.target.closest('.btn-delete-reaction')
    if (!button) return
    e.preventDefault()
    if (!button.classList.contains('btn-delete-all')) {
      if (window.confirm(_t('REACTION_CONFIRM_DELETE'))) {
        reactionManagementHelper.deleteATag(button)
      }
    } else if (window.confirm(_t('REACTION_CONFIRM_DELETE_ALL'))) {
      reactionManagementHelper.deleteTags(button)
    }
  })

  // ---- comments table: multi-delete modal ----
  const commentsDeleteModal = document.getElementById(
    'commentsTableDeleteModal',
  )
  if (commentsDeleteModal) {
    commentsDeleteModal.addEventListener('yw-modal-shown', (event) => {
      const modal = commentsDeleteModal
      multiDeleteService.initProgressBar(modal)
      modal
        .querySelectorAll('.yw-modal__body .multi-delete-results')
        .forEach((results) => {
          results.innerHTML = ''
        })
      const deleteButton = modal.querySelector(
        'button.start-btn-delete-comment',
      )
      if (!deleteButton) return
      deleteButton.removeAttribute('disabled')
      const opener = event.detail.relatedTarget // Button that triggered the modal
      const name = opener ? opener.dataset.name : ''
      const nameHolder = modal.querySelector('#commentToDelete')
      if (nameHolder) nameHolder.textContent = name
      deleteButton.dataset.name = name
      deleteButton.commentTargetNode = opener
      if (!deleteButton.classList.contains('eventSet')) {
        deleteButton.classList.add('eventSet')
        deleteButton.addEventListener('click', () => {
          deleteButton.setAttribute('disabled', 'disabled')
          const commentName = deleteButton.dataset.name
          const targetNode = deleteButton.commentTargetNode

          fetch(wiki.url(`api/comments/${commentName}/delete`), {
            method: 'POST',
          })
            .then((response) =>
              response.ok
                ? response
                : response.json().then((payload) => Promise.reject(payload)),
            )
            .then(() => {
              if (targetNode) {
                const row = targetNode.closest('tr')
                if (row) row.remove()
              }
              const results = modal.querySelector(
                '.yw-modal__body .multi-delete-results',
              )
              if (results) {
                const done = document.createElement('div')
                done.textContent = _t('COMMENT_DELETED')
                results.appendChild(done)
              }
            })
            .catch((payload) => {
              multiDeleteService.addErrorMessage(
                modal,
                `${_t('COMMENT_NOT_DELETED', { comment: commentName })} : ${
                  payload && payload.error ? payload.error : ''
                }`,
              )
            })
            .finally(() => {
              multiDeleteService.updateProgressBar(modal, ['test'], 0)
            })
        })
      }
    })
  }

  // a11y
  const jumpToContent = document.getElementById('yw-a11y-jump-content')
  if (jumpToContent) {
    jumpToContent.addEventListener('click', () => {
      setTimeout(() => {
        const topnav = document.getElementById('yw-topnav')
        if (topnav) {
          topnav.classList.remove('nav-down')
          topnav.classList.add('nav-up')
        }
        document.body.classList.remove('nav-down')
        document.body.classList.add('nav-up')
      }, 300)
    })
  }
})()
