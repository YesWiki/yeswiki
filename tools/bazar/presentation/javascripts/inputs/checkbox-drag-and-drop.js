$(document).ready(() => {
  $('.yeswiki-checkbox').each(function() {
    if ($(this).find('.list-entries-to-export .select-page-item').length < 1) {
      $(this).find('.list-entries-to-export .empty-list').show()
    }
  })

  $('ul.checkbox-selection-container').each(function() {
    const text_id = `ul.list-entries-to-export.group-${$(this).data('group')}`
    $(this).sortable({
      connectWith: text_id,
      receive(event, ui) {
        $(this).find('.select-page-item').each(function() {
          checkbox_dragndrop_select(this)
          checkbox_dragndrop_update_at_select(this)
        })
      },
      cancel: '.empty-list'
    })
  })

  $('ul.list-entries-to-export').each(function() {
    const text_id = `ul.checkbox-selection-container.group-${$(this).data('group')}`
    $(this).sortable({
      connectWith: text_id,
      receive(event, ui) {
        $(this).find('.remove-page-item').each(function() {
          checkbox_dragndrop_remove(this)
          checkbox_dragndrop_update_at_remove(this)
        })
      },
      cancel: '.empty-list'
    })
  })

  $('.btn-erase-filter').on('click', function() {
    $(this).parents('.input-group').find('.checkbox-filter-input').val('')
      .keyup()
  })

  $('.checkbox-select-all').on('click', function(event) {
    event.stopPropagation()
    $(this).parents('.export-table-container').find('.list-entries-to-export .list-group-item').not(':hidden')
      .find('.select-page-item')
      .click()
    return false
  })
  $('.checkbox-remove-all').on('click', function(event) {
    event.stopPropagation()
    $(this).parents('.import-table-container').find('ul.checkbox-selection-container .list-group-item').not(':hidden')
      .find('.remove-page-item')
      .click()
    return false
  })

  function checkbox_dragndrop_select(element) {
    $(element).siblings().filter('.remove-page-item').removeClass('hide')
    $(element).siblings().filter('.movable').removeClass('hide')
    $(element).siblings().filter('.checkbox-icons-up-down').removeClass('hide')
    $(element).parent().find('.movable-h').addClass('hide')
    $(element).addClass('hide')
    $(element).parents('.yeswiki-checkbox').find('ul.checkbox-selection-container .empty-list').hide()
    $(element).parent().find('input').prop('checked', true)
  }

  function checkbox_dragndrop_update_at_select(element) {
    if ($(element).parents('.yeswiki-checkbox').find('.list-entries-to-export .select-page-item').length < 1) {
      $(element).parents('.yeswiki-checkbox').find('.list-entries-to-export .empty-list').show()
    }
    $(element).parents('.yeswiki-checkbox').find('.checkbox-filter-input').keyup()
  }

  $('.select-page-item').on('click', function() {
    const elem = this
    const listitem = $(this).parent()
    listitem.fadeOut('fast', function() {
      checkbox_dragndrop_select(elem)
      listitem.appendTo($(this).parents('.yeswiki-checkbox').find('ul.checkbox-selection-container')).fadeIn('fast')
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
    $(element).parents('.yeswiki-checkbox').find('.list-entries-to-export .empty-list').hide()
    $(element).parent().find('input').prop('checked', false)
  }

  function checkbox_dragndrop_update_at_remove(element) {
    if ($(element).parents('.yeswiki-checkbox').find('.checkbox-selection-container .select-page-item').length < 1) {
      $(element).parents('.yeswiki-checkbox').find('.checkbox-selection-container .empty-list').show()
    }
    $(element).parents('.yeswiki-checkbox').find('.checkbox-filter-input').keyup()
  }

  $('.remove-page-item').on('click', function() {
    const elem = this
    const listitem = $(this).parent()
    listitem.fadeOut('fast', function() {
      checkbox_dragndrop_remove(elem)
      listitem.prependTo($(this).parents('.yeswiki-checkbox').find('ul.list-entries-to-export')).fadeIn('fast')
      checkbox_dragndrop_update_at_remove(this)
    })
    return false
  })

  $('.checkbox-icons-up').on('click', function() {
    const elem_to_move = $(this).parents('.list-group-item')
    if (elem_to_move.prev('.empty-list').length > 0) {
      elem_to_move.prev().prev().before(elem_to_move)
    } else {
      elem_to_move.prev().before(elem_to_move)
    }
  })

  $('.checkbox-icons-down').on('click', function() {
    const elem_to_move = $(this).parents('.list-group-item')
    if (elem_to_move.next('.empty-list').length > 0) {
      elem_to_move.next().next().after(elem_to_move)
    } else {
      elem_to_move.next().after(elem_to_move)
    }
  })

  const filter = $('.checkbox-filter-input')
  filter.keyup(function() {
    // Retrieve the input field text and reset the count to zero
    let count = 0

    // Loop through the comment list
    $(this).parents('.export-table-container').find('.list-group-item').not('.empty-list')
      .each(function() {
      // If the list item does not contain the text phrase fade it out
        if ($(this).text().search(new RegExp(filter.val(), 'i')) < 0) {
          $(this).hide()

          // Show the list item if the phrase matches and increase the count by 1
        } else {
          $(this).show()
          count++
        }
      })

    // Update the count
    const numberItems = count
    $(this).parents('.export-table-container').find('.checkbox-filter-count').text(count)
  })
})
