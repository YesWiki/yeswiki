export default class {
  editor = null;

  constructor(editor) {
    this.editor = editor
    this.initialize()
  }

  initialize() {
    var flyingActionBar = $(`<div class="flying-action-bar">
      <a class="editor-btn-actions-bazar btn btn-primary btn-icon">
        <i class="fa fa-pencil-alt"></i>
      </a>
    </div>`)
    $('textarea#body').before(flyingActionBar);

    this.editor.onCursorChange( () => {
      let isBazarLine = this.editor.currentLine.match(/^\s*\{\{\s*bazar.*/g) != null
      // wait for editor to change cursor
      setTimeout(() => {
        flyingActionBar.toggleClass('active', isBazarLine);
        if (isBazarLine) {
          let top = $('.ace_gutter-active-line').offset().top - $('.ace-editor-container').offset().top + flyingActionBar.height()
          flyingActionBar.css('top', top + 'px')
        }
      }, 100)
    })
  }
}
