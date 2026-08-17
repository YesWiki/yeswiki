ywInitEach('.tag-label', (label) => {
  label.addEventListener('mouseenter', () => {
    label.classList.add('label-primary')
    label.classList.remove('label-info')
  })
  label.addEventListener('mouseleave', () => {
    if (!label.classList.contains('label-active')) {
      label.classList.add('label-info')
      label.classList.remove('label-primary')
    }
  })
})

ywInitEach('body', () => {
  function closePopovers() {
    document
      .querySelectorAll('.yw-popover.tag-popover')
      .forEach((popover) => popover.remove())
  }

  document.querySelectorAll('.tag-link').forEach((link) => {
    link.addEventListener('click', (e) => {
      e.preventDefault()
      const alreadyOpen =
        link.nextElementSibling &&
        link.nextElementSibling.classList.contains('tag-popover')
      closePopovers()
      if (alreadyOpen) return
      const popover = document.createElement('div')
      popover.className = 'yw-popover tag-popover'
      popover.style.cssText =
        'position: absolute; z-index: 1000; max-width: 320px;' +
        ' background: #fff; border: 1px solid rgba(0, 0, 0, 0.2); border-radius: 6px;' +
        ' box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2); padding: 8px 12px;'
      const title = document.createElement('div')
      title.className = 'tag-popover__title'
      title.innerHTML = link.dataset.title || ''
      const content = document.createElement('div')
      content.className = 'tag-popover__content'
      content.innerHTML = link.dataset.content || ''
      popover.appendChild(title)
      popover.appendChild(content)
      link.insertAdjacentElement('afterend', popover)
      const linkRect = link.getBoundingClientRect()
      const popRect = popover.getBoundingClientRect()
      popover.style.left = `${link.offsetLeft + (linkRect.width - popRect.width) / 2}px`
      popover.style.top = `${link.offsetTop - popRect.height - 6}px`
    })
  })

  document.addEventListener('click', (e) => {
    if (e.target.closest('.btn-close-popover')) {
      e.preventDefault()
      closePopovers()
    }
  })
})
