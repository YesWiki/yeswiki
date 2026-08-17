function selectPageItem(element) {
  Array.from(element.parentNode.children).forEach((sibling) => {
    if (sibling === element) return
    if (
      sibling.matches('.remove-page-item, .movable, .checkbox-icons-up-down')
    ) {
      sibling.classList.remove('hide')
    }
  })
  const movableH = element
    .closest('.list-group-item')
    .querySelector('.movable-h')
  if (movableH) movableH.classList.add('hide')
  element.classList.add('hide')
  const checkbox = element.closest('.list-group-item')
  if (checkbox) {
    const yeswikiCheckbox = checkbox.closest('.yeswiki-checkbox')
    const emptyList = yeswikiCheckbox.querySelector(
      'ul.checkbox-selection-container .empty-list',
    )
    if (emptyList) emptyList.style.display = 'none'
    const input = checkbox.querySelector('input')
    if (input) input.checked = true
  }
}

function updateAtSelect(root) {
  const yeswikiCheckbox = root.closest('.yeswiki-checkbox')
  if (
    yeswikiCheckbox.querySelectorAll(
      '.list-entries-to-export .select-page-item',
    ).length < 1
  ) {
    const emptyList = yeswikiCheckbox.querySelector(
      '.list-entries-to-export .empty-list',
    )
    if (emptyList) emptyList.style.display = ''
  }
  const filterInput = yeswikiCheckbox.querySelector('.checkbox-filter-input')
  if (filterInput) filterInput.dispatchEvent(new Event('input'))
}

function removePageItem(element) {
  Array.from(element.parentNode.children).forEach((sibling) => {
    if (sibling === element) return
    if (sibling.matches('.select-page-item')) sibling.classList.remove('hide')
    if (sibling.matches('.movable, .checkbox-icons-up-down'))
      sibling.classList.add('hide')
  })
  const movableH = element
    .closest('.list-group-item')
    .querySelector('.movable-h')
  if (movableH) movableH.classList.remove('hide')
  element.classList.add('hide')
  const checkbox = element.closest('.list-group-item')
  if (checkbox) {
    const yeswikiCheckbox = checkbox.closest('.yeswiki-checkbox')
    const emptyList = yeswikiCheckbox.querySelector(
      '.list-entries-to-export .empty-list',
    )
    if (emptyList) emptyList.style.display = 'none'
    const input = checkbox.querySelector('input')
    if (input) input.checked = false
  }
}

function updateAtRemove(root) {
  const yeswikiCheckbox = root.closest('.yeswiki-checkbox')
  const remainingSelector = '.checkbox-selection-container .select-page-item'
  if (yeswikiCheckbox.querySelectorAll(remainingSelector).length < 1) {
    const emptyList = yeswikiCheckbox.querySelector(
      '.checkbox-selection-container .empty-list',
    )
    if (emptyList) emptyList.style.display = ''
  }
  const filterInput = yeswikiCheckbox.querySelector('.checkbox-filter-input')
  if (filterInput) filterInput.dispatchEvent(new Event('input'))
}

ywInitEach('.yeswiki-checkbox', (container) => {
  if (
    container.querySelectorAll('.list-entries-to-export .select-page-item')
      .length < 1
  ) {
    const emptyList = container.querySelector(
      '.list-entries-to-export .empty-list',
    )
    if (emptyList) emptyList.style.display = ''
  }
})

ywInitEach('ul.checkbox-selection-container', (list) => {
  Sortable.create(list, {
    group: `checkbox-dnd-${list.dataset.group}`,
    filter: '.empty-list',
    onAdd(evt) {
      evt.item.querySelectorAll('.select-page-item').forEach((el) => {
        selectPageItem(el)
        updateAtSelect(el)
      })
    },
  })
})

ywInitEach('ul.list-entries-to-export', (list) => {
  Sortable.create(list, {
    group: `checkbox-dnd-${list.dataset.group}`,
    filter: '.empty-list',
    onAdd(evt) {
      evt.item.querySelectorAll('.remove-page-item').forEach((el) => {
        removePageItem(el)
        updateAtRemove(el)
      })
    },
  })
})

ywInitEach('.btn-erase-filter', (button) => {
  button.addEventListener('click', () => {
    const input = button
      .closest('.input-group')
      .querySelector('.checkbox-filter-input')
    if (input) {
      input.value = ''
      input.dispatchEvent(new Event('input'))
    }
  })
})

ywInitEach('.checkbox-select-all', (button) => {
  button.addEventListener('click', (event) => {
    event.stopPropagation()
    const items = button
      .closest('.export-table-container')
      .querySelectorAll('.list-entries-to-export .list-group-item')
    items.forEach((item) => {
      if (item.offsetParent === null) return
      const selectItem = item.querySelector('.select-page-item')
      if (selectItem) selectItem.click()
    })
    return false
  })
})

ywInitEach('.checkbox-remove-all', (button) => {
  button.addEventListener('click', (event) => {
    event.stopPropagation()
    const items = button
      .closest('.import-table-container')
      .querySelectorAll('ul.checkbox-selection-container .list-group-item')
    items.forEach((item) => {
      if (item.offsetParent === null) return
      const removeItem = item.querySelector('.remove-page-item')
      if (removeItem) removeItem.click()
    })
    return false
  })
})

ywInitEach('.select-page-item', (element) => {
  element.addEventListener('click', () => {
    const listitem = element.parentNode
    const yeswikiCheckbox = listitem.closest('.yeswiki-checkbox')
    const target = yeswikiCheckbox.querySelector(
      'ul.checkbox-selection-container',
    )
    selectPageItem(element)
    target.appendChild(listitem)
    updateAtSelect(element)
    return false
  })
})

ywInitEach('.remove-page-item', (element) => {
  element.addEventListener('click', () => {
    const listitem = element.parentNode
    const target = listitem
      .closest('.yeswiki-checkbox')
      .querySelector('ul.list-entries-to-export')
    removePageItem(element)
    target.insertBefore(listitem, target.firstChild)
    updateAtRemove(element)
    return false
  })
})

ywInitEach('.checkbox-icons-up', (button) => {
  button.addEventListener('click', () => {
    const elemToMove = button.closest('.list-group-item')
    const prev = elemToMove.previousElementSibling
    if (prev && prev.matches('.empty-list')) {
      const beforePrev = prev.previousElementSibling
      if (beforePrev) beforePrev.parentNode.insertBefore(elemToMove, beforePrev)
    } else if (prev) {
      prev.parentNode.insertBefore(elemToMove, prev)
    }
  })
})

ywInitEach('.checkbox-icons-down', (button) => {
  button.addEventListener('click', () => {
    const elemToMove = button.closest('.list-group-item')
    const next = elemToMove.nextElementSibling
    if (next && next.matches('.empty-list')) {
      const afterNext = next.nextElementSibling
      if (afterNext)
        afterNext.parentNode.insertBefore(elemToMove, afterNext.nextSibling)
    } else if (next) {
      next.parentNode.insertBefore(elemToMove, next.nextSibling)
    }
  })
})

ywInitEach('.checkbox-filter-input', (filter) => {
  filter.addEventListener('input', () => {
    let count = 0
    const items = filter
      .closest('.export-table-container')
      .querySelectorAll('.list-group-item:not(.empty-list)')
    items.forEach((rawItem) => {
      const item = rawItem
      if (item.textContent.search(new RegExp(filter.value, 'i')) < 0) {
        item.style.display = 'none'
      } else {
        item.style.display = ''
        count += 1
      }
    })
    const counter = filter
      .closest('.export-table-container')
      .querySelector('.checkbox-filter-count')
    if (counter) counter.textContent = count
  })
})
