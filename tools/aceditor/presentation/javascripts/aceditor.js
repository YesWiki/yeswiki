import * as aceModule from '../../../../javascripts/vendor/ace/ace.js'
// Loads html rules cause it's used inside yeswiki mode
import * as aceModeHtml from '../../../../javascripts/vendor/ace/mode-html.js'

import setupAceditorKeyBindings from './aceditor-key-bindings.js'
import openModal from './aceditor-toolbar-remote-modal.js'
import LinkModal from './link-modal.js'
import FileUploadModal from '../../../attach/presentation/javascripts/file-upload-modal.js'

class Aceditor {
  ace = null
  linkModal

  constructor($container) {
    this.$container = $container
    this.initialize()
  }

  get $textarea() {
    return this.$container.find('.aceditor-textarea')
  }

  get $aceContainer() {
    return this.$container.find('.ace-container')
  }

  get $toolbar() {
    return this.$container.find('.aceditor-toolbar')
  }

  initialize() {
    // Where to find the 'mode-XXXX' files
    ace.config.set('basePath', 'tools/aceditor/presentation/javascripts')

    this.ace = ace.edit(this.$container.find('.ace-body')[0], {
      printMargin: false,
      mode: 'ace/mode/yeswiki',
      showGutter: true,
      wrap: 'free',
      maxLines: Infinity,
      minLines: this.$textarea.attr('rows'),
      showFoldWidgets: false,
      fontSize: '18px',
      useSoftTabs: false,
      tabSize: 3,
      fontFamily: 'monospace',
      highlightActiveLine: true
    })

    // Sync textarea and editor
    this.ace.session.setValue(this.$textarea.val())
    this.ace.session.on('change', () => {
      this.$textarea.val(this.ace.session.getValue())
    })

    setupAceditorKeyBindings(this.$aceContainer, this.$toolbar)

    // Enable alert popup when leaving the page
    this.ace.on('change', () => {
      if (typeof showPopup !== 'undefined') { showPopup = 1 }
    })

    this.linkModal = new LinkModal()
    this.fileUplodModal = new FileUploadModal()

    this.fileUplodModal.initButton(
      this.$toolbar.find('.attach-file-uploader'),
      (result) => { this.replaceSelectionBy(result) }
    )

    this.$toolbar.find('.aceditor-btn').on('click', (e) => {
      const $btn = $(e.currentTarget)

      if ($btn.data('remote')) {
        // Remote Modal Button
        openModal($btn.attr('title'), $btn.attr('href'))
      } else if ($btn.hasClass('aceditor-btn-link')) {
        // Link Button
        this.linkModal.open({
          text: this.ace.getSelectedText(),
          onComplete: (result) => { this.replaceSelectionBy(result) }
        })
      } else {
        // Other Buttons
        this.surroundSelectionWith($btn.data('lft'), $btn.data('rgt'))
      }
    })
  }

  surroundSelectionWith(left = '', right = '') {
    this.ace.session.replace(this.ace.getSelectionRange(), left + this.ace.getSelectedText() + right)
  }

  replaceSelectionBy(replacement) {
    this.ace.session.replace(this.ace.getSelectionRange(), replacement)
  }
}

jQuery(() => {
  $('.aceditor-container').each(function() {
    const name = $(this).data('name')
    window[`aceditor-${name}`] = new Aceditor($(this))
  })
})
