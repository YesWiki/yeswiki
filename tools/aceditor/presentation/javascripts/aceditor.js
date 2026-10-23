import setupAceditorKeyBindings from './aceditor-key-bindings.js'
import openModal from './aceditor-toolbar-remote-modal.js'
import LinkModal from './link-modal.js'
import FileUploadModal from '../../../attach/presentation/javascripts/file-upload-modal.js'
import AceWrapper from './ace-wrapper.js'
import ActionsBuilder from './actions-builder.js'
import FlyingEditButton from './flying-edit-button.js'

const PLAIN_TEXT_STORAGE_KEY = 'yeswiki-aceditor-plain-text'

/** Whether this browser last left the editor in plain-text mode. */
function readPlainTextPreference() {
  try {
    return localStorage.getItem(PLAIN_TEXT_STORAGE_KEY) === '1'
  } catch {
    return false
  }
}

/** Remembers the plain-text mode for this browser; false when storage is unavailable. */
function storePlainTextPreference(enabled) {
  try {
    if (enabled) localStorage.setItem(PLAIN_TEXT_STORAGE_KEY, '1')
    else localStorage.removeItem(PLAIN_TEXT_STORAGE_KEY)
    return true
  } catch {
    return false
  }
}

class Aceditor {
  editor
  linkModal
  fileUplodModal

  constructor($container) {
    this.$container = $container
    this.$toolbar = $container.find('.aceditor-toolbar')
    this.initialize()
  }

  get $textarea() {
    return this.$container.find('.aceditor-textarea')
  }

  get $aceContainer() {
    return this.$container.find('.ace-container')
  }

  get $aceBody() {
    return this.$container.find('.ace-body')
  }

  get isPlainText() {
    return this.$container.hasClass('plain-text')
  }

  initialize() {
    this.editor = new AceWrapper(this.$aceBody[0], {
      rows: this.$textarea.attr('rows'),
    })
    this.linkModal = new LinkModal()
    this.fileUplodModal = new FileUploadModal()
    this.actionsBuilder = new ActionsBuilder()
    this.flyingButton = new FlyingEditButton(this.$container)

    this.editor.setValue(this.$textarea.val())
    this.editor.on('change', () => {
      if (this.$textarea.val() !== this.editor.getValue()) {
        this.$textarea.val(this.editor.getValue())
      }
      if (typeof showPopup !== 'undefined') {
        showPopup = 1
      }
    })

    setupAceditorKeyBindings(this.$aceContainer, this.$toolbar)
    this.initToolbar()
    this.initEditionHelpers()
    this.editor.ace.setOptions({
      placeholder: this.$textarea.attr('placeholder'),
    })
    this.editor.on('blur', () => {
      this.flyingButton.hide()
    })
    this.initPlainTextToggle()
  }

  initPlainTextToggle() {
    this.$plainTextButton = this.$toolbar.find('.aceditor-btn-plain-text')
    this.$plainTextButton.on('click', () => {
      this.setPlainText(!this.isPlainText, true)
      storePlainTextPreference(this.isPlainText)
    })
    this.$toolbar[0].addEventListener(
      'mousedown',
      (event) => {
        if (
          this.isPlainText &&
          !event.target.closest('.aceditor-btn-plain-text')
        ) {
          this.syncEditorFromTextarea()
        }
      },
      true,
    )
    this.editor.on('change', () => {
      if (this.isPlainText) this.syncTextareaSelectionFromEditor()
    })
    if (readPlainTextPreference()) this.setPlainText(true, false)
  }

  /** Swaps ACE for the bare textarea, where the browser's spell-checker and middle-click paste work. */
  setPlainText(enabled, focus) {
    if (enabled) {
      this.$container.addClass('plain-text')
      if (focus) this.$textarea.trigger('focus')
    } else {
      this.syncEditorFromTextarea()
      this.$container.removeClass('plain-text')
      this.editor.ace.resize(true)
      if (focus) this.editor.ace.focus()
    }
    this.$plainTextButton
      .toggleClass('active', enabled)
      .attr('aria-pressed', String(enabled))
  }

  /** Copies the textarea's text and selection into ACE, so toolbar commands act on what the user selected. */
  syncEditorFromTextarea() {
    const textarea = this.$textarea[0]
    const { selectionStart, selectionEnd } = textarea
    if (this.editor.getValue() !== textarea.value) {
      this.editor.setValue(textarea.value)
    }
    const { doc } = this.editor.ace.session
    const start = doc.indexToPosition(selectionStart)
    const end = doc.indexToPosition(selectionEnd)
    this.editor.ace.selection.setRange(
      new ace.Range(start.row, start.column, end.row, end.column),
    )
  }

  /** Gives the textarea back the focus and ACE's selection once a toolbar command has edited the text. */
  syncTextareaSelectionFromEditor() {
    clearTimeout(this.selectionSyncTimeout)
    this.selectionSyncTimeout = setTimeout(() => {
      const { doc } = this.editor.ace.session
      const range = this.editor.ace.getSelectionRange()
      const textarea = this.$textarea[0]
      textarea.focus()
      textarea.setSelectionRange(
        doc.positionToIndex(range.start),
        doc.positionToIndex(range.end),
      )
    })
  }

  initToolbar() {
    this.fileUplodModal.initButton(
      this.$toolbar.find('.attach-file-uploader'),
      (result) => {
        this.editor.replaceSelectionBy(result)
      },
    )
    this.$toolbar.find('.aceditor-btn').on('click', (e) => {
      const $btn = $(e.currentTarget)

      if ($btn.data('remote')) {
        e.preventDefault()
        openModal($btn.attr('title'), $btn.attr('href'))
      } else if ($btn.hasClass('aceditor-btn-link')) {
        this.linkModal.open({
          action: 'newlink',
          text: this.editor.getSelectedText(),
          onComplete: (result) => {
            this.editor.replaceSelectionBy(result)
          },
        })
      } else if ($btn.hasClass('aceditor-btn-newpage')) {
        this.linkModal.open({
          action: 'newpage',
          onComplete: (result) => {
            this.editor.insert(result)
          },
        })
      } else {
        this.editor.surroundSelectionWith($btn.data('lft'), $btn.data('rgt'))
      }
    })
    this.$toolbar.find('.open-actions-builder-btn').click((event) => {
      this.actionsBuilder.open(this.editor, {
        groupName: $(event.target).data('group-name'),
      })
    })
    this.$toolbar.find('.open-existing-action').click(() => {
      this.actionsBuilder.open(this.editor, {
        action: this.editor.currentGroupTextwithoutMarkup,
      })
    })
  }

  initEditionHelpers() {
    this.editor.onCursorChange((cursor) => {
      this.$toolbar.find('.component-action-list').removeClass('only-edit')
      this.flyingButton.hide()
      this.editor.disableAutocompletion()

      if (!cursor.groupType) return

      switch (cursor.groupType) {
        case 'yw-action': {
          const actionName = cursor.groupData['action-name']
          if (
            this.actionsBuilder.allAvailableActionsWithBackward.includes(
              actionName,
            )
          ) {
            if (this.actionsBuilder.getActionConfiguration(actionName).onlyAdd)
              return
            this.$toolbar.find('.component-action-list').addClass('only-edit')
            this.flyingButton.show().onClick(() => {
              this.actionsBuilder.open(this.editor, {
                action: cursor.groupTextWithoutMarkup,
              })
            })
          }
          if (cursor.nodeType && cursor.nodeType.includes('action-name')) {
            this.editor.setAutocompletionList(
              this.actionsBuilder.allAvailableActions,
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
            'md-extra': extra,
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
              },
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

jQuery(() => {
  $('.aceditor-container').each(function () {
    const name = $(this).data('name')
    window[`aceditor-${name}`] = new Aceditor($(this))
  })

  $('.wiki-textarea .scroll-container-toolbar').each(function () {
    $(this).prependTo($(this).parents('.wiki-textarea'))
  })
})
