import openModal from './aceditor-toolbar-remote-modal.js'
import LinkModal from './link-modal.js'

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

  const linkModal = new LinkModal((result) => {
    aceditor.session.replace(aceditor.getSelectionRange(), result)
  })

  $('.aceditor-toolbar').find('a.aceditor-btn')
    .on('click', function(e) {
      e.preventDefault()
      e.stopPropagation()

      if ($(this).data('remote')) {
        // Remote Modal Button
        openModal($(this).attr('title'), $(this).attr('href'))
      } else if ($(this).hasClass('aceditor-btn-link')) {
        // Link Button
        linkModal.open(aceditor.getSelectedText())
      } else {
        // Other Buttons
        textarea.surroundSelectedText($(this).data('lft'), $(this).data('rgt'))
      }

      $(this).parents('.btn-group').removeClass('open')
      return false
    })
}
