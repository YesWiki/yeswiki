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

  let timer = null
  iframe.onload = function () {
    const doc = iframe.contentWindow ? iframe.contentWindow.document : null
    if (doc) {
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
  modal.addEventListener('yw-modal-hidden', close)
  modal.classList.add('yw-modal--open')

  return { close }
}
