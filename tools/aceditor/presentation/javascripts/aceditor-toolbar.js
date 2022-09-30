import openModal from './aceditor-toolbar-remote-modal.js'
import openAceditorToolbarLinkModal from './aceditor-toolbar-link-modal.js'

export default function setupAceditorToolbarBindings(textarea, aceditor) {
  (function($) {
    $.fn.surroundSelectedText = function(left = '', right = '') {
      return this.each(function() {
        let aceditor = $(this).data('aceditor')
        if (!aceditor) aceditor = $(this).aceditor()
        aceditor.session.replace(aceditor.getSelectionRange(), left + aceditor.getSelectedText() + right)
      })
    }
  }(jQuery, window))

  $('.aceditor-toolbar').find('a.aceditor-btn')
    .on('click', function(e) {
      e.preventDefault()
      e.stopPropagation()

      if ($(this).data('prompt')) {
        // Prompt Button
        const prompt = window.prompt($(this).data('prompt'), $(this).data('prompt-val'))
        if (prompt != null) {
          textarea.surroundSelectedText(`${$(this).data('lft') + prompt} `, $(this).data('rgt'))
        }
      } else if ($(this).data('remote')) {
        // Remote Modal Button
        openModal($(this).attr('title'), $(this).attr('href'))
      } else if ($(this).data('link')) {
        // Link Button
        openAceditorToolbarLinkModal($(this), aceditor)
      } else {
        // Other Buttons
        textarea.surroundSelectedText($(this).data('lft'), $(this).data('rgt'))
      }

      $(this).parents('.btn-group').removeClass('open')
      return false
    })
}
