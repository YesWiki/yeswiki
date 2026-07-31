/* global pageTags, showPopup:writable */
import setupAceditorKeyBindings from './aceditor-key-bindings.js'
import openModal from './aceditor-toolbar-remote-modal.js'
import LinkModal from './link-modal.js'
import FilePickerModal from './file-picker-modal.js'
import AceWrapper from './ace-wrapper.js'
import ActionsBuilder from './actions-builder.js'
import FlyingEditButton from './flying-edit-button.js'

class Aceditor {
  editor
  linkModal
  filePickerModal

  constructor(container) {
    this.container = container
    this.initialize()
  }

  get textarea() {
    return this.container.querySelector('.aceditor-textarea')
  }

  get aceContainer() {
    return this.container.querySelector('.ace-container')
  }

  get aceBody() {
    return this.container.querySelector('.ace-body')
  }

  get toolbar() {
    return this.container.querySelector('.aceditor-toolbar')
  }

  initialize() {
    // Init Components
    this.editor = new AceWrapper(this.aceBody, { rows: this.textarea.getAttribute('rows') })
    this.linkModal = new LinkModal()
    this.filePickerModal = new FilePickerModal()
    this.actionsBuilder = new ActionsBuilder()
    this.flyingButton = new FlyingEditButton(this.container)

    // Sync textarea and editor
    this.editor.setValue(this.textarea.value)
    this.editor.on('change', () => {
      this.textarea.value = this.editor.getValue()
      // Enable alert popup when leaving the page
      if (typeof showPopup !== 'undefined') {
        showPopup = 1
      }
    })

    setupAceditorKeyBindings(this.aceContainer, this.toolbar)
    this.initToolbar()
    this.initEditionHelpers()
    this.editor.ace.setOptions({ placeholder: this.textarea.getAttribute('placeholder') })
    this.editor.on('blur', () => {
      this.flyingButton.hide()
    })
  }

  initToolbar() {
    this.toolbar.querySelectorAll('.aceditor-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        if (btn.dataset.remote) {
          // Remote Modal Button
          e.preventDefault()
          openModal(btn.getAttribute('title'), btn.getAttribute('href'))
        } else if (btn.classList.contains('aceditor-btn-link')) {
          // Link Button
          this.linkModal.open({
            action: 'newlink',
            text: this.editor.getSelectedText(),
            onComplete: (result) => {
              this.editor.replaceSelectionBy(result)
            }
          })
        } else if (btn.classList.contains('aceditor-btn-newpage')) {
          // New Page Button
          this.linkModal.open({
            action: 'newpage',
            onComplete: (result) => {
              this.editor.insert(result)
            }
          })
        } else if (btn.classList.contains('aceditor-btn-file')) {
          // File Picker Button (ticket 17: replaces the per-button qq.FileUploader init)
          this.filePickerModal.open({
            onComplete: (result) => {
              this.editor.replaceSelectionBy(result)
            }
          })
        } else {
          // Other Buttons
          this.editor.surroundSelectionWith(btn.dataset.lft, btn.dataset.rgt)
        }
      })
    })
    this.toolbar.querySelectorAll('.open-actions-builder-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        this.actionsBuilder.open(this.editor, { groupName: e.currentTarget.dataset.groupName })
      })
    })
    this.toolbar.querySelectorAll('.open-existing-action').forEach((btn) => {
      btn.addEventListener('click', () => {
        this.actionsBuilder.open(this.editor, { action: this.editor.currentGroupTextwithoutMarkup })
      })
    })
  }

  initEditionHelpers() {
    this.editor.onCursorChange((cursor) => {
      // Reset
      this.toolbar.querySelectorAll('.component-action-list')
        .forEach((el) => el.classList.remove('only-edit'))
      this.flyingButton.hide()
      this.editor.disableAutocompletion()

      // wait for the full group to be written
      if (!cursor.groupType) return

      switch (cursor.groupType) {
        case 'yw-action': {
          const actionName = cursor.groupData['action-name']
          if (this.actionsBuilder.allAvailableActionsWithBackward.includes(actionName)) {
            if (this.actionsBuilder.getActionConfiguration(actionName).onlyAdd) return
            this.toolbar.querySelectorAll('.component-action-list')
              .forEach((el) => el.classList.add('only-edit'))
            this.flyingButton.show().onClick(() => {
              this.actionsBuilder.open(this.editor, { action: cursor.groupTextWithoutMarkup })
            })
          }
          if (cursor.nodeType && cursor.nodeType.includes('action-name')) {
            this.editor.setAutocompletionList(
              this.actionsBuilder.allAvailableActions
            )
          }
          break
        }
        case 'yw-link-markdown':
        case 'yw-link': {
          const {
            'link-url': link,
            'link-text': text,
            'link-title': title,
            'md-extra': extra
          } = cursor.groupData
          this.flyingButton.show().onClick(() => {
            this.linkModal.open({
              action: 'edit',
              link,
              text,
              title,
              extra,
              onComplete: (result) => {
                this.editor.replaceCurrentGroupBy(result)
              }
            })
          })
          if (cursor.nodeType && cursor.nodeType.includes('link-url')) {
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

// ticket 14: one initialiser convention -- see ywInit in yeswiki-base-no-defer.js
ywInitEach('body', () => {
  document.querySelectorAll('.aceditor-container').forEach((container) => {
    const { name } = container.dataset
    window[`aceditor-${name}`] = new Aceditor(container)
  })
})
