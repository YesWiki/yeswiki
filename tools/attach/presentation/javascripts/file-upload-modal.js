export default class {
  $btnContainer
  onComplete // callback to be called

  get $modal() { return $('#UploadModal') }
  get $form() { return this.$modal.find('form') }
  get tempTag() { return this.$btnContainer.data('temptag') }
  get $uploadUrl() { return wiki.url(`${wiki.pageTag}/ajaxupload`) }
  get $downloadList() { return this.$modal.find('.qq-upload-list') }
  get $fileDownloadText() { return this.$modal.find('.file-option .attach_link_text') }
  get $hiddenFilenameInput() { return this.$modal.find('.filename') }
  get $imagesOptions() { return this.$modal.find('.image-option') }
  get $fileOptions() { return this.$modal.find('.file-option') }
  get $pdfOptions() { return this.$modal.find('.pdf-option') }

  constructor() {
    // Cancel Button
    this.$modal.find('.btn-cancel-upload, .close').on('click', () => {
      this.hideUploadModal()
    })
  }

  initButton($btnContainer, onComplete) {
    this.$btnContainer = $btnContainer
    this.onComplete = onComplete

    const uploader = new qq.FileUploader({
      element: $btnContainer[0],
      action: this.$uploadUrl,
      params: this.tempTag ? { tempTag: this.tempTag } : {},
      debug: false,
      fileTemplate: $btnContainer.find('.sample-upload-list').html(),
      onSubmit: () => {
        this.resetModal()
        this.$modal.modal('show')

        // Insert Button
        this.$modal.find('.btn-insert-upload').off('click').on('click', () => {
          this.onComplete(this.buildCode())
          this.hideUploadModal()
        })
      },
      onComplete: (id, fileName, responseJSON) => {
        const fileuploaded = this.$downloadList.find('.qq-upload-success .qq-upload-file')
        const filesize = fileuploaded.siblings('.qq-upload-size')
        this.$modal.find('.modal-title').append(fileuploaded).append(filesize)

        // If it's an image
        if (imagesExtensions.includes(responseJSON.extension)) {
          this.$imagesOptions.show()
          this.$hiddenFilenameInput.val(responseJSON.simplefilename)
          this.$modal.find('.attach_caption').val(`image ${responseJSON.simplefilename} (${filesize.text()})`)
        } else if (responseJSON.extension === 'pdf') {
          this.$fileOptions.show()
          this.$pdfOptions.show()
          this.$hiddenFilenameInput.val(responseJSON.simplefilename)
          this.$fileDownloadText.val(`${responseJSON.simplefilename} (${filesize.text()})`)
        } else {
          this.$fileOptions.show()
          this.$hiddenFilenameInput.val(responseJSON.simplefilename)
          this.$fileDownloadText.val(`${responseJSON.simplefilename} (${filesize.text()})`)
        }
      },
      onCancel: () => {
        this.hideUploadModal()
      }
    })

    // We move the section where qq will display the upload progress into the modal
    this.$modal.find('.modal-body').prepend($btnContainer.find('.qq-upload-list'))
  }

  // Private Methods

  resetModal() {
    this.$fileOptions.hide()
    this.$pdfOptions.hide()
    this.$imagesOptions.hide()
    // delete element in upload list
    this.$downloadList.empty()
    // reset form
    this.$form[0].reset()
    // close accordion
    this.$form.find('.accordion-trigger.image-option').addClass('collapsed')
    $('#avanced-settings').collapse('hide')
    // Clean title
    this.$modal.find('.modal-title .qq-upload-file, .modal-title .qq-upload-size').remove()
  }

  hideUploadModal() {
    this.$modal.modal('hide')
  }

  buildCode() {
    const formvals = this.$form.find(':input').serialize()
    let desctxt = this.getParameterByName(formvals, 'attach_alt')
    if (typeof desctxt == 'undefined' || desctxt == '') {
      desctxt = this.getParameterByName(formvals, 'attach_link_text')
    }

    const file = `${this.tempTag ? `${this.tempTag}/` : ''}${this.getParameterByName(formvals, 'filename')}`
    let result = `{{attach file="${file}" desc="${desctxt}"`

    const displaypdf = this.getParameterByName(formvals, 'attach_action_display_pdf')
    if (typeof displaypdf != 'undefined' && displaypdf == '1') {
      result += ' displaypdf="1"'
    }

    const imagesize = this.getParameterByName(formvals, 'attach_imagesize')
    if (typeof imagesize != 'undefined' && imagesize != '') {
      result += ` size="${imagesize}"`
    }

    const imagealign = this.getParameterByName(formvals, 'attach_align')
    if (typeof imagealign != 'undefined') {
      result += ` class="${imagealign}`
      this.$form.find('input[name="attach_css_class"]:checked').each(function() {
        result += ` ${$(this).val()}`
      })
      result += '"'
    }

    const imagelink = this.getParameterByName(formvals, 'attach_link')
    if (typeof imagelink != 'undefined' && imagelink !== '') {
      result += ` link="${imagelink}"`
    }

    const imagecaption = this.getParameterByName(formvals, 'attach_caption')
    if (typeof imagecaption != 'undefined' && imagecaption !== '') {
      result += ` caption="${imagecaption}"`
    }

    const nofullimagelink = this.getParameterByName(formvals, 'attach_nofullimagelink')
    if (typeof nofullimagelink != 'undefined' && nofullimagelink == '1') {
      result += ' nofullimagelink="1"'
    }

    result += '}}'
    return result
  }

  getParameterByName(querystring, name) {
    name = name.replace(/[\[]/, '\\\[').replace(/[\]]/, '\\\]')
    const regex = new RegExp(`[\\?&]${name}=([^&#]*)`)
    const results = regex.exec(`?${querystring}`)
    return results == null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '))
  }
}
