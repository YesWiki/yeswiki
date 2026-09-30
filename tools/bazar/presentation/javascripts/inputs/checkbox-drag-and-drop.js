$(document).ready(() => {
  $('.yeswiki-checkbox').each(function () {
    if ($(this).find('.list-entries-to-export .select-page-item').length < 1) {
      $(this).find('.list-entries-to-export .empty-list').show()
    }
  })

  $('ul.checkbox-selection-container').each(function () {
    const text_id = `ul.list-entries-to-export.group-${$(this).data('group')}`
    $(this).sortable({
      connectWith: text_id,
      receive(_event, _ui) {
        $(this)
          .find('.select-page-item')
          .each(function () {
            checkbox_dragndrop_select(this)
            checkbox_dragndrop_update_at_select(this)
          })
      },
      cancel: '.empty-list',
    })
  })

  $('ul.list-entries-to-export').each(function () {
    const text_id = `ul.checkbox-selection-container.group-${$(this).data('group')}`
    $(this).sortable({
      connectWith: text_id,
      receive(_event, _ui) {
        $(this)
          .find('.remove-page-item')
          .each(function () {
            checkbox_dragndrop_remove(this)
            checkbox_dragndrop_update_at_remove(this)
          })
      },
      cancel: '.empty-list',
    })
  })

  $('.btn-erase-filter').on('click', function () {
    $(this)
      .parents('.input-group')
      .find('.checkbox-filter-input')
      .val('')
      .trigger('input')
  })

  $('.checkbox-select-all').on('click', function (event) {
    event.stopPropagation()
    $(this)
      .parents('.export-table-container')
      .find('.list-entries-to-export .list-group-item')
      .not(':hidden')
      .find('.select-page-item')
      .click()
    return false
  })
  $('.checkbox-remove-all').on('click', function (event) {
    event.stopPropagation()
    $(this)
      .parents('.import-table-container')
      .find('ul.checkbox-selection-container .list-group-item')
      .not(':hidden')
      .find('.remove-page-item')
      .click()
    return false
  })

  function checkbox_dragndrop_count_selected(element) {
    const container = $(element).parents('.yeswiki-checkbox')
    container
      .find('.checkbox-selected-count')
      .text(
        container
          .find('ul.checkbox-selection-container .list-group-item')
          .not('.empty-list').length,
      )
  }

  function checkbox_dragndrop_select(element) {
    $(element).siblings().filter('.remove-page-item').removeClass('hide')
    $(element).siblings().filter('.movable').removeClass('hide')
    $(element).siblings().filter('.checkbox-icons-up-down').removeClass('hide')
    $(element).parent().find('.movable-h').addClass('hide')
    $(element).addClass('hide')
    $(element)
      .parents('.yeswiki-checkbox')
      .find('ul.checkbox-selection-container .empty-list')
      .hide()
    $(element).parent().find('input').prop('checked', true)
  }

  function checkbox_dragndrop_update_at_select(element) {
    if (
      $(element)
        .parents('.yeswiki-checkbox')
        .find('.list-entries-to-export .select-page-item').length < 1
    ) {
      $(element)
        .parents('.yeswiki-checkbox')
        .find('.list-entries-to-export .empty-list')
        .show()
    }
    checkbox_dragndrop_count_selected(element)
    $(element)
      .parents('.yeswiki-checkbox')
      .find('.checkbox-filter-input')
      .trigger('input')
  }

  $('.select-page-item').on('click', function () {
    const elem = this
    const listitem = $(this).parent()
    listitem.fadeOut('fast', function () {
      checkbox_dragndrop_select(elem)
      listitem
        .appendTo(
          $(this)
            .parents('.yeswiki-checkbox')
            .find('ul.checkbox-selection-container'),
        )
        .fadeIn('fast')
      checkbox_dragndrop_update_at_select(this)
    })
    return false
  })

  function checkbox_dragndrop_remove(element) {
    $(element).siblings().filter('.select-page-item').removeClass('hide')
    $(element).siblings().filter('.movable').addClass('hide')
    $(element).siblings().filter('.checkbox-icons-up-down').addClass('hide')
    $(element).parent().find('.movable-h').removeClass('hide')
    $(element).addClass('hide')
    $(element)
      .parents('.yeswiki-checkbox')
      .find('.list-entries-to-export .empty-list')
      .hide()
    $(element).parent().find('input').prop('checked', false)
  }

  function checkbox_dragndrop_update_at_remove(element) {
    if (
      $(element)
        .parents('.yeswiki-checkbox')
        .find('.checkbox-selection-container .select-page-item').length < 1
    ) {
      $(element)
        .parents('.yeswiki-checkbox')
        .find('.checkbox-selection-container .empty-list')
        .show()
    }
    checkbox_dragndrop_count_selected(element)
    $(element)
      .parents('.yeswiki-checkbox')
      .find('.checkbox-filter-input')
      .trigger('input')
  }

  $('.remove-page-item').on('click', function () {
    const elem = this
    const listitem = $(this).parent()
    listitem.fadeOut('fast', function () {
      checkbox_dragndrop_remove(elem)
      listitem
        .prependTo(
          $(this)
            .parents('.yeswiki-checkbox')
            .find('ul.list-entries-to-export'),
        )
        .fadeIn('fast')
      checkbox_dragndrop_update_at_remove(this)
    })
    return false
  })

  $('.checkbox-icons-up').on('click', function () {
    const elem_to_move = $(this).parents('.list-group-item')
    if (elem_to_move.prev('.empty-list').length > 0) {
      elem_to_move.prev().prev().before(elem_to_move)
    } else {
      elem_to_move.prev().before(elem_to_move)
    }
  })

  $('.checkbox-icons-down').on('click', function () {
    const elem_to_move = $(this).parents('.list-group-item')
    if (elem_to_move.next('.empty-list').length > 0) {
      elem_to_move.next().next().after(elem_to_move)
    } else {
      elem_to_move.next().after(elem_to_move)
    }
  })

  $('.checkbox-filter-input').on('input', function () {
    const container = $(this).parents('.export-table-container')
    const needle = $(this).val().trim().toLowerCase()
    let count = 0
    container
      .find('.list-entries-to-export .list-group-item')
      .not('.empty-list')
      .each(function () {
        if (
          needle.length > 0 &&
          $(this).text().toLowerCase().indexOf(needle) < 0
        ) {
          $(this).hide()
        } else {
          $(this).show()
          count++
        }
      })
    container.find('.checkbox-filter-count').text(count)
  })
})
