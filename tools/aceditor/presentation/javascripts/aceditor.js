import setupAceditorKeyBindings from './aceditor-key-bindings.js'
import openModal from './aceditor-toolbar-remote-modal.js'
import LinkModal from './link-modal.js'
import FileUploadModal from '../../../attach/presentation/javascripts/file-upload-modal.js'
import AceWrapper from './ace-wrapper.js'
import ActionsBuilder from './actions-builder.js'
import FlyingEditButton from './flying-edit-button.js'

class Aceditor {
  editor
  linkModal
  fileUplodModal

  constructor($container) {
    this.$container = $container
    this.initialize()
  }

  get $textarea() { return this.$container.find('.aceditor-textarea') }
  get $aceContainer() { return this.$container.find('.ace-container') }
  get $aceBody() { return this.$container.find('.ace-body') }
  get $toolbar() { return this.$container.find('.aceditor-toolbar') }

  initialize() {
    // Init Components
    this.editor = new AceWrapper(this.$aceBody[0], { rows: this.$textarea.attr('rows') })
    this.linkModal = new LinkModal()
    this.fileUplodModal = new FileUploadModal()
    this.actionsBuilder = new ActionsBuilder()
    this.flyingButton = new FlyingEditButton(this.$container)

    // Sync textarea and editor
    this.editor.setValue(this.$textarea.val())
    this.editor.on('change', () => {
      this.$textarea.val(this.editor.getValue())
      // Enable alert popup when leaving the page
      if (typeof showPopup !== 'undefined') { showPopup = 1 }
    })

    setupAceditorKeyBindings(this.$aceContainer, this.$toolbar)
    this.initToolbar()
    this.initEditionHelpers()
  }

  initToolbar() {
    this.fileUplodModal.initButton(
      this.$toolbar.find('.attach-file-uploader'),
      (result) => { this.editor.replaceSelectionBy(result) }
    )
    this.$toolbar.find('.aceditor-btn').on('click', (e) => {
      const $btn = $(e.currentTarget)

      if ($btn.data('remote')) {
        // Remote Modal Button
        e.preventDefault()
        openModal($btn.attr('title'), $btn.attr('href'))
      } else if ($btn.hasClass('aceditor-btn-link')) {
        // Link Button
        this.linkModal.open({
          action: 'newlink',
          text: this.editor.getSelectedText(),
          onComplete: (result) => { this.editor.replaceSelectionBy(result) }
        })
      } else if ($btn.hasClass('aceditor-btn-newpage')) {
        // New Page Button
        this.linkModal.open({
          action: 'newpage',
          onComplete: (result) => { this.editor.insert(result) }
        })
      } else {
        // Other Buttons
        this.editor.surroundSelectionWith($btn.data('lft'), $btn.data('rgt'))
      }
    })
    this.$toolbar.find('.open-actions-builder-btn').click((event) => {
      this.actionsBuilder.open(this.editor, { groupName: $(event.target).data('group-name') })
    })
    this.$toolbar.find('.open-existing-action').click(() => {
      this.actionsBuilder.open(this.editor, { action: this.editor.currentGroupTextwithoutMarkup })
    })
  }

  initEditionHelpers() {
    this.editor.onCursorChange((cursor) => {
      // Reset
      this.$toolbar.find('.component-action-list').removeClass('only-edit')
      this.flyingButton.hide()
      this.editor.disableAutocompletion()

      // wait for the full group to be written
      if (!cursor.groupType || !cursor.groupEnd) return

      switch (cursor.groupType) {
        case 'yw-action': {
          const [actionName] = cursor.groupTextWithoutMarkup.split(' ')
          if (this.actionsBuilder.allAvailableActions.includes(actionName)) {
            this.$toolbar.find('.component-action-list').addClass('only-edit')
            this.flyingButton.show().onClick(() => {
              this.actionsBuilder.open(this.editor, { action: cursor.groupTextWithoutMarkup })
            })
          }
          break
        }
        case 'yw-link': {
          const [link, text] = cursor.groupTextWithoutMarkup.split(' ')
          this.flyingButton.show().onClick(() => {
            this.linkModal.open({
              action: 'edit',
              link,
              text,
              onComplete: (result) => { this.editor.replaceCurrentGroupBy(result) }
            })
          })
          if (cursor.nodeType.includes('ace_link-url')) {
            this.editor.setAutocompletionList(pageTags)
          }
          break
        }
        default:
          break
      }
    })
  }
}

jQuery(() => {
  $('.aceditor-container').each(function() {
    const name = $(this).data('name')
    window[`aceditor-${name}`] = new Aceditor($(this))
  })
})
