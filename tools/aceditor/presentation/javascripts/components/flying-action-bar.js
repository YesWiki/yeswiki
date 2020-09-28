export default class {
  editor = null;

  constructor(editor) {
    this.editor = editor
    this.initialize()
  }

  initialize() {
    var flyingActionBar = $(`<div class="flying-action-bar">
      <a class="open-actions-builder-btn btn btn-primary btn-icon">
        <i class="fa fa-pencil-alt"></i>
      </a>
    </div>`)
    $('textarea#body').before(flyingActionBar);

    this.editor.onCursorChange( () => {
      // wait for editor to change cursor
      setTimeout(() => {
        flyingActionBar.toggleClass('active', this.editor.currentSelectedAction != "");
        if (this.editor.currentSelectedAction) {
          let top = $('.ace_gutter-active-line').offset().top - $('.ace-editor-container').offset().top + flyingActionBar.height()
          flyingActionBar.css('top', top + 'px')
        }
      }, 100)
    })
  }
}
