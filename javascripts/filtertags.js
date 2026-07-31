// filtertags.js — tag-filtered page grid (ticket 16: vanilla JS; the jQuery
// wookmark masonry layout is replaced by the browser's normal flow layout —
// filtering simply shows/hides the matching elements)
// ticket 14: one initialiser convention -- see ywInit in yeswiki-base-no-defer.js
ywInitEach('.filter-container', (container) => {
  const elements = Array.from(container.querySelectorAll('.filtered-element'))
  const { elementWidth } = container.dataset
  if (elementWidth) {
    elements.forEach((elementParam) => {
      const element = elementParam
      element.style.width = elementWidth
    })
  }

  const controls = container.previousElementSibling
  if (!controls || !controls.classList.contains('controls')) return

  const applyFilters = () => {
    const activeFilters = Array.from(controls.querySelectorAll('.filter.active'))
      .map((filter) => filter.dataset.filter)
      .filter((value) => value)
    elements.forEach((elementParam) => {
      const element = elementParam
      const matches = activeFilters.every(
        (value) => element.classList.contains(value)
      )
      element.style.display = matches ? '' : 'none'
    })
    const counter = document.querySelector('.nbfilteredelements')
    if (counter) {
      counter.textContent = elements.filter(
        (element) => element.style.display !== 'none'
      ).length
    }
  }

  controls.querySelectorAll('.filter').forEach((filter) => {
    filter.addEventListener('click', (e) => {
      e.preventDefault()
      filter.classList.toggle('active')
      const group = filter.closest('.filter-group')
      if (group && group.dataset.type === 'radio') {
        // for the radio type filter buttons, just one active in one row
        Array.from(filter.parentElement.children).forEach((sibling) => {
          if (sibling !== filter && sibling.classList.contains('filter')) {
            sibling.classList.remove('active')
          }
        })
      }
      applyFilters()
    })
  })
})
