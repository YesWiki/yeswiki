/* global pageTags, showPopup:writable */
import setupAceditorKeyBindings from './aceditor-key-bindings.js'
import openModal from './aceditor-toolbar-remote-modal.js'
import LinkPanel from './link-panel.js'
import FilePickerPanel from './file-picker-panel.js'
import { closeRails } from './editor-rails.js'
import AceWrapper from './ace-wrapper.js'
import ActionsBuilder from './actions-builder.js'

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

  initialize() {
    // Init Components
    this.editor = new AceWrapper(this.aceBody, { rows: this.textarea.getAttribute('rows') })
    this.linkPanel = new LinkPanel()
    this.filePicker = new FilePickerPanel()
    this.actionsBuilder = new ActionsBuilder()

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
  }

  initToolbar() {
    this.toolbar.querySelectorAll('.aceditor-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        if (btn.dataset.remote) {
          // Remote Modal Button
          e.preventDefault()
          openModal(btn.getAttribute('title'), btn.getAttribute('href'))
        } else if (btn.classList.contains('aceditor-btn-link')) {
          // Link Button: the selection is what the link will replace, and it is captured
          // now -- the rail is not a modal, so the selection is free to change under it
          this.openLinkPanel({
            action: 'newlink',
            text: this.editor.getSelectedText(),
            range: this.editor.selectionRange()
          })
        } else if (btn.classList.contains('aceditor-btn-newpage')) {
          // New Page Button
          this.openLinkPanel({ action: 'newpage', range: this.editor.selectionRange() })
        } else if (btn.classList.contains('aceditor-btn-file')) {
          // File Picker Button (ticket 17: replaces the per-button qq.FileUploader init)
          this.filePicker.open({
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
      btn.addEventListener('click', () => {
        // the toolbar button adds a component: it always opens the palette. Editing the
        // one already written is the cursor's job -- see syncRailsWithCursor()
        this.editor.updateCursor()
        const insertAt = this.insertionPointFrom(this.editor.cursor)
        this.actionsBuilder.open(this.editor, { insertAt })
      })
    })
  }

  openLinkPanel(options) {
    this.linkPanel.open(this.editor, {
      ...options,
      onComplete: (result) => this.editor.replaceRange(options.range, result)
    })
  }

  /**
   * Where a component chosen from the palette should be written: at the cursor, unless
   * the cursor sits inside a component -- a `{{...}}` tag written inside another one's
   * tag corrupts both -- in which case it goes on a line of its own just below.
   */
  insertionPointFrom(cursor) {
    if (!cursor) return null
    if (cursor.groupType === 'yw-action' && cursor.groupEnd !== undefined) {
      return { row: cursor.row, column: this.editor.lineLength(cursor.row), onNewLine: true }
    }
    return { row: cursor.row || 0, column: cursor.column || 0 }
  }

  /** What identifies the thing a rail is open on, so it knows when it is a different one. */
  railKey(range) {
    return `${this.container.dataset.name}:${range.start.row}:${range.start.column}`
  }

  /**
   * The component this cursor is on -- its wikitext and where it is written -- when the
   * builder can edit it.
   *
   * A closing `{{end elem="..."}}` counts as being on the component it closes: that tag is
   * the other half of the same component, and its own parameter is only the name of it.
   */
  actionTargetFrom(cursor) {
    if (!cursor || cursor.groupType !== 'yw-action' || !cursor.groupData) return null
    if (cursor.groupStart === undefined || cursor.groupEnd === undefined) return null
    let target = {
      name: cursor.groupData['action-name'],
      text: cursor.groupTextWithoutMarkup,
      range: this.editor.rangeFor(cursor.row, cursor.groupStart, cursor.groupEnd)
    }
    if (target.name === 'end') {
      const elem = /elem\s*=\s*["']([^"']+)["']/.exec(target.text || '')
      if (!elem) return null
      target = this.editor.findOpeningTag(elem[1], cursor.row, cursor.groupStart)
      if (!target) return null
    }
    if (!this.actionsBuilder.allAvailableActionsWithBackward.includes(target.name)) return null
    if (this.actionsBuilder.getActionConfiguration(target.name).onlyAdd) return null

    return target
  }

  /** The link this cursor is in, in both the syntaxes the editor writes them in. */
  linkTargetFrom(cursor) {
    if (!cursor || !['yw-link', 'yw-link-markdown'].includes(cursor.groupType)) return null
    if (cursor.groupStart === undefined || cursor.groupEnd === undefined) return null

    return {
      data: cursor.groupData || {},
      range: this.editor.rangeFor(cursor.row, cursor.groupStart, cursor.groupEnd)
    }
  }

  /**
   * The rails follow the cursor: one opens on whatever the cursor moves into -- a
   * component, a link -- swaps to the next one, and closes when the cursor leaves for
   * ordinary text. There is no button to press first, and none to dismiss them with.
   *
   * Nothing is written back on the way out -- what a rail holds reaches the document only
   * when the user asks for it -- so leaving IS how an unwanted change is abandoned.
   */
  syncRailsWithCursor(cursor) {
    // opening a page whose first line is a component should not open a rail nobody asked
    // for: the cursor only drives them once the editor has actually been used
    if (!this.cursorDrivesRails) return
    // a rail placing something the document does not have yet was opened from the toolbar,
    // not from the cursor, and owns itself until that thing is written
    if (this.actionsBuilder.isPlacingNewAction || this.linkPanel.isPlacingNewLink
        || this.filePicker.isOpen) return

    const action = this.actionTargetFrom(cursor)
    if (action) {
      // re-marked on every move inside it, so that typing in the component keeps the tint
      // on all of it rather than on what it used to span
      this.editor.highlightRange(action.range)
      this.actionsBuilder.open(this.editor, {
        action: action.text,
        target: action.range,
        // WHICH component this is. Same key, same panel: typing inside the one being
        // edited must not tear down its settings and rebuild them under the user
        key: this.railKey(action.range),
        insertAt: this.insertionPointFrom(cursor)
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
        key: this.railKey(link.range)
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
      // Reset
      this.editor.disableAutocompletion()
      this.syncRailsWithCursor(cursor)

      // wait for the full group to be written
      if (!cursor.groupType) return

      // what is left here is autocompletion: editing is the rails' job, and they have
      // already opened themselves on whatever the cursor is in
      switch (cursor.groupType) {
        case 'yw-action': {
          if (cursor.nodeType && cursor.nodeType.includes('action-name')) {
            this.editor.setAutocompletionList(
              this.actionsBuilder.allAvailableActions
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

// ticket 14: one initialiser convention -- see ywInit in yeswiki-base-no-defer.js
// ticket 16: keyed on the container, not on <body>. A boosted navigation swaps the body's
// *contents* -- <body> itself survives -- so a body-keyed initialiser runs once per session
// and every later page gets an uninitialised editor.
ywInitEach('.aceditor-container', (container) => {
  const { name } = container.dataset
  window[`aceditor-${name}`] = new Aceditor(container)
})
