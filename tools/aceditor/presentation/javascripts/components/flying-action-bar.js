export default class {
  editor = null;
  actionGroups = {}

  constructor(editor, actionGroups) {
    this.editor = editor
    this.actionGroups = actionGroups
    this.initialize()
  }

  get allAvailableActions() {
    return Object.values(this.actionGroups).map(e => Object.keys(e.actions)).flat()
  }

  get actionIsSelected() {
    let fakeDom = $(`<${this.editor.currentSelectedAction}/>`)[0]
    if (fakeDom && fakeDom.tagName) 
      return this.allAvailableActions.includes(fakeDom.tagName.toLowerCase())
    else 
      return false
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
        flyingActionBar.toggleClass('active', this.actionIsSelected);
        $('.component-action-list').toggleClass('only-edit', this.actionIsSelected)
        if (this.actionIsSelected) {
          let top = $('.ace_gutter-active-line').offset().top - $('.ace-editor-container').offset().top + $('.aceditor-toolbar').height()
          flyingActionBar.css('top', top + 'px')
        }
      }, 100)
    })
  }
}
