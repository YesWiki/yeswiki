/* global pageTags, showPopup:writable */
import setupAceditorKeyBindings from './aceditor-key-bindings.js'
import openModal from './aceditor-toolbar-remote-modal.js'
import LinkPanel from './link-panel.js'
import FilePickerPanel from './file-picker-panel.js'
import { closeRails } from './editor-rails.js'
import { registerEditor } from './editor-handles.js'
import { restoreStashedValue, switchEditorTo } from './editor-switch.js'
import AceWrapper from './ace-wrapper.js'
import ActionsBuilder from './actions-builder.js'
import { fillSourceEditorToolbar } from './editor-markup-syntax.js'

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
  linkPanel
  filePicker
  cursorDrivesRails = false

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

  get isPlainText() {
    return this.container.classList.contains('plain-text')
  }

  initialize() {
    restoreStashedValue(this.textarea)

    this.editor = new AceWrapper(this.aceBody, {
      rows: this.textarea.getAttribute('rows'),
    })
    this.linkPanel = new LinkPanel()
    this.filePicker = new FilePickerPanel()
    this.actionsBuilder = new ActionsBuilder()

    this.editor.setValue(this.textarea.value)
    this.editor.on('change', () => {
      if (this.textarea.value !== this.editor.getValue()) {
        this.textarea.value = this.editor.getValue()
      }
      if (typeof showPopup !== 'undefined') {
        showPopup = 1
      }
    })

    fillSourceEditorToolbar(this.toolbar)
    setupAceditorKeyBindings(this.aceContainer, this.toolbar)
    this.initToolbar()
    this.initEditionHelpers()
    this.editor.ace.setOptions({
      placeholder: this.textarea.getAttribute('placeholder'),
    })
    this.initPlainTextToggle()
  }

  initPlainTextToggle() {
    this.plainTextButton = this.toolbar.querySelector(
      '.aceditor-btn-plain-text',
    )
    if (!this.plainTextButton) return
    this.plainTextButton.addEventListener('click', () => {
      this.setPlainText(!this.isPlainText, true)
      storePlainTextPreference(this.isPlainText)
    })
    this.toolbar.addEventListener(
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
    this.textarea.addEventListener('input', () => {
      if (this.isPlainText && typeof showPopup !== 'undefined') {
        showPopup = 1
      }
    })
    this.editor.on('change', () => {
      if (this.isPlainText) this.syncTextareaSelectionFromEditor()
    })
    if (readPlainTextPreference()) this.setPlainText(true, false)
  }

  /** Swaps ACE for the bare textarea, where the browser's spell-checker and middle-click paste work. */
  setPlainText(enabled, focus) {
    if (enabled) {
      this.container.classList.add('plain-text')
      if (focus) this.textarea.focus()
    } else {
      this.syncEditorFromTextarea()
      this.container.classList.remove('plain-text')
      this.editor.ace.resize(true)
      if (focus) this.editor.ace.focus()
    }
    this.plainTextButton.classList.toggle('active', enabled)
    this.plainTextButton.setAttribute('aria-pressed', String(enabled))
  }

  /** Copies the textarea's text and selection into ACE, so toolbar commands act on what the user selected. */
  syncEditorFromTextarea() {
    const { selectionStart, selectionEnd, value } = this.textarea
    if (this.editor.getValue() !== value) {
      this.editor.setValue(value)
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
      this.textarea.focus()
      this.textarea.setSelectionRange(
        doc.positionToIndex(range.start),
        doc.positionToIndex(range.end),
      )
    })
  }

  initToolbar() {
    this.toolbar.querySelectorAll('.aceditor-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        if (btn.dataset.remote) {
          e.preventDefault()
          openModal(btn.getAttribute('title'), btn.getAttribute('href'))
        } else if (btn.classList.contains('aceditor-btn-link')) {
          this.openLinkPanel({
            action: 'newlink',
            text: this.editor.getSelectedText(),
            range: this.editor.selectionRange(),
          })
        } else if (btn.classList.contains('aceditor-btn-newpage')) {
          this.openLinkPanel({
            action: 'newpage',
            range: this.editor.selectionRange(),
          })
        } else if (btn.classList.contains('aceditor-btn-file')) {
          this.filePicker.open({
            onComplete: (result) => {
              this.editor.replaceSelectionBy(result)
            },
          })
        } else if (btn.dataset.callout) {
          this.editor.applyCallout(btn.dataset.callout)
        } else {
          this.editor.surroundSelectionWith(btn.dataset.lft, btn.dataset.rgt)
        }
      })
    })
    this.toolbar
      .querySelectorAll('.open-actions-builder-btn')
      .forEach((btn) => {
        btn.addEventListener('click', () => {
          this.editor.updateCursor()
          const insertAt = this.insertionPointFrom(this.editor.cursor)
          this.actionsBuilder.open(this.editor, { insertAt })
        })
      })
    this.toolbar
      .querySelectorAll('.aceditor-btn-switch-editor')
      .forEach((btn) => {
        btn.addEventListener('click', () => {
          if (this.isPlainText) this.syncEditorFromTextarea()
          this.textarea.value = this.editor.getValue()
          switchEditorTo('vditor', this.textarea)
        })
      })
  }

  openLinkPanel(options) {
    this.linkPanel.open(this.editor, {
      ...options,
      onComplete: (result) => this.editor.replaceRange(options.range, result),
    })
  }

  /** Where a component chosen from the palette should be written: at the cursor, unless the cursor sits inside a component -- a `{{...}}` tag written inside another one's tag corrupts both -- in which case it goes on a line of its own just below. */
  insertionPointFrom(cursor) {
    if (!cursor) return null
    const row = cursor.row || 0
    const column = cursor.column || 0
    const insideAComponent =
      cursor.groupType === 'yw-action' && cursor.groupEnd !== undefined
    const midLine = column > 0 || this.editor.lineLength(row) > 0

    if (insideAComponent || midLine) {
      return { row, column: this.editor.lineLength(row), onNewLine: true }
    }

    return { row, column }
  }

  /** What identifies the thing a rail is open on, so it knows when it is a different one. */
  railKey(range) {
    return `${this.container.dataset.name}:${range.start.row}:${range.start.column}`
  }

  /** The component this cursor is on -- its wikitext and where it is written -- when the builder can edit it. */
  actionTargetFrom(cursor) {
    if (!cursor || cursor.groupType !== 'yw-action' || !cursor.groupData)
      return null
    if (cursor.groupStart === undefined || cursor.groupEnd === undefined)
      return null
    let target = {
      name: cursor.groupData['action-name'],
      text: cursor.groupTextWithoutMarkup,
      range: this.editor.rangeFor(
        cursor.row,
        cursor.groupStart,
        cursor.groupEnd,
      ),
    }
    if (target.name === 'end') {
      const elem = /elem\s*=\s*["']([^"']+)["']/.exec(target.text || '')
      if (!elem) return null
      target = this.editor.findOpeningTag(
        elem[1],
        cursor.row,
        cursor.groupStart,
      )
      if (!target) return null
    }
    if (
      !this.actionsBuilder.allAvailableActionsWithBackward.includes(target.name)
    )
      return null
    if (this.actionsBuilder.getActionConfiguration(target.name).onlyAdd)
      return null

    return target
  }

  /** The link this cursor is in, in both the syntaxes the editor writes them in. */
  linkTargetFrom(cursor) {
    if (!cursor || !['yw-link', 'yw-link-markdown'].includes(cursor.groupType))
      return null
    if (cursor.groupStart === undefined || cursor.groupEnd === undefined)
      return null

    return {
      data: cursor.groupData || {},
      range: this.editor.rangeFor(
        cursor.row,
        cursor.groupStart,
        cursor.groupEnd,
      ),
    }
  }

  /** The rails follow the cursor: one opens on whatever the cursor moves into -- a component, a link -- swaps to the next one, and closes when the cursor leaves for ordinary text. */
  syncRailsWithCursor(cursor) {
    if (!this.cursorDrivesRails) return
    if (
      this.actionsBuilder.isPlacingNewAction ||
      this.linkPanel.isPlacingNewLink ||
      this.filePicker.isOpen
    )
      return

    const action = this.actionTargetFrom(cursor)
    if (action) {
      this.editor.highlightRange(action.range)
      this.actionsBuilder.open(this.editor, {
        action: action.text,
        target: action.range,
        key: this.railKey(action.range),
        insertAt: this.insertionPointFrom(cursor),
      })
      return
    }

    const link = this.linkTargetFrom(cursor)
    if (link) {
      this.editor.highlightRange(link.range)
      this.openLinkPanel({
        action: 'edit',
        link: link.data['link-url'],
        text: link.data['link-text'],
        title: link.data['link-title'],
        extra: link.data['md-extra'],
        range: link.range,
        key: this.railKey(link.range),
      })
      return
    }

    closeRails()
    this.editor.clearHighlight()
  }

  initEditionHelpers() {
    this.editor.on('focus', () => {
      this.cursorDrivesRails = true
    })
    this.editor.onCursorChange((cursor) => {
      this.editor.disableAutocompletion()
      this.syncRailsWithCursor(cursor)

      if (!cursor.groupType) return

      switch (cursor.groupType) {
        case 'yw-action': {
          if (cursor.nodeType && cursor.nodeType.includes('action-name')) {
            this.editor.setAutocompletionList(
              this.actionsBuilder.allAvailableActions,
            )
          }
          break
        }
        case 'yw-link-markdown':
        case 'yw-link': {
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

ywInitEach('.aceditor-container', (container) => {
  const { name } = container.dataset
  const aceditor = new Aceditor(container)
  window[`aceditor-${name}`] = aceditor
  registerEditor(name, {
    getValue: () => {
      if (aceditor.isPlainText) aceditor.syncEditorFromTextarea()
      return aceditor.editor.getValue()
    },
    setValue: (text) => aceditor.editor.setValue(text),
  })
})
