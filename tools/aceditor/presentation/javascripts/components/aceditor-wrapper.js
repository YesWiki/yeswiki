export default class {
  aceditor = null

  constructor() {
    $(document).ready(() => {
      this.aceditor = $('textarea#body').data('aceditor');
    })
  }

  get currentLineNumber() {
    return this.aceditor.selection.getRange().start.row
  }

  get currentLine() {
    return this.aceditor.session.getLine(this.currentLineNumber)
  }

  selectCurrentLine() {
    this.aceditor.selection.selectLine()
  }

  replaceCurrentLineBy(text) {
    const lineNumber = this.currentLineNumber
    this.aceditor.session.replace(new ace.Range(lineNumber, 0, lineNumber + 1, 0), text + "\n");
    this.aceditor.gotoLine(lineNumber + 1)
    this.selectCurrentLine()
  }

  insert(text) {
    this.aceditor.insert(text)
    this.selectCurrentLine()
  }

  onCursorChange(callback) {
    $(document).ready(() => {
      this.aceditor.selection.on('changeCursor', callback)
    })
  }

}
