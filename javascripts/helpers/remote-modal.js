export default function(title, url) {
  const $modal = $(`
    <div class="modal fade">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h2>${title}</h2>
          </div>
          <div class="modal-body">
            <iframe src="${url}" width="100%" scrolling="no" frameborder="0"></iframe>
          </div>
        </div>
      </div>
    </div>
  `)

  $('body').append($modal)

  // auto resize iframe height
  const $iframe = $modal.find('iframe')
  const iframe = $iframe[0]
  let timer = null
  iframe.onload = function() {
    // remove favorite button
    $iframe.contents().find('.btn.favorites').remove()
    // remove "back/cancel" button in list view
    $iframe.contents().find('.btn-cancel-list').remove()
    // auto adjust iframe height
    timer = setInterval(() => {
      if (!iframe.contentWindow) return
      iframe.height = `${iframe.contentWindow.document.documentElement.scrollHeight}px`
    }, 200)
  }

  $modal.modal({
    show: true,
    keyboard: false
  }).on('hidden hidden.bs.modal', () => {
    $modal.remove()
  })

  return {
    close() {
      $modal.modal('hide')
      clearInterval(timer)
    }
  }
}
