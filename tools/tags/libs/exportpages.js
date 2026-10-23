$(document).ready(() => {
  $('#ebook-selection-container').sortable()

  $('.btn-erase-filter').on('click', () => {
    $('#filter').val('').keyup()
  })

  $('.select-page-item').on('click', function () {
    $(this).siblings().filter('.remove-page-item').removeClass('hide')
    $(this).siblings().filter('.movable').removeClass('hide')
    $(this).addClass('hide')
    const listitem = $(this).parent()
    listitem.fadeOut('fast', () => {
      listitem.appendTo('#ebook-selection-container').fadeIn('fast')
    })
    return false
  })

  $('.remove-page-item').on('click', function () {
    $(this).siblings().filter('.select-page-item').removeClass('hide')
    $(this).siblings().filter('.movable').addClass('hide')
    $(this).addClass('hide')
    const listitem = $(this).parent()
    listitem.fadeOut('fast', () => {
      listitem.prependTo('#list-pages-to-export').fadeIn('fast')
    })
    return false
  })

  const listpages = $('#list-pages-to-export .list-group-item')
  const filter = $('#filter')
  const filtercount = $('#filter-count')
  filter.keyup(() => {
    let count = 0

    listpages.each(function () {
      if ($(this).text().search(new RegExp(filter.val(), 'i')) < 0) {
        $(this).hide()
      } else {
        $(this).show()
        count++
      }
    })

    filtercount.text(_t('TAGS_NUMBER_OF_PAGES'), { nb: count })
  })
})
