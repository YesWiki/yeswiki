export default class {
  aceditor = null

  constructor() {
    this.aceditor = $('textarea#body,textarea#bf_contenu').data('aceditor');
  }

  get currentLineNumber() {
    return this.aceditor.selection.getRange().start.row
  }
  get currentLine() {
    return this.aceditor.session.getLine(this.currentLineNumber)
  }
  get cursor() {
    return this.aceditor.selection.getCursor()
  }
  get startLineToCursor() {
    return this.aceditor.session.getTextRange(new ace.Range(this.cursor.row, 0, this.cursor.row, this.cursor.column))
  }
  get cursorToEndLine() {
    return this.aceditor.session.getTextRange(new ace.Range(this.cursor.row, this.cursor.column, this.cursor.row + 1, 0))
  }
  // Return the action without the {{ }} -> exple 'action param="1"'
  get currentSelectedAction() {
    // Multi Row Selection, abort
    if (this.aceditor.getSelectionRange().start.row != this.aceditor.getSelectionRange().end.row) {
      return ""
    }
    // Selected Text contains an action
    if (this.aceditor.getSelectedText().match(/\s*\{\{.*\}\}\s*/g) != null) {
      return this.aceditor.getSelectedText().replace('}}','').replace('{{','').trim()
    }

    const startLineToCursor = this.startLineToCursor
    const cursorToEndLine = this.cursorToEndLine

    // Cursor is in the middle of an action : {{action param="1" CURSOR param="2"}}
    if (startLineToCursor.split('{{').length > 1 && cursorToEndLine.split('}}').length > 1) {
      return startLineToCursor.split('{{').slice(-1)[0] + cursorToEndLine.split('}}')[0]
    }
    // Cursor is at the end of an action : {{action param="1" param="2"}}CURSOR
    if (startLineToCursor.match(/\{\{.*\}\}\s*$/) != null) {
      return startLineToCursor.replace('}}', '').trim()
    }
    // Cursor is at the beggining of an action : CURSOR{{action param="1" param="2"}}
    if (cursorToEndLine.match(/^\s*\{\{.*\}\}/) != null) {
      return cursorToEndLine.replace('{{', '').trim()
    }
    return ""
  }

  selectCurrentAction() {
    const startLineToCursor = this.startLineToCursor
    const cursorToEndLine = this.cursorToEndLine
    const cursor = this.cursor
    
    let textBeforeCursor = startLineToCursor.split('{{').slice(-1)[0]
    let textAfterCursor = cursorToEndLine.split('}}')[0]
    // Cursor is in the middle of an action : {{action param="1" CURSOR param="2"}}
    if (startLineToCursor.split('{{').length > 1 && cursorToEndLine.split('}}').length > 1) {
      this.aceditor.selection.setRange(new ace.Range(
        cursor.row, cursor.column - textBeforeCursor.length - 2,
        cursor.row, cursor.column + textAfterCursor.length + 2));
    }
    // Cursor is at the end of an action : {{action param="1" param="2"}}CURSOR
    else if (startLineToCursor.match(/\{\{.*\}\}\s*$/) != null) {
      this.aceditor.selection.setRange(new ace.Range(
        cursor.row, cursor.column - textBeforeCursor.length - 2,
        cursor.row, cursor.column));
    }
    // Cursor is at the beggining of an action : CURSOR{{action param="1" param="2"}}
    else if (cursorToEndLine.match(/^\s*\{\{.*\}\}/) != null) {
      this.aceditor.selection.setRange(new ace.Range(
        cursor.row, cursor.column,
        cursor.row, cursor.column + textAfterCursor.length + 2));
    }
  }
  replaceCurrentActionBy(text) {
    this.selectCurrentAction()
    this.aceditor.session.replace(this.aceditor.getSelectionRange(), text)
    this.selectCurrentAction()
  }
  insert(text) {
    this.aceditor.insert(text)
    this.selectCurrentAction()
  }
  onCursorChange(callback) {
    $(document).ready(() => {
      this.aceditor.selection.on('changeCursor', callback)
    })
  }

}
