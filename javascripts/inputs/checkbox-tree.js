function hideElement(el) {
  el.style.display = 'none'
}

function uncheckElement(el) {
  el.checked = false
}

ywInitEach('.checkbox-node .checkbox-label', (label) => {
  label.addEventListener('click', (event) => {
    const nodeContainer = label.closest('.node-container')
    const childrenContainer = nodeContainer.querySelector(
      ':scope > .node-children',
    )
    const hasChildren =
      childrenContainer &&
      childrenContainer.querySelectorAll('.node-container').length > 0

    if (!hasChildren) return

    event.stopPropagation()
    event.preventDefault()

    const isVisible =
      childrenContainer.style.display !== 'none' &&
      childrenContainer.offsetParent !== null
    if (isVisible) {
      childrenContainer.style.display = 'none'
      nodeContainer.classList.remove('expanded')
    } else {
      childrenContainer.style.display = ''
      nodeContainer.classList.add('expanded')
    }
  })
})

ywInitEach('.checkbox-node input[type=checkbox]', (checkbox) => {
  checkbox.addEventListener('change', () => {
    const nodeContainer = checkbox.closest('.node-container')
    const childrenContainer = nodeContainer.querySelector(
      ':scope > .node-children',
    )

    if (checkbox.checked) {
      if (childrenContainer) childrenContainer.style.display = ''
      nodeContainer.classList.add('expanded')
      let ancestor = nodeContainer.parentElement
        ? nodeContainer.parentElement.closest('.node-container')
        : null
      while (ancestor) {
        const ancestorCheckbox = ancestor.querySelector(
          ':scope > .checkbox-node input[type=checkbox]',
        )
        if (ancestorCheckbox) ancestorCheckbox.checked = true
        ancestor = ancestor.parentElement
          ? ancestor.parentElement.closest('.node-container')
          : null
      }
    } else {
      if (childrenContainer) {
        childrenContainer.style.display = 'none'
        childrenContainer
          .querySelectorAll('.node-children')
          .forEach(hideElement)
        childrenContainer
          .querySelectorAll('.node-container')
          .forEach((el) => el.classList.remove('expanded'))
        childrenContainer
          .querySelectorAll('input[type=checkbox]')
          .forEach(uncheckElement)
      }
      nodeContainer.classList.remove('expanded')
    }
  })
})

ywInitEach('.check-all', (checkAll) => {
  checkAll.addEventListener('change', () => {
    const { checked } = checkAll
    const container = checkAll.closest('.check-all-container')
    if (!container || !container.parentElement) return
    Array.from(container.parentElement.children)
      .filter(
        (sibling) =>
          sibling !== container && sibling.classList.contains('node-container'),
      )
      .forEach((sibling) => {
        const checkbox = sibling.querySelector(
          ':scope > .checkbox-node input[type=checkbox]',
        )
        if (checkbox) {
          checkbox.checked = checked
          checkbox.dispatchEvent(new Event('change', { bubbles: true }))
        }
      })
  })
})
