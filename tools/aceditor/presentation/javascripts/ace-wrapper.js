export default class {
  ace = null
  $container
  cursor = {} // ace cursor with more infos
  cursorChangeCallbacks = []

  constructor(domElement, options = {}) {
    this.$container = $(domElement)

    // Where to find the 'mode-XXXX' files
    ace.config.set('basePath', 'tools/aceditor/presentation/javascripts')

    this.ace = ace.edit(domElement, {
      printMargin: false,
      mode: 'ace/mode/yeswiki',
      showGutter: true,
      wrap: 'free',
      maxLines: Infinity,
      minLines: options.rows,
      showFoldWidgets: false,
      fontSize: '18px',
      useSoftTabs: false,
      tabSize: 3,
      fontFamily: 'monospace',
      highlightActiveLine: true
    })

    this.ace.selection.on('changeCursor', () => {
      this.updateCursor()
    })
  }

  // Wrappers methods
  getValue() { return this.ace.session.getValue() }
  setValue(val) { return this.ace.session.setValue(val) }
  getSelectedText() { return this.ace.getSelectedText() }
  on(event, callback) { return this.ace.on(event, callback) }

  onCursorChange(callback) {
    this.cursorChangeCallbacks.push(callback)
  }

  get currentLineNodes() {
    const $renderedLineGroup = this.$container
      .find(`.ace_text-layer > .ace_line_group:nth-of-type(${this.cursor.row + 1})`)
    let allLineNodes = []
    $renderedLineGroup.find('.ace_line').each(function() {
      allLineNodes = allLineNodes.concat(this.childNodes)
    })
    return allLineNodes[0]
  }

  // Based on current cursor, detect if we are inside a specific group (like action or link),
  // and where we are in this group (are we on the action name, or in the link url?)
  // This is using the text highlightings rules. So if you want to detect a group, you need to use
  // "markup.open.GROUP_NAME" and "markup.close.GROUP_NAME" inside mode-yeswiki.js
  // Please refer to "yw-action" as an example
  updateCursor() {
    // Copy ace cursor
    this.cursor = { ...this.ace.selection.getCursor() }

    if (this.currentLineNodes) {
      let [currColumn, nextColumn] = [0, 0]
      this.currentLineNodes.forEach((node) => {
        // iterate until we find a full group, or reach the end of line
        if (this.cursor.groupEnd == null) {
          const $node = $(node)
          nextColumn += $node.text().length
          // Openning group markup, before finding the selectedNode
          if (!this.cursor.$node && $node.hasClass('ace_open')) {
            this.cursor.groupStart = currColumn
            this.cursor.groupStartMarkup = $node.text()
            this.cursor.groupType = $node.attr('class').replace('ace_markup ace_open ace_', '')
          }
          // SelectedNode
          if ($node.text() && currColumn <= this.cursor.column && nextColumn >= this.cursor.column) {
            this.cursor.$node = $node
            this.cursor.nodeStart = currColumn
            this.cursor.nodeEnd = nextColumn
            this.cursor.nodeText = $node.text()
            this.cursor.nodeType = $node.attr('class')
          }
          // Closing group markup, after finding selectedNode
          if (this.cursor.$node && this.cursor.groupStart != undefined && $node.hasClass('ace_close')) {
            this.cursor.groupEnd = nextColumn
            this.cursor.groupEndMarkup = $node.text()
            this.cursor.groupText = this.currentGroupText
            this.cursor.groupTextWithoutMarkup = this.currentGroupTextwithoutMarkup
          }
          currColumn = nextColumn
        }
      })
    }
    // Trigger callbacks
    this.cursorChangeCallbacks.forEach((callback) => {
      callback(this.cursor)
    })
  }

  textFromRange(row, colStart, colEnd) {
    return this.ace.session.getTextRange(new ace.Range(row, colStart, row, colEnd))
  }

  get currentGroupText() {
    return this.textFromRange(this.cursor.row, this.cursor.groupStart, this.cursor.groupEnd)
  }

  get currentGroupTextwithoutMarkup() {
    return this.currentGroupText
      .replace(new RegExp(`^${this.escapeRegExp(this.cursor.groupStartMarkup)}`), '')
      .replace(new RegExp(`${this.escapeRegExp(this.cursor.groupEndMarkup)}$`), '')
      .trim()
  }

  // Espace characters to be used in reg expr
  escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  selectLineRange(row, colStart, colEnd) {
    this.ace.selection.setRange(new ace.Range(row, colStart, row, colEnd))
  }

  selectCurrentNode() {
    this.selectLineRange(this.cursor.row, this.cursor.nodeStart, this.cursor.nodeEnd)
  }

  selectCurrentGroup() {
    this.selectLineRange(this.cursor.row, this.cursor.groupStart, this.cursor.groupEnd)
  }

  surroundSelectionWith(left = '', right = '') {
    this.ace.session.replace(this.ace.getSelectionRange(), left + this.ace.getSelectedText() + right)
  }

  replaceSelectionBy(replacement) {
    this.ace.session.replace(this.ace.getSelectionRange(), replacement)
  }

  replaceCurrentNodeBy(text) {
    this.selectCurrentNode()
    this.replaceSelectionBy(text)
    this.selectCurrentNode()
  }

  replaceCurrentGroupBy(text) {
    this.selectCurrentGroup()
    this.replaceSelectionBy(text)
    this.selectCurrentGroup()
  }

  insert(text) {
    this.ace.insert(text)
    this.selectCurrentGroup()
  }

  // OLD CODE

  // get currentLineNumber() {
  //   return this.ace.selection.getRange().start.row
  // }

  // get currentLine() {
  //   return this.ace.session.getLine(this.currentLineNumber)
  // }

  // get startLineToCursor() {
  //   return this.ace.session.getTextRange(new ace.Range(this.cursor.row, 0, this.cursor.row, this.cursor.column))
  // }

  // get cursorToEndLine() {
  //   return this.ace.session.getTextRange(new ace.Range(this.cursor.row, this.cursor.column, this.cursor.row + 1, 0))
  // }

  // // Return the action without the {{ }} -> exple 'action param="1"'
  // get currentSelectedAction() {
  //   // Multi Row Selection, abort
  //   if (this.ace.getSelectionRange().start.row != this.ace.getSelectionRange().end.row) {
  //     return ''
  //   }
  //   // Selected Text contains an action
  //   if (this.ace.getSelectedText().match(/\s*\{\{.*\}\}\s*/g) != null) {
  //     return this.ace.getSelectedText().replace('}}', '').replace('{{', '').trim()
  //   }

  //   const { startLineToCursor } = this
  //   const { cursorToEndLine } = this

  //   // Cursor is in the middle of an action : {{action param="1" CURSOR param="2"}}
  //   if (startLineToCursor.split('{{').length > 1 && cursorToEndLine.split('}}').length > 1) {
  //     return startLineToCursor.split('{{').slice(-1)[0] + cursorToEndLine.split('}}')[0]
  //   }
  //   // Cursor is at the end of an action : {{action param="1" param="2"}}CURSOR
  //   if (startLineToCursor.match(/\{\{.*\}\}\s*$/) != null) {
  //     return startLineToCursor.replace('}}', '').trim()
  //   }
  //   // Cursor is at the beggining of an action : CURSOR{{action param="1" param="2"}}
  //   if (cursorToEndLine.match(/^\s*\{\{.*\}\}/) != null) {
  //     return cursorToEndLine.replace('{{', '').trim()
  //   }
  //   return ''
  // }

  // selectCurrentAction() {
  //   const { startLineToCursor } = this
  //   const { cursorToEndLine } = this
  //   const { cursor } = this

  //   const textBeforeCursor = startLineToCursor.split('{{').slice(-1)[0]
  //   const textAfterCursor = cursorToEndLine.split('}}')[0]
  //   // Cursor is in the middle of an action : {{action param="1" CURSOR param="2"}}
  //   if (startLineToCursor.split('{{').length > 1 && cursorToEndLine.split('}}').length > 1) {
  //     this.ace.selection.setRange(new ace.Range(
  //       cursor.row,
  //       cursor.column - textBeforeCursor.length - 2,
  //       cursor.row,
  //       cursor.column + textAfterCursor.length + 2
  //     ))
  //   }
  //   // Cursor is at the end of an action : {{action param="1" param="2"}}CURSOR
  //   else if (startLineToCursor.match(/\{\{.*\}\}\s*$/) != null) {
  //     this.ace.selection.setRange(new ace.Range(
  //       cursor.row,
  //       cursor.column - textBeforeCursor.length - 2,
  //       cursor.row,
  //       cursor.column
  //     ))
  //   }
  //   // Cursor is at the beggining of an action : CURSOR{{action param="1" param="2"}}
  //   else if (cursorToEndLine.match(/^\s*\{\{.*\}\}/) != null) {
  //     this.ace.selection.setRange(new ace.Range(
  //       cursor.row,
  //       cursor.column,
  //       cursor.row,
  //       cursor.column + textAfterCursor.length + 2
  //     ))
  //   }
  // }
}
