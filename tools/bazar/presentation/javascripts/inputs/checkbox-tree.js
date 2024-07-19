$(document).ready(() => {
  // Handle chevron click (only expand/collapse)
  $('.checkbox-node .checkbox-label').on('click', function(event) {
    const nodeContainer = $(this).closest('.node-container')
    const childrenContainer = nodeContainer.find('> .node-children')

    if (childrenContainer.find('.node-container').length === 0) return

    event.stopPropagation()
    event.preventDefault()

    if (childrenContainer.is(':visible')) {
      childrenContainer.slideUp(250)
      nodeContainer.removeClass('expanded')
    } else {
      childrenContainer.slideDown(250)
      nodeContainer.addClass('expanded')
    }
  })

  // Handle checkbox clicks - expand/collapse & check parent uncheck children
  $('.checkbox-node input[type=checkbox]').on('change', function() {
    const nodeContainer = $(this).closest('.node-container')
    const childrenContainer = nodeContainer.find('> .node-children')

    if ($(this).is(':checked')) {
      childrenContainer.slideDown(300)
      nodeContainer.addClass('expanded')
      nodeContainer.parents('.node-container').each(function() {
        $(this).find('> .checkbox-node input[type=checkbox]').prop('checked', true)
      })
    } else {
      childrenContainer.slideUp(200)
      nodeContainer.removeClass('expanded')
      childrenContainer.find('.node-children').hide()
      childrenContainer.find('.node-container').removeClass('expanded')
      childrenContainer.find('input[type=checkbox]').prop('checked', false)
    }
  })

  $('.check-all').on('change', function() {
    const checked = $(this).is(':checked')
    $(this).closest('.check-all-container').siblings('.node-container').each(function() {
      $(this).find('> .checkbox-node input[type=checkbox]').prop('checked', checked).trigger('change')
    })
  })
})
