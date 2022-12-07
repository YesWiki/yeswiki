export default function(title, url) {
  const $modal = $(`
    <div class="modal fade">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h2>${title}</h2>
          </div>
          <div class="modal-body" style="min-height:500px">
            <span id="yw-modal-loading" class="throbber"></span>
          </div>
        </div>
      </div>
    </div>
  `)

  $('body').append($modal)
  $modal.find('.modal-body').load(`${url} .page`, () => false)
  $modal.modal({
    show: true,
    keyboard: false
  }).on('hidden hidden.bs.modal', () => {
    $modal.remove()
  })
}
