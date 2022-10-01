
const pluginName = 'uploadbutton'
const defaults = { propertyName: 'value' }

function Plugin(element, options) {
  this.element = element
  this.options = $.extend({}, defaults, options)

  this.init()
}

Plugin.prototype.init = function() {
  // code goes here
  const $this = $(this.element)

  const textAreaId = $(this.element).data('textarea')
  const body = (textAreaId && textAreaId.length > 0) ? $(textAreaId) : $('#body')

  const tempTag = $(this.element).data('temptag')
  const uploadurl = wiki.url(`${wiki.pageTag}/ajaxupload`)
  const downloadlist = $this.find('.qq-upload-list')
  const UploadModal = $('#UploadModal')
  const UploadModalForm = $('#form-modal-upload')
  const filedownloadtext = UploadModal.find('.file-option .attach_link_text')
  const hiddenfilenameinput = UploadModal.find('.filename')
  const imageinput = UploadModal.find('.image-option')
  const fileinput = UploadModal.find('.file-option')
  const pdfinput = UploadModal.find('.pdf-option')

  function hideUploadModal() {
    // delete element in upload list
    downloadlist.empty()

    // reset form
    UploadModalForm[0].reset()

    // close accordion
    UploadModalForm.find('.accordion-trigger.image-option').addClass('collapsed')
    $('#avanced-settings').collapse('hide')

    // hide the modal and clean title
    UploadModal.modal('hide').find('.modal-title .qq-upload-file, .modal-title .qq-upload-size').remove()
  }

  function getParameterByName(querystring, name) {
    name = name.replace(/[\[]/, '\\\[').replace(/[\]]/, '\\\]')
    const regex = new RegExp(`[\\?&]${name}=([^&#]*)`)
    const results = regex.exec(`?${querystring}`)
    return results == null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '))
  }

  // Handle of the modal cancel button, and close button
  UploadModal.find('.btn-cancel-upload, .close').on('click', () => {
    hideUploadModal()
    setTimeout(() => {
      body.trigger('focus')
    }, 10)
    return false
  })

  // Handle of the modal insert button
  UploadModal.find('.btn-insert-upload').on('click', function(e) {
    if ($this.data('textarea') !== $(this).data('textarea')) {
      return false
    }
    const formvals = UploadModalForm.find(':input').serialize()
    let desctxt = getParameterByName(formvals, 'attach_alt')
    if (typeof desctxt == 'undefined' || desctxt == '') {
      desctxt = getParameterByName(formvals, 'attach_link_text')
    }
    let actionattach = `{{attach file="${tempTag ? `${tempTag}/` : ''}${getParameterByName(formvals, 'filename')}" desc="${desctxt}"`

    const displaypdf = getParameterByName(formvals, 'attach_action_display_pdf')
    if (typeof displaypdf != 'undefined' && displaypdf == '1') {
      actionattach += ' displaypdf="1"'
    }

    const imagesize = getParameterByName(formvals, 'attach_imagesize')
    if (typeof imagesize != 'undefined' && imagesize != '') {
      actionattach += ` size="${imagesize}"`
    }

    const imagealign = getParameterByName(formvals, 'attach_align')
    if (typeof imagealign != 'undefined') {
      actionattach += ` class="${imagealign}`
      UploadModalForm.find('input[name="attach_css_class"]:checked').each(function() {
        actionattach += ` ${$(this).val()}`
      })
      actionattach += '"'
    }

    const imagelink = getParameterByName(formvals, 'attach_link')
    if (typeof imagelink != 'undefined' && imagelink !== '') {
      actionattach += ` link="${imagelink}"`
    }

    const imagecaption = getParameterByName(formvals, 'attach_caption')
    if (typeof imagecaption != 'undefined' && imagecaption !== '') {
      actionattach += ` caption="${imagecaption}"`
    }

    const nofullimagelink = getParameterByName(formvals, 'attach_nofullimagelink')
    if (typeof nofullimagelink != 'undefined' && nofullimagelink == '1') {
      actionattach += ' nofullimagelink="1"'
    }

    actionattach += '}}'

    setTimeout(() => {
      // on ajoute le code de l'action attach au mode édition
      body.focus()
      body.surroundSelectedText(actionattach, '')
    }, 10)

    hideUploadModal()

    return false
  })

  const uploader = new qq.FileUploader({
    element: this.element,
    action: uploadurl,
    debug: false,
    params: { ...{}, ...(tempTag ? { tempTag } : {}) },

    onSubmit(id, fileName) {
      // upload modal is cleaned and showed
      fileinput.hide()
      pdfinput.hide()
      imageinput.hide()
      UploadModal.find('.btn-insert-upload').data('textarea', $(this.element).data('textarea')) // to set which textarea is asking a new file
      UploadModal.modal('show')
    },

    onComplete(id, fileName, responseJSON) {
      const fileuploaded = downloadlist.find('.qq-upload-success .qq-upload-file')
      const filesize = fileuploaded.siblings('.qq-upload-size')
      UploadModal.find('.modal-title').append(fileuploaded).append(filesize)

      // If it's an image
      if (imagesExtensions.indexOf(responseJSON.extension) > -1) {
        imageinput.show()
        hiddenfilenameinput.val(responseJSON.simplefilename)
        UploadModal.find('.attach_alt').val(`image ${responseJSON.simplefilename} (${filesize.text()})`)
      } else if (responseJSON.extension === 'pdf') {
        fileinput.show()
        pdfinput.show()
        hiddenfilenameinput.val(responseJSON.simplefilename)
        filedownloadtext.val(`${responseJSON.simplefilename} (${filesize.text()})`)
      } else {
        fileinput.show()
        hiddenfilenameinput.val(responseJSON.simplefilename)
        filedownloadtext.val(`${responseJSON.simplefilename} (${filesize.text()})`)
      }
      // overlayform.appendTo(fileuploaded.parent('.qq-upload-success'));
    },

    // if download is canceled while transfering
    onCancel(id, fileName) {
      hideUploadModal()
    }
  })

  // we move the uploaded file list in the modal
  UploadModal.find('.modal-body').prepend(downloadlist)
}

$.fn[pluginName] = function(options) {
  return this.each(function() {
    if (!$.data(this, `plugin_${pluginName}`)) {
      $.data(this, `plugin_${pluginName}`, new Plugin(this, options))
    }
  })
}

$(document).ready(() => {
  // Initialize the button for upload in Aceditor
  $('.attach-file-uploader').each(function() {
    $(this).uploadbutton()
  })
})
