// remote-modal.js — iframe modal helper for the form-builder admin UI
// (ticket 16: vanilla JS on the yw-modal markup, no Bootstrap modal API)
export default function (title, url) {
  const modal = document.createElement('div')
  modal.className = 'yw-modal'
  modal.innerHTML = `
    <div class="yw-modal__dialog yw-modal__dialog--lg">
      <div class="yw-modal__content">
        <div class="yw-modal__header">
          <h2 class="yw-modal__title"></h2>
          <button type="button" class="yw-close" data-yw-dismiss="modal"
            aria-label="close">&times;</button>
        </div>
        <div class="yw-modal__body">
          <iframe width="100%" scrolling="no" frameborder="0"></iframe>
        </div>
      </div>
    </div>
  `
  modal.querySelector('.yw-modal__title').textContent = title
  const iframe = modal.querySelector('iframe')
  iframe.src = url
  document.body.appendChild(modal)

  // auto resize iframe height
  let timer = null
  iframe.onload = function () {
    const doc = iframe.contentWindow ? iframe.contentWindow.document : null
    if (doc) {
      // remove favorite button and "back/cancel" button in list view
      doc
        .querySelectorAll('.btn.favorites, .yw-btn.favorites, .btn-cancel-list')
        .forEach((el) => el.remove())
    }
    timer = setInterval(() => {
      if (!iframe.contentWindow) return
      iframe.height = `${iframe.contentWindow.document.documentElement.scrollHeight}px`
    }, 200)
  }

  const close = () => {
    clearInterval(timer)
    modal.remove()
  }
  // yw-core.js's generic dismiss handler removes the --open class and fires
  // yw-modal-hidden; this modal is single-use, so remove it entirely then
  modal.addEventListener('yw-modal-hidden', close)
  modal.classList.add('yw-modal--open')

  return { close }
}
