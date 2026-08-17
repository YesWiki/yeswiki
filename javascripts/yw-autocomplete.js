window.ywAutocomplete = function (fieldParam, options = {}) {
  const field = fieldParam
  const maxItems = options.items || 8
  const minLength = options.minLength || 0
  const renderLabel = options.render || ((item) => String(item))
  const { source } = options
  const onSelect = options.onSelect || (() => {})

  let list = null
  let currentItems = []

  function closeList() {
    if (list) {
      list.remove()
      list = null
    }
  }

  function openList() {
    closeList()
    list = document.createElement('ul')
    list.className = 'yw-suggestions'
    list.style.position = 'absolute'
    list.style.top = `${field.offsetTop + field.offsetHeight}px`
    list.style.left = `${field.offsetLeft}px`
    list.style.minWidth = `${field.offsetWidth}px`
    if (field.parentNode && !field.parentNode.style.position) {
      field.parentNode.style.position = 'relative'
    }
    field.parentNode.insertBefore(list, field.nextSibling)
  }

  function select(item) {
    field.value = renderLabel(item)
    closeList()
    onSelect(item)
    field.dispatchEvent(new Event('input', { bubbles: true }))
  }

  function renderItems(matchedItems) {
    currentItems = matchedItems.slice(0, maxItems)
    if (!currentItems.length) {
      closeList()
      return
    }
    openList()
    currentItems.forEach((item) => {
      const li = document.createElement('li')
      const button = document.createElement('button')
      button.type = 'button'
      button.textContent = renderLabel(item)
      button.addEventListener('mousedown', (e) => {
        e.preventDefault()
        select(item)
      })
      li.appendChild(button)
      list.appendChild(li)
    })
  }

  function handleQuery() {
    const query = field.value
    if (query.length < minLength) {
      closeList()
      return
    }
    const result = typeof source === 'function' ? source(query) : source
    Promise.resolve(result).then((matchedItems) =>
      renderItems(matchedItems || []),
    )
  }

  field.addEventListener('input', handleQuery)
  field.addEventListener('focus', handleQuery)
  field.addEventListener('blur', () => {
    setTimeout(closeList, 150)
  })
  field.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeList()
    if (e.key === 'Enter' && currentItems.length) {
      e.preventDefault()
      select(currentItems[0])
    }
  })
}
