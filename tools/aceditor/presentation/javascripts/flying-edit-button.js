export default class {
  $aceditor

  constructor($aceditorContainer) {
    this.$aceditor = $aceditorContainer
  }

  get $flyingButton() {
    return this.$aceditor.find('.flying-edit-button')
  }

  show() {
    this.$flyingButton.addClass('active')
    const top =
      this.$aceditor.find('.ace_gutter-active-line').offset().top -
      this.$flyingButton.offsetParent().offset().top
    this.$flyingButton.css('top', `${top}px`)
    return this
  }

  hide() {
    this.$flyingButton.removeClass('active')
  }

  onClick(callback) {
    this.$flyingButton.off('click')
    this.$flyingButton.on('click', callback)
  }
}
