export default function (title, url) {
  const modal = document.createElement('div')
  modal.className = 'yw-modal'
  modal.innerHTML = `
    <div class="yw-modal__dialog yw-modal__dialog--lg">
      <div class="yw-modal__content">
        <div class="yw-modal__header">
          <h2 class="yw-modal__title"></h2>
          <button type="button" class="yw-close" data-yw-dismiss="modal">&times;</button>
        </div>
        <div class="yw-modal__body" style="min-height:500px">
          <span class="throbber"></span>
        </div>
      </div>
    </div>
  `
  modal.querySelector('.yw-modal__title').textContent = title

  document.body.appendChild(modal)
  modal.classList.add('yw-modal--open')

  fetch(url)
    .then((response) => response.text())
    .then((html) => {
      const page = new DOMParser()
        .parseFromString(html, 'text/html')
        .querySelector('.page')
      modal.querySelector('.yw-modal__body').innerHTML = page
        ? page.innerHTML
        : html
    })

  const observer = new MutationObserver(() => {
    if (!modal.classList.contains('yw-modal--open')) {
      observer.disconnect()
      modal.remove()
    }
  })
  observer.observe(modal, { attributes: true, attributeFilter: ['class'] })
}
