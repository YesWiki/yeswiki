export default class {
  aceditor
  clickHandler

  constructor(aceditorContainer) {
    this.aceditor = aceditorContainer
  }

  get flyingButton() {
    return this.aceditor.querySelector('.flying-edit-button')
  }

  show() {
    const btn = this.flyingButton
    const gutterLine = this.aceditor.querySelector('.ace_gutter-active-line')
    const aceContainer = this.aceditor.querySelector('.ace-container')
    const toolbar = this.aceditor.querySelector('.aceditor-toolbar')
    btn.classList.add('active')
    if (gutterLine && aceContainer) {
      const top = gutterLine.getBoundingClientRect().top
        - aceContainer.getBoundingClientRect().top
        + (toolbar ? toolbar.offsetHeight : 0)
      btn.style.top = `${top}px`
    }
    return this
  }

  hide() {
    this.flyingButton.classList.remove('active')
  }

  onClick(callback) {
    const btn = this.flyingButton
    if (this.clickHandler) btn.removeEventListener('click', this.clickHandler)
    this.clickHandler = callback
    btn.addEventListener('click', callback)
  }
}
